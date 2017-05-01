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
            ->getMock();

        $uri = new Uri('https://github.com');

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->withConsecutive(['uris' => [$uri]], ['core'])
            ->will($this->returnValue([]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);

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

        $uri = $this
            ->getMockbuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['normalize'])
            ->getMock();

        $uri
            ->expects($this->any())
            ->method('normalize')
            ->willReturn(new Uri('https://github.com'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['filterIndexedAndNotExpired'])
            ->getMock();

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->withConsecutive(['uris' => [$uri]], ['core'])
            ->will($this->returnValue([$uri]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->setMethods(['smembers', 'sadd', 'expire'])
            ->getMock();

        $redis
            ->expects($this->once())
            ->method('smembers')
            ->will($this->returnValue([]));

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
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
            ->setMethods(['filterIndexedAndNotExpired'])
            ->getMock();

        $uri = new Uri('https://github.com/test/#shebang');

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->withConsecutive(['uris' => [$uri]], ['core'])
            ->will($this->returnValue([$uri]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->setMethods(['smembers', 'sadd', 'expire'])
            ->getMock();

        $redis
            ->expects($this->once())
            ->method('smembers')
            ->will($this->returnValue([]));

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));


        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
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
            ->setMethods(['filterIndexedAndNotExpired'])
            ->getMock();

        $uri = new Uri($url);

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->withConsecutive(['uris' => [$uri]], ['core'])
            ->will($this->returnValue([$uri]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [$pattern], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $event = new GenericEvent($spider, ['uris' => [$uri]]);

        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
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
            ->setMethods(['filterIndexedAndNotExpired'])
            ->getMock();

        $uri = new Uri($url);

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->withConsecutive(['uris' => [$uri]], ['core'])
            ->will($this->returnValue([$uri]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $crawlJob = new CrawlJob('https://github.com', 'https://github.com', [$pattern], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $event = new GenericEvent($spider, ['uris' => [$uri]]);

        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
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
            ['http://www.simgroep.nl/internet/portfolio_41515/search/', 'http:\/\/www\.simgroep\.nl\/internet\/portfolio_41516.*'],
            ['http://www.simgroep.nl/internet/vacatures_41521/', 'http:\/\/www\.simgroep\.nl\/intermet\/vacatures\/.*'],
        ];
    }

    public function testUrlWithSpace()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish');

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['filterIndexedAndNotExpired'])
            ->getMock();

        $uri = new Uri('http://www.socialedienstbommelerwaard.nl/Digitaal loket');

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->withConsecutive(['uris' => [$uri]], ['core'])
            ->will($this->returnValue([$uri]));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->setMethods(['smembers', 'sadd', 'expire'])
            ->getMock();

        $redis
            ->expects($this->once())
            ->method('smembers')
            ->will($this->returnValue([]));

        $crawlJob = new CrawlJob('http://www.socialedienstbommelerwaard.nl/Digitaal loket', 'http://www.socialedienstbommelerwaard.nl', [], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
        $listener->onDiscoverUrl($event);
    }

    public function testIfUrlIsAlreadyInQueue()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['getName', '__destruct'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('coreName_queueName'));

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['filterIndexedAndNotExpired', 'getHashSolarId'])
            ->getMock();

        $uri = new Uri('https://github.com');

        $indexer
            ->expects($this->once())
            ->method('filterIndexedAndNotExpired')
            ->will($this->returnValue([$uri]));

        $indexer
            ->expects($this->exactly(2))
            ->method('getHashSolarId')
            ->will($this->returnValue(sha1(strtolower($uri))));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->setMethods(['smembers', 'sadd', 'expire'])
            ->getMock();

        $redis
            ->expects($this->once())
            ->method('smembers')
            ->will($this->returnValue([sha1(strtolower($uri))]));

        $crawlJob = new CrawlJob($uri, $uri, [], ["core" => "coreName"]);

        $spider = $this
            ->getMockbuilder('Simgroep\ConcurrentSpiderbundle\Spider')
            ->disableOriginalConstructor()
            ->setMethods(['getCurrentCrawlJob'])
            ->getMock();

        $spider
            ->expects($this->once())
            ->method('getCurrentCrawlJob')
            ->will($this->returnValue($crawlJob));

        $event = new GenericEvent($spider, ['uris' => [$uri]]);
        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
        $listener->onDiscoverUrl($event);
        $result = $listener->isAlreadyInQueue($uri);

        $this->assertTrue($result);
    }

    public function testIfUrlIsNotAlreadyInQueue()
    {
        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->getMock();

        $indexer = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Indexer')
            ->disableOriginalConstructor()
            ->setMethods(['getHashSolarId'])
            ->getMock();

        $uri = new Uri('https://github.com');

        $indexer
            ->expects($this->once())
            ->method('getHashSolarId')
            ->will($this->returnValue(sha1(strtolower($uri))));

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $redis = $this
            ->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->setMethods(['smembers', 'scard', 'sadd', 'expire'])
            ->getMock();

        $listener = new DiscoverUrlListener($queue, $indexer, $eventDispatcher, $redis, 900);
        $result = $listener->isAlreadyInQueue($uri);

        $this->assertFalse($result);
    }

}
