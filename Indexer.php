<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Solarium\Client;
use Solarium\QueryType\Update\Query\Query;

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
     * Constructor.
     *
     * @param \Solarium\Client $client
     * @param array $mapping
     */
    public function __construct(Client $client, array $mapping)
    {
        $this->client = $client;
        $this->mapping = $mapping;
    }

    /**
     * Indicates whether an URL already has been indexed or not.
     *
     * @param string $uri
     * @param array  $metadata
     *
     * @return boolean
     */
    public function isUrlIndexed($uri, array $metadata = [])
    {
        $this->setCoreNameFromMetadata($metadata);

        $query = $this->client->createSelect();
        $query->setQuery(sprintf("id:%s", sha1($uri)));

        $result = $this->client->select($query);

        return ($result->getNumFound() > 0);
    }

    /**
     * Make a document ready to be indexed.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @param array $metadata
     */
    public function prepareDocument(AMQPMessage $message, array $metadata = [])
    {
        $data = json_decode($message->body, true);

        $this->setCoreNameFromMetadata($metadata);

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

        $this->documents[] = $document;

        if (count($this->documents) >= 10) {
            $this->addDocuments($updateQuery, $this->documents, $metadata);
            $this->documents = [];
        }
    }

    /**
     * Set Core Name to write/read data
     *
     * @param array $metadata
     */
    protected function setCoreNameFromMetadata(array $metadata)
    {
        if (array_key_exists('core', $metadata)) {
            $this->client->getEndpoint()->setCore($metadata['core']);
        }
    }

     /**
     * Add multiple documents to the data store.
     *
     * @param \Solarium\QueryType\Update\Query\Query $updateQuery
     * @param array $documents
     * @param array $metadata
     */
    protected function addDocuments(Query $updateQuery, array $documents, array $metadata = [])
    {
        $updateQuery->addDocuments($documents);
        $updateQuery->addCommit();

        $this->client->update($updateQuery);
    }
}
