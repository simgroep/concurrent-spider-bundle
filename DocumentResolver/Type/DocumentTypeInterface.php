<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use VDB\Spider\Resource;

/**
 *
 * @author lkalinka
 */
interface DocumentTypeInterface
{
    /**
     * Get data from resource
     *
     * @param Resource $resource
     */
    public function getData(Resource $resource);

    /**
     * Extract content field from resource
     *
     * @param Resource $resource
     */
    public function extractContentFromResource(Resource $resource);

}
