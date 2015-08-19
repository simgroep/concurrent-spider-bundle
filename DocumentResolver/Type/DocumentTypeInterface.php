<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use VDB\Spider\Resource;

/**
 *
 * @author lkalinka
 */
interface DocumentTypeInterface
{
    public function getData(Resource $resource);
}
