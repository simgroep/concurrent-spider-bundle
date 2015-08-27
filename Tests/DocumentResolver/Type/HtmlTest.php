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
        $uri->expects($this->once())
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
                ->expects($this->exactly(18))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html('.test');
        $data = $type->getData($resource);

        $this->assertEquals(0, count($crawler->filter('.test')));
        $this->assertEquals(25, count($data['document']));
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
        $uri->expects($this->once())
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');
        $crawler->addContent('<html><body><p>This is the text value.</p></body></html>');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(18))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html(null);
        $data = $type->getData($resource);

        $expectedData = [
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
        $uri->expects($this->once())
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');
        $crawler->addContent('<html><meta name="DCTERMS.available" content="2015-06-18T23:49:41Z"><meta name="DCTERMS.modified" content="2015-06-18T23:49:41Z"><body><p>This is the text value.</p></body></html>');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(18))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html(null);
        $data = $type->getData($resource);

        $expectedData = [
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
        ];

        $this->assertEquals($expectedData, $data);
    }

    /**
     * @test
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     * @expectedExceptionMessage Webpage didn't contain enough content (minimal chars is 3)
     */
    public function throwExceptionOnLessThenMinimalContentLength()
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
        $uri->expects($this->once())
                ->method('toString')
                ->will($this->returnValue('http://blabdummy.de/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(13))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->once())
                ->method('getUri')
                ->will($this->returnValue($uri));

        $type = new Html(null);
        $type->getData($resource);
    }

}