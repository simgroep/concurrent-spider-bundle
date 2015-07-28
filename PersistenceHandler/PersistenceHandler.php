<?php
namespace Simgroep\ConcurrentSpiderBundle\PersistenceHandler;

use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;

interface PersistenceHandler
{
    public function persist(Resource $resource, CrawlJob $crawlJob);
}
