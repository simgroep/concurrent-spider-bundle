<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Solarium\Client;
use Solarium\QueryType\Update\Query\Query;
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
     * @var integer
     */
    private $hoursBeforeExpired = 8;

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
        $expiresBeforeDate = new DateTime();
        $expiresBeforeDate->modify(sprintf('-%s hour', $this->hoursBeforeExpired));

        $queryPhrase = sprintf(
            "id:%s AND updatedDate:[%s TO %s]",
            sha1($uri),
            $expiresBeforeDate->format('Y-m-d\TH:i:s\Z'),
            $currentDate->format('Y-m-d\TH:i:s\Z')
        );

        $query = $this->client->createSelect();
        $query->setQuery($queryPhrase);

        $result = $this->client->select($query);

        return ($result->getNumFound() > 0);
    }

    public function findDocumentByUrl($url, $metadata)
    {
        $this->setCoreNameFromMetadata($metadata);

        $query = $this->client->createSelect();
        $query->setQuery(sprintf('id:%s', sha1($url)));

        $result = $this->client->select($query);

        return ($result->getNumFound() == 0) ? null : $result;

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

        $expiresBeforeDate = new DateTime();
        $expiresBeforeDate->modify(sprintf('-%s hour', $this->hoursBeforeExpired));

        $queryPhrase = sprintf("updatedDate:[* TO %s]", $expiresBeforeDate->format('Y-m-d\TH:i:s\Z'));

        $query = $this->client->createSelect()
            ->setQuery($queryPhrase)
            ->setRows(1000);

        return $this->client->select($query);
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
     * Remove document from solr.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @param array $metadata
     */
    public function deleteDocument(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        $this->setCoreNameFromMetadata($data['metadata']);

        $updateQuery = $this->client->createUpdate();
        $updateQuery->addDeleteById(sha1($data['url']));
        $updateQuery->addCommit();

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
        $updateQuery->addCommit();

        $this->client->update($updateQuery);
    }
}
