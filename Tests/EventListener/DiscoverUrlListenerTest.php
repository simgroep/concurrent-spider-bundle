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

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

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
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher);
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

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

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
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher);
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

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

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
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher);
        $listener->onDiscoverUrl($event);
    }

    /**
     * @dataProvider blacklistedDataProvider
     */
    public function testUrlIsBlacklisted($url, $pattern)
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

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [$pattern]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $uri = new Uri($url);

        $event = new GenericEvent($spider, ['uris' => [$uri]]);

        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher);
        $listener->onDiscoverUrl($event);
    }

    /**
     * @dataProvider notBlacklistedDataProvider
     */
    public function testUrlIsNotBlacklisted($url, $pattern)
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
            ->with($this->equalTo($url))
            ->will($this->returnValue(true));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();
        $eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [$pattern]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $uri = new Uri($url);

        $event = new GenericEvent($spider, ['uris' => [$uri]]);

        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher);
        $listener->onDiscoverUrl($event);
    }

    public function blacklistedDataProvider()
    {
        return [
            ['http://www.simgroep.nl/internet/medewerkers_41499/', '\/internet\/.*'],
            ['http://www.simgroep.nl/internet/medewerkers_41499/andrew_8295.html', '\/internet\/medewerkers_41499\/.*'],
            ['http://www.simgroep.nl/internet/medewerkers_41499/anne-marie_8287.html', '\/internet\/medewerkers_41499\/.*'],
            ['http://www.simgroep.nl/internet/medewerkers_41499/anne-marie_8287.html', '(internet|medewerker)'],
            ['http://www.simgroep.nl/internet/medewerkers_41499/anne-marie_8287.html', '\.html$'],
        ];
    }

    public function notBlacklistedDataProvider()
    {
        return [
            ['http://www.simgroep.nl/internet/medewerkers_41499/', 'http:\/\/www\.simgroep\.nl\/intranet\/.*'],
            ['http://www.simgroep.nl/internet/nieuws-uit-de-branche_41509/', 'http:\/\/www\.simgroep\.nl\/beheer\/.*'],
            ['http://www.simgroep.nl/internet/portfolio_41515/search', 'http:\/\/www\.simgroep\.nl\/internet\/portfolio_41516.*'],
            ['http://www.simgroep.nl/internet/vacatures_41521/', 'http:\/\/www\.simgroep\.nl\/intermet\/vacatures\/.*'],
        ];
    }

}
