<?php

namespace Simgroep\ConcurrentSpiderBundle\PersistenceHandler;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;

class RabbitMqPersistenceHandlerTest extends PHPUnit_Framework_TestCase
{

    public function testPersist()
    {
        $queue = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['publish', '__destruct'])
                ->getMock();

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->getMock();

        $documentResolver = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver')
                ->disableOriginalConstructor()
                ->setMethods(['resolveTypeFromResource', 'getData'])
                ->getMock();
        $documentResolver
                ->expects($this->once())
                ->method('resolveTypeFromResource')
                ->with($resource);
        $documentResolver
                ->expects($this->once())
                ->method('getData')
                ->will($this->returnValue(array(1)));

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $documentResolver);

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com');

        $this->assertNull($persistenceHandler->persist($resource, $crawlJob));
    }

}
