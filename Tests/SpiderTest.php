<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\Spider;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use VDB\Uri\Uri;
use Symfony\Component\EventDispatcher\GenericEvent;

class SpiderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function isDoubleSlashIsRemoved()
    {
        $node = $this
            ->getMockBuilder('DOMNode')
            ->disableOriginalConstructor()
            ->setMethods(['getAttribute'])
            ->getMock();

        $node
            ->expects($this->exactly(2))
            ->method('getAttribute')
            ->with($this->equalTo('href'))
            ->will($this->onConsecutiveCalls('/', 'aboutus'));

        $crawler = $this
            ->getMockBuilder('Symfony\Component\DomCrawler\Crawler')
            ->disableOriginalConstructor()
            ->setMethods(['filterXpath'])
            ->getMock();

        $crawler
            ->expects($this->once())
            ->method('filterXpath')
            ->with($this->equalTo('//a'))
            ->will($this->returnValue([$node, $node]));

        $uri = new Uri('https://github.com/test');

        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getCrawler', 'getUri'])
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getCrawler')
            ->will($this->returnValue($crawler));

        $resource
            ->expects($this->exactly(2))
            ->method('getUri')
            ->will($this->returnValue($uri));

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['request'])
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('request')
            ->with($this->isInstanceOf('VDB\Uri\Uri'))
            ->will($this->returnValue($resource));

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(['persist'])
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

        /** @var Spider $spider */
        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->setMethods(null)
            ->getMock();

        $eventDispatcher
            ->expects($this->at(1))
            ->method('dispatch')
            ->with(
                $this->equalTo('spider.crawl.post_discover'),
                $this->callback(
                    function (GenericEvent $event) {
                        $uris = $event->getArgument('uris');
                        $validUris = [
                            'https://github.com/',
                            'https://github.com/aboutus',
                        ];

                        foreach ($uris as $uri) {
                            if (!in_array($uri->toString(), $validUris)) {
                                return false;
                            }
                        }

                        return true;
                    }
                )
            );

        $crawlJob = new CrawlJob('https://github.com/test', 'https://github.com/test');

        $spider->crawl($crawlJob);

        $this->assertEquals('https://github.com/test', $spider->getCurrentCrawlJob()->getUrl());
    }

    public function testGetBlacklistReturnCorrectValue()
    {
        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

        /** @var Spider $spider */
        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->setMethods(null)
            ->getMock();

        $this->assertTrue($spider->isUrlBlacklisted($uri, [$pattern]));
    }

    public function testIsLiteralUrlBlacklisted()
    {
        $uri = new Uri('http://www.simgroep.nl/');

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

        $eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->setMethods(null)
            ->getMock();

        $this->assertTrue($spider->isUrlBlacklisted($uri, ['http://www.simgroep.nl/']));
    }

    /**
     * @dataProvider notBlacklistedDataProvider
     */
    public function testUrlIsNotBlacklisted($url, $pattern)
    {
        $uri = new Uri($url);

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->setMethods(null)
            ->getMock();

        $this->assertFalse($spider->isUrlBlacklisted($uri, [$pattern]));
    }

    /**
     * @test
     */
    public function objectPropertyGetters()
    {
        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals($requestHandler, $spider->getRequesthandler());
        $this->assertEquals($eventDispatcher, $spider->getEventDispatcher());
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

    /**
     * @test
     */
    public function canGetPersistenceHandler()
    {
        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals($persistenceHandler, $spider->getPersistenceHandler());

    }
}
