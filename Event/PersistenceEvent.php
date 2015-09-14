<?php

namespace Simgroep\ConcurrentSpiderBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Simgroep\ConcurrentSpiderBundle\PersistableDocument;
use VDB\Spider\Resource;

class PersistenceEvent extends Event
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\PersistableDocument
     */
    private $document;

    /**
     * @var \VDB\Spider\Resource
     */
    private $resource;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\PersistableDocument $document
     * @param \VDB\Spider\Resource                                 $resource
     */
    public function __construct(PersistableDocument $document, Resource $resource)
    {
        $this->document = $document;
        $this->resource = $resource;
    }

    /**
     * Returns the document.
     *
     * @return \Simgroep\ConcurrentSpiderBundle\PersistableDocument
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Returns the resource that is crawled.
     *
     * @return \VDB\Spider\Resource
     */
    public function getResource()
    {
        return $this->resource;
    }
}

