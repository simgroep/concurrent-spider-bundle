<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use VDB\Uri\Uri;
use Symfony\Component\EventDispatcher\GenericEvent;

class SpiderTest extends PHPUnit_Framework_TestCase
{
    public function testIfDoubleSlashIsRemoved()
    {
        $node = $this
            ->getMockBuilder('DOMNode')
            ->disableOriginalConstructor()
            ->setMethods(array('getAttribute'))
            ->getMock();

        $node
            ->expects($this->exactly(3))
            ->method('getAttribute')
            ->with($this->equalTo('href'))
            ->will($this->onConsecutiveCalls('/', '#search', 'aboutus'));

        $crawler = $this
            ->getMockBuilder('Symfony\Component\DomCrawler\Crawler')
            ->disableOriginalConstructor()
            ->setMethods(array('filterXpath'))
            ->getMock();

        $crawler
            ->expects($this->once())
            ->method('filterXpath')
            ->with($this->equalTo('//a'))
            ->will($this->returnValue(array($node, $node, $node)));

        $uri = new Uri('https://github.com/test');

        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(array('getCrawler', 'getUri'))
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getCrawler')
            ->will($this->returnValue($crawler));

        $resource
            ->expects($this->exactly(3))
            ->method('getUri')
            ->will($this->returnValue($uri));

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(array('request'))
            ->getMock();

        $requestHandler
            ->expects($this->once())
            ->method('request')
            ->with($this->isInstanceOf('VDB\Uri\Uri'))
            ->will($this->returnValue($resource));

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(array('persist'))
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(array('dispatch'))
            ->getMock();

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs(array($eventDispatcher, $requestHandler, $persistenceHandler))
            ->setMethods(null)
            ->getMock();

        $eventDispatcher
            ->expects($this->at(1))
            ->method('dispatch')
            ->with(
                $this->equalTo('spider.crawl.post_discover'),
                $this->callback(
                    function(GenericEvent $event) {
                        $uris = $event->getArgument('uris');
                        $validUris = array(
                            'https://github.com/',
                            'https://github.com/test#search',
                            'https://github.com/aboutus',
                        );

                        foreach ($uris as $uri) {
                            if (!in_array($uri->toString(), $validUris)) {
                                return false;
                            }
                        }

                        return true;
                    }
                )
            );

        $spider->crawlUrl('https://github.com/test');
    }
}
