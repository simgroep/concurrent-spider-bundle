<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\Event;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\PageBlacklistedException;
use Simgroep\ConcurrentSpiderBundle\PersistableDocument;
use Simgroep\ConcurrentSpiderBundle\Event\PersistenceEvent;
use Simgroep\ConcurrentSpiderBundle\EventListener\PersistenceEventListener;

class PersistenceEventListenerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function isDefaultRevisitFactorAddedWhenDocumentHasNeverBeenIndexed()
    {
        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();
        $document->offsetSet('collection', 'test_collection');

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 50, 50, 50);
        $eventListener->onPrePersistDocument($event);

        $this->assertEquals(50, $document['revisit_after']);
    }

    /**
     * @testdox Test if the revisit_after value is doubled because content or title hasn't changed.
     * @test
     */
    public function isRevisitFactorIncreased()
    {
        $currentDocument = new PersistableDocument();
        $currentDocument['revisit_after'] = 3600;
        $currentDocument['title'] = 'test';
        $currentDocument['strippedContent'] = 'test';

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('findDocumentByUrl')
            ->will($this->returnValue($currentDocument));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();
        $document['title'] = 'test';
        $document['strippedContent'] = 'test';
        $document['collection'] = 'test';

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 10, 10000, 50);
        $eventListener->onPrePersistDocument($event);

        $this->assertEquals(7200, $document['revisit_after']);
    }

    /**
     * @testdox Test if the revisit_after value is divided because content or title has changed.
     * @test
     */
    public function isRevisitFactorDecreased()
    {
        $currentDocument = new PersistableDocument();
        $currentDocument['revisit_after'] = 3600;
        $currentDocument['title'] = 'test';
        $currentDocument['strippedContent'] = 'test';

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('findDocumentByUrl')
            ->will($this->returnValue($currentDocument));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();
        $document['title'] = 'test1';
        $document['strippedContent'] = 'test1';
        $document['collection'] = 'test1';

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 10, 10000, 50);
        $eventListener->onPrePersistDocument($event);

        $this->assertEquals(1800, $document['revisit_after']);
    }

    /**
     * @testdox Test if the revisit_after value is set to the maximum when content or title hasn't changed.
     * @test
     */
    public function isRevisitFactorMaximized()
    {
        $currentDocument = new PersistableDocument();
        $currentDocument['revisit_after'] = 3600;
        $currentDocument['title'] = 'test';
        $currentDocument['strippedContent'] = 'test';

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('findDocumentByUrl')
            ->will($this->returnValue($currentDocument));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();
        $document['title'] = 'test';
        $document['strippedContent'] = 'test';
        $document['collection'] = 'test';

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 10, 5000, 50);
        $eventListener->onPrePersistDocument($event);

        $this->assertEquals(5000, $document['revisit_after']);
    }

    /**
     * @testdox Test if the revisit_after value is set to the minimum when content or title has changed.
     * @test
     */
    public function isRevisitFactorMinimized()
    {
        $currentDocument = new PersistableDocument();
        $currentDocument['revisit_after'] = 3600;
        $currentDocument['title'] = 'test';
        $currentDocument['strippedContent'] = 'test';

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('findDocumentByUrl')
            ->will($this->returnValue($currentDocument));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();
        $document['title'] = 'test1';
        $document['strippedContent'] = 'test1';
        $document['collection'] = 'test1';

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 2000, 7200, 50);
        $eventListener->onPrePersistDocument($event);

        $this->assertEquals(2000, $document['revisit_after']);
    }

    /**
     * @expectedException Simgroep\ConcurrentSpiderBundle\PageBlacklistedException
     * @expectedExceptionMessage Page blacklisted in segments
     */
    public function testIfPageBlacklistedExceptionIsThrownWhenCollectionIsNotSet()
    {
        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 50, 50, 50);
        $eventListener->onPrePersistDocument($event);
    }

    /**
     * @dataProvider metaKeysWithBlacklistPhraseDataProvider
     */
    public function testIPageBlacklistedExceptionWhenBlacklistKeywordFound($metaKey)
    {
        $this->setExpectedException(
            PageBlacklistedException::class,
            sprintf('Page blacklisted by meta %s', $metaKey)
        );

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(array('findDocumentByUrl'))
            ->getMock();

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $document = new PersistableDocument();
        $document['collection'] = 'test1';
        $document[$metaKey] = sprintf('test1, %s, test2',PersistenceEventListener::BLACKLIST_KEYWORD);

        $event = new PersistenceEvent($document, $resource, array());

        $eventListener = new PersistenceEventListener($indexer, 50, 50, 50);
        $eventListener->onPrePersistDocument($event);
    }

    public function metaKeysWithBlacklistPhraseDataProvider()
    {
        return [
            ['keywords'],
            ['SIM.item_trefwoorden'],
        ];
    }
}
