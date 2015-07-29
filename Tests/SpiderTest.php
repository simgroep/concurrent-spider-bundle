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
