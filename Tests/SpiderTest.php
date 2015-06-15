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

        $spider->setBlacklist(array());

        $eventDispatcher
            ->expects($this->at(1))
            ->method('dispatch')
            ->with(
                $this->equalTo('spider.crawl.post_discover'),
                $this->callback(
                    function (GenericEvent $event) {
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

    /**
     * @dataProvider blacklistedDataProvider
     */
    public function testIsUrlBlacklisted($url, $pattern)
    {
        $uri = new Uri($url);

        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $eventDispatcher = $this
            ->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->setMethods(array('dispatch'))
            ->getMock();

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->equalTo('spider.crawl.blacklisted'),
                $this->callback(
                    function (GenericEvent $event) use ($url) {
                        $uri = $event->getArgument('uri');

                        return ($uri->toString() === $url);
                    }
                )
            );

        $spider = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Spider')
            ->setConstructorArgs(array($eventDispatcher, $requestHandler, $persistenceHandler))
            ->setMethods(null)
            ->getMock();

        $spider->setBlacklist(array($pattern));

        $this->assertTrue($spider->isUrlBlacklisted($uri));
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
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
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

        $spider->setBlacklist(['http://www.simgroep.nl/']);

        $this->assertTrue($spider->isUrlBlacklisted($uri));
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
            ->setMethods(array())
            ->getMock();
        
        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(array())
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

        $spider->setBlacklist(array($pattern));

        $this->assertFalse($spider->isUrlBlacklisted($uri));
    }

    /**
     * @test
     */
    public function objectPropertyGetters()
    {
        $requestHandler = $this
            ->getMockBuilder('VDB\Spider\RequestHandler\GuzzleRequestHandler')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $persistenceHandler = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
            ->disableOriginalConstructor()
            ->setMethods(array())
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

        $this->assertEquals($requestHandler, $spider->getRequesthandler());
        $this->assertEquals($eventDispatcher, $spider->getEventDispatcher());
    }

    public function blacklistedDataProvider()
    {
        return array(
            array('http://www.simgroep.nl/internet/medewerkers_41499/', 'http:\/\/www\.simgroep\.nl\/internet\/.*'),
            array('http://www.simgroep.nl/internet/medewerkers_41499/andrew_8295.html', 'http:\/\/www\.simgroep\.nl\/internet\/medewerkers_41499\/.*'),
            array('http://www.simgroep.nl/internet/medewerkers_41499/anne-marie_8287.html', 'http:\/\/www\.simgroep\.nl\/internet\/medewerkers_41499\/.*'),
            array('http://www.simgroep.nl/internet/medewerkers_41499/anne-marie_8287.html', '(internet|medewerker)'),
            array('http://www.simgroep.nl/internet/medewerkers_41499/anne-marie_8287.html', '\.html$'),
        );
    }

    public function notBlacklistedDataProvider()
    {
        return array(
            array('http://www.simgroep.nl/internet/medewerkers_41499/', 'http:\/\/www\.simgroep\.nl\/intranet\/.*'),
            array('http://www.simgroep.nl/internet/nieuws-uit-de-branche_41509/', 'http:\/\/www\.simgroep\.nl\/beheer\/.*'),
            array('http://www.simgroep.nl/internet/portfolio_41515/#search', 'http:\/\/www\.simgroep\.nl\/internet\/portfolio_41516.*'),
            array('http://www.simgroep.nl/internet/vacatures_41521/', 'http:\/\/www\.simgroep\.nl\/intermet\/vacatures\/.*'),
        );
    }
}
