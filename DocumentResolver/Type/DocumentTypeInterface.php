<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use VDB\Spider\Resource;

interface DocumentTypeInterface
{
    /**
     * Returns data extraced from resource
     *
     * @param \VDB\Spider\Resource $resource
     */
    public function getData(Resource $resource);

    /**
     * Extract content field from resource
     *
     * @param \VDB\Spider\Resource $resource
     */
    public function extractContentFromResource(Resource $resource);
}
