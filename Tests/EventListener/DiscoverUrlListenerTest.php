<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\EventListener;

use Symfony\Component\EventDispatcher\GenericEvent;
use Simgroep\ConcurrentSpiderBundle\EventListener\DiscoverUrlListener;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use PHPUnit_Framework_TestCase;
use VDB\Uri\Uri;

class DiscoverUrlListenerTest extends PHPUnit_Framework_TestCase
{
    public function testIfUrlIsSkippedWhenAlreadyIndexed()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $queue
            ->expects($this->never())
            ->method('publish');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(true));

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com');

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $uri = new Uri('https://github.com');
        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer);
        $listener->onDiscoverUrl($event);
    }

    public function testIfUrlIsIndexed()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com'))
            ->will($this->returnValue(false));

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com');

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $uri = new Uri('https://github.com');

        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer);
        $listener->onDiscoverUrl($event);
    }

    public function testIfShebangIsRemoved()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['isUrlIndexed'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('isUrlIndexed')
            ->with($this->equalTo('https://github.com/test/'))
            ->will($this->returnValue(true));

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com');

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));


        $uri = new Uri('https://github.com/test/#shebang');

        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer);
        $listener->onDiscoverUrl($event);
    }
}
