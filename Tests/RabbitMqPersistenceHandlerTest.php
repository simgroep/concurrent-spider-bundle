<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler;

class RabbitMqPersistenceHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testIfCanHandleApplicationPdfContentType()
    {
        $queue = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['publish', '__destruct'])
                ->getMock();

        $pdfParser = $this
                ->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $persistenceHandler = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
                ->setConstructorArgs([$queue, $pdfParser])
                ->setMethods(['getDataFromPdfFile'])
                ->getMock();

        $persistenceHandler
                ->expects($this->once())
                ->method('getDataFromPdfFile')
                ->will($this->returnValue([]));

        $response = $this
                ->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();

        $response
                ->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('application/pdf'));

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse'])
                ->getMock();

        $resource
                ->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));

        $persistenceHandler->persist($resource);
    }

    public function testIfCanHandleTextHtmlContentType()
    {
        $queue = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['publish', '__destruct'])
                ->getMock();

        $pdfParser = $this
                ->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $persistenceHandler = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
                ->setConstructorArgs([$queue, $pdfParser])
                ->setMethods(['getDataFromWebPage'])
                ->getMock();

        $persistenceHandler
                ->expects($this->once())
                ->method('getDataFromWebPage')
                ->will($this->returnValue([]));

        $response = $this
                ->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();

        $response
                ->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('text/html'));

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse'])
                ->getMock();

        $resource
                ->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));

        $persistenceHandler->persist($resource);
    }

    /**
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function testInvalidContentException()
    {
        $queue = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['publish', '__destruct'])
                ->getMock();

        $pdfParser = $this
                ->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $persistenceHandler = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
                ->setConstructorArgs([$queue, $pdfParser])
                ->setMethods(['getDataFromPdfFile'])
                ->getMock();

        $persistenceHandler
                ->expects($this->once())
                ->method('getDataFromPdfFile')
                ->will($this->returnValue(utf8_decode('nön-json-value'))); # decode, so it should fail on json_encode()

        $response = $this
                ->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();

        $response
                ->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('application/pdf'));

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse'])
                ->getMock();

        $resource
                ->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));

        $persistenceHandler->persist($resource);
    }

    /**
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function testInvalidArgumentException()
    {
        $queue = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['publish', '__destruct'])
                ->getMock();

        $pdfParser = $this
                ->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $persistenceHandler = $this
                ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler')
                ->setConstructorArgs([$queue, $pdfParser])
                ->setMethods(['getDataFromWebPage'])
                ->getMock();

        $persistenceHandler
                ->expects($this->once())
                ->method('getDataFromWebPage')
                ->will($this->throwException(new \InvalidArgumentException()));

        $response = $this
                ->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();

        $response
                ->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('text/html'));

        $resource = $this
                ->getMockBuilder('\VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse'])
                ->getMock();

        $resource
                ->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));

        $persistenceHandler->persist($resource);
    }

    public function testPersistRetrieveValidDataFromPdfFile()
    {

        $queue = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['__destruct', 'publish'])
                ->getMock();

        $pdfDocument = $this->getMockBuilder('Smalot\PdfParser\Document')
                ->disableOriginalConstructor()
                ->setMethods(['getText'])
                ->getMock();
        $pdfDocument->expects($this->once())
                ->method('getText')
                ->will($this->returnValue('dummy text from pdf file'));

        $pdfParser = $this->getMockBuilder('Smalot\PdfParser\Parser')
                ->setMethods(['parseContent'])
                ->disableOriginalConstructor()
                ->getMock();
        $pdfParser->expects($this->once())
                ->method('parseContent')
                ->will($this->returnValue($pdfDocument));

        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();
        $response->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('application/pdf'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->once())
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/dummyfile.pdf'));

        $resource = $this->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse', 'getUri'])
                ->getMock();
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $pdfParser);

        $this->assertNull($persistenceHandler->persist($resource));
    }

    public function testPersistRetrieveValidDataFromWebPage()
    {

        $queue = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['__destruct', 'publish'])
                ->getMock();

        $pdfParser = $this->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType'])
                ->getMock();
        $response->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('text/html'));

        $domNode = $this->getMockBuilder('\DOMNode')
                ->setMethods(['text', 'each'])
                ->getMock();
        $domNode->expects($this->once())
                ->method('text')
                ->will($this->returnValue('dummy text from web page'));

        $domCrawler = $this->getMockBuilder('Symfony\Component\DomCrawler\Crawler')
                ->disableOriginalConstructor()
                ->setMethods(['filterXpath'])
                ->getMock();
        $domCrawler->expects($this->at(0))
                ->method('filterXpath')
                ->with($this->equalTo('//title'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(1))
                ->method('filterXpath')
                ->with($this->equalTo('//*[not(self::script)]/text()'))
                ->will($this->returnValue($domNode));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->once())
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $resource = $this->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse', 'getUri', 'getCrawler'])
                ->getMock();
        $resource->expects($this->once())
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getCrawler')
                ->will($this->returnValue($domCrawler));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $pdfParser);

        $this->assertNull($persistenceHandler->persist($resource));
    }

    public function testSpiderId()
    {
        $queue = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['__destruct', 'publish'])
                ->getMock();

        $pdfParser = $this->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $pdfParser);

        $this->assertNull($persistenceHandler->setSpiderId(123));
    }

}
