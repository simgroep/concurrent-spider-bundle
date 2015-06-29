<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\RabbitMqPersistenceHandler;

/**
 * Mock version of json_encode
 *
 * Used for testing an error situation.
 *
 * @param $value
 * @return bool|string
 */
function json_encode($value)
{
    if ($value === 'return-false') {
        return false;
    }
    return \json_encode($value);
}

/**
 * Date function mock for returning the same date string
 * Fixing problem with generating not the same datetime each call
 *
 * @retutn string
 */
function date()
{
    return '2015-06-18T23:49:41Z';
}

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
                ->will($this->returnValue('return-false')); # tell the mock-json_encode() to return false

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
        $uri->expects($this->exactly(2))
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/dummyfile.pdf'));

        $resource = $this->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getResponse', 'getUri'])
                ->getMock();
        $resource->expects($this->exactly(5))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $pdfParser);
        $persistenceHandler->setCoreName('dummyCoreName');
        
        $this->assertNull($persistenceHandler->persist($resource));
    }

    public function testPersistRetrieveValidDataFromWebPageWithAllDefaultValuesInFields()
    {
        $pdfParser = $this->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType', 'getLastModified'])
                ->getMock();
        $response->expects($this->exactly(2))
                ->method('getContentType')
                ->will($this->returnValue('text/html'));
        $response->expects($this->exactly(1))
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $domNode = $this->getMockBuilder('\DOMNode')
                ->setMethods(['text', 'each'])
                ->getMock();
        $domNode->expects($this->at(0))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(1))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(2))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(3))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(4))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(5))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(6))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(7))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(8))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(9))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(10))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(11))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(12))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(13))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(14))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(15))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));
        $domNode->expects($this->at(16))
                ->method('text')
                ->will($this->throwException(new \InvalidArgumentException()));

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
        $resource->expects($this->exactly(3))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(18))
                ->method('getCrawler')
                ->will($this->returnValue($this->getDomCrawlerMock($domNode)));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $data = [
            'document' => [
                'id' => sha1('http://blabdummy.de/dummydir/somewebpagedummyfile.html'),
                'url' => 'http://blabdummy.de/dummydir/somewebpagedummyfile.html',
                'content' => 'This is the text value.',
                'title' => '',
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'type' => ["text/html","text","html"],
                'contentLength' => 23,
                'lastModified'=> date('Y-m-d\TH:i:s\Z'),
                'date' => date('Y-m-d\TH:i:s\Z'),
                'lang' => 'nl-NL',
                'author' => '',
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'updatedDate' => date('Y-m-d\TH:i:s\Z'),
                'strippedContent' => 'This is the text value.',
                'collection' => ["Alles"],
                'description' => '',
                'keywords'=> '',
                'SIM_archief' => 'no',
                'SIM.simfaq' => ['no'],
                'DCTERMS.modified' => date('Y-m-d\TH:i:s\Z'),
                'DCTERMS.identifier'=> 'http://blabdummy.de/dummydir/somewebpagedummyfile.html',
                'DCTERMS.title' => '',
                'DCTERMS.available' => date('Y-m-d\TH:i:s\Z'),
                'DCTERMS.language'=> 'nl-NL',
                'DCTERMS.type'=> 'webpagina'
            ],
            'core_name' => 'dummyCoreName',
        ];
        $message = new AMQPMessage(json_encode($data), ['delivery_mode' => 1]);
        $queue = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['__destruct', 'publish'])
                ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish')
            ->with($message);

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $pdfParser);
        $persistenceHandler->setCoreName('dummyCoreName');

        $this->assertNull($persistenceHandler->persist($resource));
    }

    public function testPersistRetrieveValidDataFromWebPageWithAllValuesSetInFields()
    {
        $pdfParser = $this->getMockBuilder('Smalot\PdfParser\Parser')
                ->disableOriginalConstructor()
                ->getMock();

        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType', 'getLastModified'])
                ->getMock();
        $response->expects($this->exactly(2))
                ->method('getContentType')
                ->will($this->returnValue('text/html'));
        $response->expects($this->exactly(1))
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $domNode = $this->getMockBuilder('\DOMNode')
                ->setMethods(['text', 'each'])
                ->getMock();
        $domNode->expects($this->at(0))
                ->method('text')
                ->will($this->returnValue('This is the title value.'));
        $domNode->expects($this->at(1))
                ->method('text')
                ->will($this->returnValue('author'));
        $domNode->expects($this->at(2))
                ->method('text')
                ->will($this->returnValue('description'));
        $domNode->expects($this->at(3))
                ->method('text')
                ->will($this->returnValue('keywords'));
        $domNode->expects($this->at(4))
                ->method('text')
                ->will($this->returnValue('yes'));
        $domNode->expects($this->at(5))
                ->method('text')
                ->will($this->returnValue('yes'));
        $domNode->expects($this->at(6))
                ->method('text')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));
        $domNode->expects($this->at(7))
                ->method('text')
                ->will($this->returnValue('http://dummypage.nl/document.html'));
        $domNode->expects($this->at(8))
                ->method('text')
                ->will($this->returnValue('dc title 1'));
        $domNode->expects($this->at(9))
                ->method('text')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));
        $domNode->expects($this->at(10))
                ->method('text')
                ->will($this->returnValue('pl-PL'));
        $domNode->expects($this->at(11))
                ->method('text')
                ->will($this->returnValue('webdocument'));
        $domNode->expects($this->at(12))
                ->method('text')
                ->will($this->returnValue('SIM.item_trefwoorden'));
        $domNode->expects($this->at(13))
                ->method('text')
                ->will($this->returnValue('SIM.simloket_synoniemen'));
        $domNode->expects($this->at(14))
                ->method('text')
                ->will($this->returnValue('DCTERMS.spatial'));
        $domNode->expects($this->at(15))
                ->method('text')
                ->will($this->returnValue('DCTERMS.audience'));
        $domNode->expects($this->at(16))
                ->method('text')
                ->will($this->returnValue('DCTERMS.subject'));

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
        $resource->expects($this->exactly(3))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(18))
                ->method('getCrawler')
                ->will($this->returnValue($this->getDomCrawlerMock($domNode)));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $data = [
            'document' => [
                'id' => sha1('http://blabdummy.de/dummydir/somewebpagedummyfile.html'),
                'url' => 'http://blabdummy.de/dummydir/somewebpagedummyfile.html',
                'content' => 'This is the text value.',
                'title' => 'This is the title value.',
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'type' => ["text/html","text","html"],
                'contentLength' => 23,
                'lastModified'=> date('Y-m-d\TH:i:s\Z'),
                'date' => date('Y-m-d\TH:i:s\Z'),
                'lang' => 'nl-NL',
                'author' => 'author',
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'updatedDate' => date('Y-m-d\TH:i:s\Z'),
                'strippedContent' => 'This is the text value.',
                'collection' => ["Alles"],
                'description' => 'description',
                'keywords'=> 'keywords',
                'SIM_archief' => 'yes',
                'SIM.simfaq' => ['yes'],
                'DCTERMS.modified' => date('Y-m-d\TH:i:s\Z'),
                'DCTERMS.identifier'=> 'http://dummypage.nl/document.html',
                'DCTERMS.title' => 'dc title 1',
                'DCTERMS.available' => date('Y-m-d\TH:i:s\Z'),
                'DCTERMS.language'=> 'pl-PL',
                'DCTERMS.type'=> 'webdocument',
                'SIM.item_trefwoorden'=> 'SIM.item_trefwoorden',
                'SIM.simloket_synoniemen'=> 'SIM.simloket_synoniemen',
                'DCTERMS.spatial'=> 'DCTERMS.spatial',
                'DCTERMS.audience'=> 'DCTERMS.audience',
                'DCTERMS.subject'=> 'DCTERMS.subject'
            ],
            'core_name' => 'dummyCoreName2',
        ];
        $message = new AMQPMessage(json_encode($data), ['delivery_mode' => 1]);

        $queue = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\Queue')
                ->disableOriginalConstructor()
                ->setMethods(['__destruct', 'publish'])
                ->getMock();

        $queue
            ->expects($this->once())
            ->method('publish')
            ->with($message);

        $persistenceHandler = new RabbitMqPersistenceHandler($queue, $pdfParser);
        $persistenceHandler->setCoreName('dummyCoreName2');

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

    /**
     *
     * @param DOMNode $domNode
     * @return Symfony\Component\DomCrawler\Crawler
     */
    protected function getDomCrawlerMock($domNode)
    {
        $domCrawlerWithNode = $this->getMockBuilder('Symfony\Component\DomCrawler\Crawler')
                ->setMethods(null)
                ->getMock();
        $domCrawlerWithNode->add(new \DOMElement('test', 'This is the text value.'));

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
                ->with($this->equalTo('//head/meta[@name="author"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(2))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="description"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(3))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="keywords"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(4))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="SIM_archief"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(5))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="SIM.simfaq"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(6))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.modified"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(7))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.identifier"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(8))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.title"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(9))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.available"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(10))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.language"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(11))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.type"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(12))
                ->method('filterXpath')
                ->with($this->equalTo('//*[not(self::script)]/text()'))
                ->will($this->returnValue($domCrawlerWithNode));
        $domCrawler->expects($this->at(13))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="SIM.item_trefwoorden"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(14))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="SIM.simloket_synoniemen"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(15))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.spatial"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(16))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.audience"]/@content'))
                ->will($this->returnValue($domNode));
        $domCrawler->expects($this->at(17))
                ->method('filterXpath')
                ->with($this->equalTo('//head/meta[@name="DCTERMS.subject"]/@content'))
                ->will($this->returnValue($domNode));

        return $domCrawler;
    }
}
