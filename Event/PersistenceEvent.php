<?php

namespace Simgroep\ConcurrentSpiderBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Simgroep\ConcurrentSpiderBundle\PersistableDocument;

class PersistenceEvent extends Event
{
    private $document;

    public function __construct(PersistableDocument $document)
    {
        $this->document = $document;
    }

    public function getDocument()
    {
        return $this->document;
    }
}
