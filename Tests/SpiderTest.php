<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\CurlClient;
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
        $dom = new \DOMDocument('1.0', 'utf-8');

        $node1 = $dom->createElement('a', 'https://github.com/');
        $node1->setAttribute("rel", "nofollow");
        $node2 = $dom->createElement('loc', 'https://github.com/');

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $crawler = $this
            ->getMockBuilder('Symfony\Component\DomCrawler\Crawler')
            ->disableOriginalConstructor()
            ->setMethods(['filterXpath'])
            ->getMock();

        $crawler
            ->expects($this->exactly(2))
            ->method('filterXpath')
            ->with($this->callback(function ($subject) {
                if ($subject === "//a" || $subject === "//loc") {
                    return true;
                }
                return false;
            }))
            ->will($this->returnValue([$node1, $node2, $node2]));

        $uri = new Uri('https://github.com/test');

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getBody'])
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue('<html><body>test</body></html>'));

        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getCrawler', 'getUri', 'getResponse'])
            ->getMock();

        $resource
            ->expects($this->exactly(2))
            ->method('getCrawler')
            ->will($this->returnValue($crawler));

        $resource
            ->expects($this->once())
            ->method('getUri')
            ->will($this->returnValue($uri));

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

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

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        /** @var Spider $spider */
        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
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
                            'https://github.com/nofollow',
                            'https://github.com/test'
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

        $spider->crawl($crawlJob, $queueFactory, 'queueName');

        $this->assertEquals('https://github.com/test/', $spider->getCurrentCrawlJob()->getUrl());
    }

    /**
     * @test
     */
    public function responseWithDocument()
    {
        $url = 'https://github.com/test';

        $queue = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
            ->disableOriginalConstructor()
            ->setMethods(['publish', '__destruct'])
            ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish')
            ->will($this->returnValue(true));

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getQueue'])
            ->getMock();

        $queueFactory
            ->expects($this->once())
            ->method('getQueue')
            ->will($this->returnValue($queue));

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['request'])
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\PersistenceHandler\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        /** @var Spider $spider */
        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
            ->setMethods(['getStatusCode', 'getRedirectUrl'])
            ->getMock();

        $curlClient
            ->expects($this->once())
            ->method('isDocument')
            ->will($this->returnValue(true));

        $crawlJob = new CrawlJob($url, $url);

        $spider->crawl($crawlJob, $queueFactory, 'queueName');
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

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
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

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals($persistenceHandler, $spider->getPersistenceHandler());

    }

    public function testIfEmptyStringResourceBodyThenRequestHandlerReadsPageAgainWithCookiePlugin()
    {
        $dom = new \DOMDocument('1.0', 'utf-8');

        $node1 = $dom->createElement('a', 'https://github.com/');
        $node1->setAttribute("rel", "nofollow");
        $node2 = $dom->createElement('loc', 'https://github.com/');

        $queueFactory = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\QueueFactory')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $crawler = $this
            ->getMockBuilder('Symfony\Component\DomCrawler\Crawler')
            ->disableOriginalConstructor()
            ->setMethods(['filterXpath'])
            ->getMock();

        $crawler
            ->expects($this->exactly(2))
            ->method('filterXpath')
            ->with($this->callback(function ($subject) {
                if ($subject === "//a" || $subject === "//loc") {
                    return true;
                }
                return false;
            }))
            ->will($this->returnValue([$node1, $node2, $node2]));

        $uri = new Uri('https://github.com/test');

        $curlClient = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getBody'])
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue(''));

        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getCrawler', 'getUri', 'getResponse'])
            ->getMock();

        $resource
            ->expects($this->exactly(2))
            ->method('getCrawler')
            ->will($this->returnValue($crawler));

        $resource
            ->expects($this->once())
            ->method('getUri')
            ->will($this->returnValue($uri));

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(['request'])
            ->getMock();

        $requestHandler
            ->expects($this->exactly(2))
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

        $cookiePlugin = $this
            ->getMockBuilder('Guzzle\Plugin\Cookie\CookiePlugin')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        /** @var Spider $spider */
        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs([$eventDispatcher, $requestHandler, $persistenceHandler, $curlClient, $cookiePlugin])
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
                            'https://github.com/nofollow',
                            'https://github.com/test'
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

        $spider->crawl($crawlJob, $queueFactory, 'queueName');

        $this->assertEquals('https://github.com/test/', $spider->getCurrentCrawlJob()->getUrl());
    }
}
