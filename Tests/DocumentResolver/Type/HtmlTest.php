<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Mock version of json_encode
 *
 * Used for testing an error situation.
 *
 * @param mixed $value
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
 * @return string
 */
function date()
{
    return '2015-06-18T23:49:41Z';
}

class HtmlTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function isBlacklistFilterApplied()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType', 'getLastModified'])
                ->getMock();
        $response->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('text/html'));
        $response->expects($this->once())
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->exactly(2))
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');
        $crawler->addContent('<html><body><div class="test">test</div><p>Dummy Text</p></body></html>');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(5))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html('.test');
        $data = $type->getData($resource);

        $this->assertEquals(0, count($crawler->filter('.test')));
        $this->assertEquals(16, count($data));
    }

    /**
     * @test
     */
    public function persistRetrieveValidDataFromWebPageWithAllDefaultValuesInFields()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType', 'getLastModified'])
                ->getMock();
        $response->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('text/html'));
        $response->expects($this->once())
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->exactly(2))
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');
        $crawler->addContent('<html><head><meta name="DCTERMS.available" content="2015-06-18T23:49:41Z" /><meta name="DCTERMS.modified" content="2015-06-18T23:49:41Z" /></head><body><p>This is the text value.</p></body></html>');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(5))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html(null);
        $data = $type->getData($resource);

        $expectedData = [
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
            'description' => '',
            'keywords' => '',
        ];

        $this->assertEquals($expectedData, $data);
    }

    /**
     * @test
     */
    public function persistRetrieveValidDataFromWebPageWithDateAvailableAndModifiedValuesSetInFields()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType', 'getLastModified'])
                ->getMock();
        $response->expects($this->once())
                ->method('getContentType')
                ->will($this->returnValue('text/html'));
        $response->expects($this->once())
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->exactly(2))
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');
        $crawler->addContent('<html><head><title>Site dummy 1</title><meta name="description" content="Dummy description" /><meta name="keywords" content="keyword1,keyword2" /><meta name="author" content="Dummy Author" /><meta name="SIM_archief" content="yes" /><meta name="SIM.simfaq" content="yes" /><meta name="SIM.item_trefwoorden" content="trefwoorden" /><meta name="DCTERMS.title" content="Title 2" /><meta name="DCTERMS.language" content="pl-PL" /><meta name="DCTERMS.type" content="dummytype" /><meta name="SIM.simloket_synoniemen" content="synoniemen" /><meta name="SIM.spatial" content="spatial" /><meta name="DCTERMS.identifier" content="identifierurl" /><meta name="SIM.audience" content="audience" /><meta name="SIM.subject" content="subject" /><meta name="DCTERMS.available" content="2015-06-18T23:49:41Z" /><meta name="DCTERMS.modified" content="2015-06-18T23:49:41Z" /></head><body><p>This is the text value.</p></body></html>');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(5))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html(null);
        $data = $type->getData($resource);

        $expectedData = [
            'id' => sha1('http://blabdummy.de/dummydir/somewebpagedummyfile.html'),
            'url' => 'http://blabdummy.de/dummydir/somewebpagedummyfile.html',
            'content' => 'This is the text value.',
            'title' => 'Site dummy 1',
            'tstamp' => date('Y-m-d\TH:i:s\Z'),
            'type' => ["text/html","text","html"],
            'contentLength' => 23,
            'lastModified'=> date('Y-m-d\TH:i:s\Z'),
            'date' => date('Y-m-d\TH:i:s\Z'),
            'lang' => 'nl-NL',
            'author' => 'Dummy Author',
            'publishedDate' => date('Y-m-d\TH:i:s\Z'),
            'updatedDate' => date('Y-m-d\TH:i:s\Z'),
            'strippedContent' => 'This is the text value.',
            'description' => 'Dummy description',
            'keywords'=> 'keyword1,keyword2',
        ];

        $this->assertEquals($expectedData, $data);
    }

}
