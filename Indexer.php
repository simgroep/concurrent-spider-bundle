<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Solarium\Client;
use Solarium\QueryType\Update\Query\Query;
use Solarium\Exception\HttpException;
use DateTime;

/**
 * This class provides a gateway to the datastore for spidered webpages.
 */
class Indexer
{
    /**
     * @var \Solarium\Client
     */
    private $client;

    /**
     * Holds the documents before submitting them to Solr
     *
     * @var array
     */
    private $documents = [];

    /**
     * @var array
     */
    private $mapping;

    /**
     * Amount of documents that should be kept in memory before they are saved to solr.
     *
     * @var integer
     */
    private $minimalDocumentSaveAmount;

    /**
     * Constructor.
     *
     * @param \Solarium\Client $client
     * @param array            $mapping
     * @param integer          $minimalDocumentSaveAmount
     */
    public function __construct(Client $client, array $mapping, $minimalDocumentSaveAmount)
    {
        $this->client = $client;
        $this->mapping = $mapping;
        $this->minimalDocumentSaveAmount = $minimalDocumentSaveAmount;
    }

    /**
     * Indicates whether an URL already has been indexed or not.
     *
     * @param string $uri
     * @param array  $metadata
     *
     * @return boolean
     */
    public function isUrlIndexedandNotExpired($uri, array $metadata = [])
    {
        $this->setCoreNameFromMetadata($metadata);

        $currentDate = new DateTime();

        $queryPhrase = sprintf(
            "id:%s AND revisit_expiration:[%s TO *]",
            sha1(strtolower($uri)),
            $currentDate->format('Y-m-d\TH:i:s\Z')
        );

        $query = $this->client->createSelect();
        $query->setQuery($queryPhrase);

        $result = $this->client->select($query);

        return ($result->getNumFound() > 0);
    }

    /**
     * Removes all documents of a complete core.
     *
     * @param string $core
     */
    public function emptyCore($core)
    {
        $this->setCoreNameFromMetadata(['core' => $core]);

        $update = $this->client->createUpdate();
        $update->addDeleteQuery('*:*');
        $update->addCommit();
        $this->client->update($update);
    }

    /**
     * Get document urls and id from solr.
     * @param array $metadata
     * @return null|\Solarium\Core\Plugin\PluginInterface
     */
    public function getDocumentUrlsInCore ($metadata) {
        $this->setCoreNameFromMetadata($metadata);

        $query = $this->client->createSelect();
        $query->setQuery('*:*');
        $query->setFields(['url', 'id']);
        $prefetch = $this->client->getPlugin('prefetchiterator');
        $prefetch->setPrefetch(100); //fetch 2 rows per query (for real world use this can be way higher)
        $prefetch->setQuery($query);
        return $prefetch;
    }

    /**
     * Returns the amount of documents in a core.
     *
     * @param string $core
     *
     * @return integer
     *
     * @throws Solarium\Exception\HttpException
     */
    public function getAmountDocumentsInCore($core)
    {
        $this->setCoreNameFromMetadata(['core' => $core]);

        $query = $this->client->createSelect();
        $query->setQuery('*:*');

        $result = $this->client->select($query);

        return $result->getNumFound();
    }

    /**
     * Returns a SOLR document based on the given URL.
     *
     * @param string $url
     * @param array  $metadata
     *
     * @return array
     */
    public function findDocumentByUrl($url, array $metadata = [])
    {
        $this->setCoreNameFromMetadata($metadata);

        $query = $this->client->createSelect();
        $query->setQuery(sprintf('id:%s', sha1(strtolower($url))));

        $result = $this->client->select($query);

        return ($result->getNumFound() == 0) ? null : $result->getDocuments()[0];

    }

    /**
     * Returns url's that are expired.
     *
     * @param string $core
     *
     * @return \Solarium\QueryType\Select\Result\Result
     */
    public function findExpiredUrls($core)
    {
        $this->setCoreNameFromMetadata(['core' => $core]);

        $now = new DateTime();

        $queryPhrase = sprintf("revisit_expiration:[* TO %s]", $now->format('Y-m-d\TH:i:s\Z'));

        $query = $this->client->createSelect()
            ->setQuery($queryPhrase)
            ->setRows(1000);

        return $this->client->select($query);
    }

    /**
     * Returns url's that are not indexed or indexed but expired.
     *
     * @param string $uri
     * @param array  $metadata
     *
     * @return boolean
     */
    public function isUrlNotIndexedOrIndexedAndExpired($uri, array $metadata = [])
    {
        $this->setCoreNameFromMetadata($metadata);

        $uriHash = sha1(strtolower($uri));
        $queryPhrase = sprintf(
            "id:%s",
            $uriHash
        );

        $query = $this->client->createSelect();
        $query->setQuery($queryPhrase);

        $result = $this->client->select($query);

        if ($result->getNumFound() < 1) {
            return true;
        }

        $now = new DateTime();

        $queryPhrase = sprintf(
            "id:%s AND revisit_expiration:[* TO %s]",
            $uriHash,
            $now->format('Y-m-d\TH:i:s\Z')
        );

        $query->setQuery($queryPhrase);

        $result = $this->client->select($query);

        return ($result->getNumFound() > 0);
    }

    /**
     * Make a document ready to be indexed.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function prepareDocument(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);
        $core = '';

        if (array_key_exists('core', $data['metadata'])) {
            $core = $data['metadata']['core'];
        }

        $updateQuery = $this->client->createUpdate();
        $document = $updateQuery->createDocument();

        foreach ($this->mapping as $field => $solrField) {

            if ($field === 'groups') {
                foreach ($solrField as $groupFieldName => $solrGroupFields) {
                    foreach ($solrGroupFields as $fieldName => $solrGroupFieldName) {

                        $composedFieldName = $groupFieldName . '.' . $fieldName;
                        $composedSolrFieldName = $groupFieldName . '.' . $solrGroupFieldName;

                        if (array_key_exists($composedFieldName, $data['document'])) {
                            $document->addField($composedSolrFieldName, $data['document'][$composedFieldName]);
                        }
                    }
                }
                continue;
            }

            if (array_key_exists($field, $data['document'])) {
                $document->addField($solrField, $data['document'][$field]);
            }
        }

        $this->documents[$core][] = $document;

        if (count($this->documents, true) >= $this->minimalDocumentSaveAmount) {
            foreach (array_keys($this->documents) as $core) {
                $this->setCoreNameFromMetadata(['core' => $core]);
                $updateQuery = $this->client->createUpdate();
                $this->addDocuments($updateQuery, $this->documents[$core]);
            }

            $this->documents = [];
        }
    }

    /**
     * Remove document from solr by AMQPMessage.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function deleteDocument(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        $this->deleteDocumentById($data['metadata'], sha1(strtolower($data['url'])));
    }

    /**
     * Remove document from solr by ID
     * @param array $metadata
     * @param string $document_id
     */
    public function deleteDocumentById ($metadata, $document_id) {
        $this->setCoreNameFromMetadata($metadata);

        $updateQuery = $this->client->createUpdate();
        $updateQuery->addDeleteById($document_id);

        $this->client->update($updateQuery);
    }


    /**
     * Set Core Name to write/read data
     *
     * @param array $metadata
     */
    protected function setCoreNameFromMetadata(array $metadata)
    {
        if (array_key_exists('core', $metadata)) {
            foreach($this->client->getEndPoints() as $endpoint) {
                $endpoint->setCore($metadata['core']);
            }
        }
    }

     /**
     * Add multiple documents to the data store.
     *
     * @param \Solarium\QueryType\Update\Query\Query $updateQuery
     * @param array $documents
     */
    protected function addDocuments(Query $updateQuery, array $documents)
    {
        $updateQuery->addDocuments($documents);

        $this->client->update($updateQuery);
    }
}
