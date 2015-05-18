<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Solarium_Client;

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
     * Constructor.
     *
     * @param \Solarium_Client $client
     */
    public function __construct(Solarium_Client $client)
    {
        $this->client = $client;
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
     * Add mulitple documents to the datastore.
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
}
