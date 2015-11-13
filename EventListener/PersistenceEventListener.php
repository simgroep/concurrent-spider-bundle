<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\PersistencEvent;

class PersistenceEventListener
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Indexer
     */
    private $indexer;

    /**
     * @var integer
     */
    private $minimalRevisitFactor;

    /**
     * @var integer
     */
    private $maximumRevisitFactor;

    /**
     * @var integer
     */
    private $defaultRevisitFactor;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param integer                                  $minimalRevisitFactor
     * @param integer                                  $maximumRevisitFactor
     * @param integer                                  $defaultRevisitFactor
     */
    public function __construct(Indexer $indexer, $minimalRevisitFactor, $maximumRevisitFactor, $defaultRevisitFactor)
    {
        $this->indexer = $indexer;
        $this->minimalRevisitFactor = $minimalRevisitFactor;
        $this->maximumRevisitFactor = $maximumRevisitFactor;
        $this->defaultRevisitFactor = $defaultRevisitFactor;
    }

    /**
     * Calculates what the desired revisit factor should be.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\PersistenceEvent $event
     */
    public function onPrePersistDocument(PersistenceEvent $event)
    {
        $newDocument = $event->getDocument();
        $currentDocument = $this->indexer->findDocumentByUrl($newDocument['url']);

        if (null === $currentDocument) {
            $document['revisit_after'] = $this->defaultRevisitFactor;
        }

        if ($this->createDocumentChecksum($oldDocument) === $this->createDocumentChecksum($newDocument)) {
            $this->increaseRevisitFactor($newDocument);
        } else {
            $this->decreaseRevisitFactor($newDocument);
        }
    }

    /**
     * Increase the time a document should be revisited.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\PersistableDocument $document
     *
     * @return \Simgroep\ConcurrentSpiderBundle\PersistableDocument
     */
    private function increaseRevisitFactor(PersistableDocument $document, $currentRevisitFactor)
    {
        if (($currentRevisitFactor * 2) > $this->maximumRevisitFactor) {
            $document['revisit_after'] = $this->maximumRevisitFactor;
        }

        $document['revisit_after'] = $currentRevisitFactor * 2;

        $expireDate = new DateTime();
        $expireDate->modify(sprintf('+%s minute', $document['revisit_after']));

        $document['revisit_expiration'] = $expireDate->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Decrease the time a document should be revisited.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\PersistableDocument $document
     *
     * @return \Simgroep\ConcurrentSpiderBundle\PersistableDocument
     */
    private function decreaseRevisitFactor(PersistableDocument $document, $currentRevisitFactor)
    {
        if (($currentRevisitFactor / 2) > $this->minimalRevisitFactor) {
            $document['revisit_after'] = $this->minimalRevisitFactor;
        }

        $document['revisit_after'] = $currentRevisitFactor / 2;

        $expireDate = new DateTime();
        $expireDate->modify(sprintf('+%s minute', $document['revisit_after']));

        $document['revisit_expiration'] = $expireDate->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Creates and returns a unique checksum of the document given.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\PersistableDocument $document
     *
     * @return string
     */
    private function createDocumentChecksum(PersistableDocument $document)
    {
        return sha1($document['title'] . $document['strippedContent']);
    }
}

