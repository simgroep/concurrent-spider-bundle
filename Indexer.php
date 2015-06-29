<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Solarium_Client;
use Solarium_Document_ReadWrite;

/**
 * This class provides a gateway to the datastore for spidered webpages.
 */
class Indexer
{
    /**
     * @var \Solarium_Client
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
     * @param \Solarium_Client $client
     * @param array $mapping
     */
    public function __construct(Solarium_Client $client, array $mapping)
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
     * Add multiple documents to the data store.
     *
     * @param array $documents
     * @param array $metadata
     */
    public function addDocuments(array $documents, array $metadata = [])
    {
        $this->setCoreNameFromMetadata($metadata);

        $update = $this->client->createUpdate();
        $update->addDocuments($documents);
        $update->addCommit();

        $this->client->update($update);
    }

    /**
     * Make a document ready to be indexed.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function prepareDocument(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        $document = new Solarium_Document_ReadWrite();

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
            $this->addDocuments($this->documents);
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
            $this->client->getAdapter()->setCore($metadata['core']);
        }
    }
}
