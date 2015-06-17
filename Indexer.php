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
     *
     * @return boolean
     */
    public function isUrlIndexed($uri)
    {
        $query = $this->client->createSelect();
        $query->setQuery(sprintf("id:%s", sha1($uri)));

        $result = $this->client->select($query);

        return ($result->getNumFound() > 0);
    }

    /**
     * Add multiple documents to the data store.
     *
     * @param array $documents
     */
    public function addDocuments(array $documents)
    {
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
                foreach ($this->mapping['groups'] as $groupFieldName => $solrGroupFields) {
                    foreach ($solrGroupFields as $fieldName => $solrGroupFieldName) {
                        $document->addField(
                            $groupFieldName . '.' . $solrGroupFieldName,
                            $data['document'][$groupFieldName . '.' . $fieldName]
                        );
                    }
                }
                continue;
            }

            $document->addField($solrField, $data['document'][$field]);
        }

        $this->documents[] = $document;

        if (count($this->documents) >= 10) {
            $this->addDocuments($this->documents);
            $this->documents = [];
        }
    }
}
