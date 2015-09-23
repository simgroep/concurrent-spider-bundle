<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Event;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\Event\PersistenceEvent;

class PersistenceEventTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function ifPersistenceEventReturnsCorrectObjects()
    {
        $document = $this->getMock('Simgroep\ConcurrentSpiderBundle\PersistableDocument');

        $resource = $this->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = ['core' => 'dummyCore'];

        $event = new PersistenceEvent($document, $resource, $metadata);

        $this->assertEquals($document, $event->getDocument());
        $this->assertEquals($resource, $event->getResource());
        $this->assertEquals($metadata, $event->getMetadata());
    }

}
