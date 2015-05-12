<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Solarium_Client;

class Indexer
{
    private $client;

    public function __construct(Solarium_Client $client)
    {
        $this->client = $client;
    }

    public function isUrlIndexed($uri)
    {
        $query = $this->client->createSelect();
        $query->setQuery(sprintf("id:%s", sha1($uri)));

        $result = $this->client->select($query);

        return ($result->getNumFound() > 0);
    }

    public function createUpdateTransaction()
    {
        return $this->client->createUpdate();
    }

    public function createDocument($transaction)
    {
        return $transaction->createDocument();
    }

    public function addDocuments(array $documents, $transaction)
    {
        $this->transaction->addDocuments($documents);
        $this->transaction->addCommit();

        $this->client->update($this->transaction);
    }
}
