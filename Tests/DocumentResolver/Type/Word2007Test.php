<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\DocumentResolver\TypeWord2007;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007;
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

class Word2007Test extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @expectedException \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     * @expectedExceptionMessage Word2007 didn't contain enough content (minimal chars is 3)
     */
    public function throwExceptionOnLessThenMinimalContentLength()
    {
        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->getMock();

        $type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007')
            ->setMethods(['extractContentFromResource'])
            ->getMock();
        $type->expects($this->once())
            ->method('extractContentFromResource')
            ->with($resource)
            ->will($this->returnValue(''));

        $type->getData($resource);
    }

    /**
     * @test
     */
    public function extractContentFromResourceOnUnrecognizedResourceContentReturnsEmptyString()
    {
        $reader = $this
            ->getMockBuilder('PhpOffice\PhpWord\Reader\Word2007')
            ->disableOriginalConstructor()
            ->setMethods(['load'])
            ->getMock();
        $reader->expects($this->once())
            ->method('load')
            ->will($this->throwException(new \Exception));

        $type = $this->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007')
            ->setMethods(['getReader'])
            ->getMock();
        $type->expects($this->once())
            ->method('getReader')
            ->will($this->returnValue($reader));

        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getBody'])
            ->getMock();
        $response->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue(file_get_contents(__DIR__ . '/../../Mock/Documents/sample.docx')));

        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse'])
            ->getMock();
        $resource->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $data = $type->extractContentFromResource($resource);

        $this->assertEquals('', $data);
    }

    /**
     * @test
     */
    public function retrieveValidDataFromResource()
    {
        $response = $this->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getBody', 'getLastModified'])
            ->getMock();
        $response->expects($this->once())
            ->method('getLastModified')
            ->will($this->returnValue('2015-06-18T23:49:41Z'));
        $response->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue(file_get_contents(__DIR__ . '/../../Mock/Documents/sample.docx')));

        $uri = $this->getMockBuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();
        $uri->expects($this->exactly(2))
            ->method('toString')
            ->will($this->returnValue('http://blabdummy.de/dummydir/sample.docx'));

        $crawler = new Crawler('', 'http://blabdummy.de/dummydir/sample.docx');

        $resource = $this
            ->getMockBuilder('VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse', 'getUri', 'getBody'])
            ->getMock();
        $resource->expects($this->exactly(2))
            ->method('getResponse')
            ->will($this->returnValue($response));
        $resource->expects($this->exactly(2))
            ->method('getUri')
            ->will($this->returnValue($uri));

        $type = new Word2007();
        $data = $type->getData($resource);

        $this->assertEquals(10, count($data));
        $expectedKeys = ['id', 'url', 'content', 'title', 'tstamp', 'contentLength', 'lastModified', 'date', 'publishedDate', 'updatedDate'];
        foreach ($expectedKeys as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $data);
        }

        $this->assertEquals('sample.docx', $data['title']);
        $this->assertNotEmpty($data, $data['content']);
    }

}
