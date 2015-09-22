<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentDataExtractor;
use Symfony\Component\DomCrawler\Crawler;

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

class DocumentDataExtractorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function ifDocuemntDataExtractorReturnAllCorrectDefaultValues()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
                ->disableOriginalConstructor()
                ->setMethods(['getContentType', 'getLastModified'])
                ->getMock();
        $response->expects($this->once())
                ->method('getContentType');
        $response->expects($this->once())
                ->method('getLastModified')
                ->will($this->returnValue('2015-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->exactly(3))
                ->method('toString')
                ->will($this->returnValue('http://dummy.xxx/dummydir/somewebpagedummyfile.html'));

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
        $resource->expects($this->exactly(3))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $documentExtractor = new DocumentDataExtractor($resource);

        $this->assertSame('3575e8f273b468d70a0e54a62e5c10b0d80f28a5', $documentExtractor->getId());
        $this->assertSame('', $documentExtractor->getTitle());
        $this->assertSame('http://dummy.xxx/dummydir/somewebpagedummyfile.html', $documentExtractor->getUrl());
        $this->assertSame([], $documentExtractor->getType());
        $this->assertSame('2015-06-18T23:49:41Z', $documentExtractor->getLastModified());
        $this->assertSame('', $documentExtractor->getAuthor());
        $this->assertSame('', $documentExtractor->getDescription());
        $this->assertSame('', $documentExtractor->getKeywords());
        $this->assertSame('no', $documentExtractor->getSimArchief());
        $this->assertSame(['no'], $documentExtractor->getSimfaq());
        $this->assertSame('2015-06-18T23:49:41Z', $documentExtractor->getDctermsModified());
        $this->assertSame('http://dummy.xxx/dummydir/somewebpagedummyfile.html', $documentExtractor->getDctermsIdentifier());
        $this->assertSame('', $documentExtractor->getDctermsTitle());
        $this->assertSame('2015-06-18T23:49:41Z', $documentExtractor->getDctermsAvailable());
        $this->assertSame('nl-NL', $documentExtractor->getDctermsLanguage());
        $this->assertSame('webpagina', $documentExtractor->getDctermsType());
        $this->assertSame('', $documentExtractor->getSimItemTrefwoorden());
        $this->assertSame('', $documentExtractor->getSimSimloketSynoniemen());
        $this->assertSame('', $documentExtractor->getDctermsSpatial());
        $this->assertSame('', $documentExtractor->getDctermsAudience());
        $this->assertSame('', $documentExtractor->getDctermsSubject());
    }

    /**
     * @test
     */
    public function ifDocuemntDataExtractorReturnAllCorrectValues()
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
                ->will($this->returnValue('2014-06-18T23:49:41Z'));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
                ->disableOriginalConstructor()
                ->setMethods(['toString'])
                ->getMock();
        $uri->expects($this->exactly(2))
                ->method('toString')
                ->will($this->returnValue('http://dummy.xxx/dummydir/somewebpagedummyfile.html'));

        $crawler = new Crawler('', 'https://github.com');
        $crawler->addContent('<html><head><title>Site dummy 1</title><meta name="description" content="Dummy description" /><meta name="keywords" content="keyword1,keyword2" /><meta name="author" content="Dummy Author" /><meta name="SIM_archief" content="yes" /><meta name="SIM.simfaq" content="yes" /><meta name="SIM.item_trefwoorden" content="trefwoorden" /><meta name="DCTERMS.title" content="Title 2" /><meta name="DCTERMS.language" content="pl-PL" /><meta name="DCTERMS.type" content="dummytype" /><meta name="SIM.simloket_synoniemen" content="synoniemen" /><meta name="SIM.spatial" content="spatial" /><meta name="DCTERMS.identifier" content="identifierurl" /><meta name="SIM.audience" content="audience" /><meta name="SIM.subject" content="subject" /><meta name="DCTERMS.available" content="2015-06-18T23:49:41Z" /><meta name="DCTERMS.modified" content="2015-06-18T23:49:41Z" /></head><body><p>This is the text value.</p></body></html>');

        $resource = $this
                ->getMockBuilder('VDB\Spider\Resource')
                ->disableOriginalConstructor()
                ->setMethods(['getCrawler', 'getResponse', 'getUri'])
                ->getMock();
        $resource
                ->expects($this->exactly(17))
                ->method('getCrawler')
                ->will($this->returnValue($crawler));
        $resource->expects($this->exactly(2))
                ->method('getResponse')
                ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
                ->method('getUri')
                ->will($this->returnValue($uri));

        $documentExtractor = new DocumentDataExtractor($resource);

        $this->assertSame('3575e8f273b468d70a0e54a62e5c10b0d80f28a5', $documentExtractor->getId());
        $this->assertSame('Site dummy 1', $documentExtractor->getTitle());
        $this->assertSame('http://dummy.xxx/dummydir/somewebpagedummyfile.html', $documentExtractor->getUrl());
        $this->assertSame(['text/html', 'text', 'html'], $documentExtractor->getType());
        $this->assertSame('2014-06-18T23:49:41Z', $documentExtractor->getLastModified());
        $this->assertSame('Dummy Author', $documentExtractor->getAuthor());
        $this->assertSame('Dummy description', $documentExtractor->getDescription());
        $this->assertSame('keyword1,keyword2', $documentExtractor->getKeywords());
        $this->assertSame('yes', $documentExtractor->getSimArchief());
        $this->assertSame(['yes'], $documentExtractor->getSimfaq());
        $this->assertSame('2015-06-18T23:49:41Z', $documentExtractor->getDctermsModified());
        $this->assertSame('identifierurl', $documentExtractor->getDctermsIdentifier());
        $this->assertSame('Title 2', $documentExtractor->getDctermsTitle());
        $this->assertSame('2015-06-18T23:49:41Z', $documentExtractor->getDctermsAvailable());
        $this->assertSame('pl-PL', $documentExtractor->getDctermsLanguage());
        $this->assertSame('dummytype', $documentExtractor->getDctermsType());
        $this->assertSame('trefwoorden', $documentExtractor->getSimItemTrefwoorden());
        $this->assertSame('synoniemen', $documentExtractor->getSimSimloketSynoniemen());
        $this->assertSame('spatial', $documentExtractor->getDctermsSpatial());
        $this->assertSame('audience', $documentExtractor->getDctermsAudience());
        $this->assertSame('subject', $documentExtractor->getDctermsSubject());
    }
}
