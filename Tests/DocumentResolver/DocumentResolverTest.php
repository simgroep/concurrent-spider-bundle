<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\DocumentResolver;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver;

class DocumentResolverTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     *
     * @dataProvider documentsProvider
     */
    public function ifDataIsReturnedFromDocuments($mimeType, $resolverType)
    {
        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getContentType'])
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getContentType')
            ->will($this->returnValue($mimeType));

        $uri = $this
            ->getMockBuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse', 'getUri'])
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $resource
            ->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue($uri));

        $data = ['dummyKey' => 'dummyValue'];

        $htmlType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html');
        $pdfType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf');
        $msdocType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc');
        $word2007Type = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007');
        $rtfType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf');
        $odtType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt');

        switch($resolverType) {
            case 'html':
                $htmlType
                    ->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
                break;
            case 'pdf':
                $pdfType
                    ->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
                break;
            case 'msdoc':
                $msdocType
                    ->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
                break;
            case 'rtf':
                $rtfType
                    ->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
                break;
            case 'odt':
                $odtType
                    ->expects($this->once())
                    ->method('getData')
                    ->with($resource)
                    ->will($this->returnValue($data));
                break;
        }

        $documentResolver = new DocumentResolver($htmlType, $pdfType, $msdocType, $word2007Type, $rtfType, $odtType);
        $document = $documentResolver->getDocumentByResource($resource);

        $this->assertInstanceOf('\Simgroep\ConcurrentSpiderBundle\PersistableDocument', $document);
        $this->assertEquals($data, $document->toArray());
    }

    /**
     * Returns a mocked document resolver.
     *
     * @param string $type
     *
     * @return \Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentTypeInterface
     */
    private function getDocumentResolverMock($type)
    {
        $documentResolver = $this
            ->getMockBuilder($type)
            ->disableOriginalConstructor()
            ->setMethods(['getData'])
            ->getMock();

        return $documentResolver;
    }

    /**
     * @test
     */
    public function ifDataIsReturnedFromWord2007Document()
    {
        $response = $this
            ->getMockBuilder('Guzzle\Http\Message\Response')
            ->disableOriginalConstructor()
            ->setMethods(['getContentType'])
            ->getMock();

        $response
            ->expects($this->once())
            ->method('getContentType')
            ->will($this->returnValue('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));

        $uri = $this
            ->getMockBuilder('VDB\Uri\Uri')
            ->disableOriginalConstructor()
            ->setMethods(['toString'])
            ->getMock();

        $uri
            ->expects($this->any())
            ->method('toString')
            ->will($this->returnValue('dummyUri/documment.docx'));

        $resource = $this
            ->getMockBuilder('\VDB\Spider\Resource')
            ->disableOriginalConstructor()
            ->setMethods(['getResponse', 'getUri'])
            ->getMock();

        $resource
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));

        $resource
            ->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue($uri));

        $data = ['dummyKey' => 'dummyValue'];

        $htmlType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html');
        $pdfType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf');
        $msdocType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc');
        $word2007Type = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007');
        $word2007Type
            ->expects($this->once())
            ->method('getData')
            ->will($this->returnValue($data));

        $rtfType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf');
        $odtType = $this->getDocumentResolverMock('Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt');

        $documentResolver = new DocumentResolver($htmlType, $pdfType, $msdocType, $word2007Type, $rtfType, $odtType);
        $document = $documentResolver->getDocumentByResource($resource);

        $this->assertInstanceOf('\Simgroep\ConcurrentSpiderBundle\PersistableDocument', $document);
        $this->assertEquals($data, $document->toArray());
    }

    /**
     * Returns document mimetype with corresponding
     * document resolverType
     *
     * Except for word2007 that need another logic
     *
     * @return array
     */
    public function documentsProvider()
    {
        return [
            ['text/html', 'html'],
            ['application/pdf', 'pdf'],
            ['application/octet-stream', 'pdf'],
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'msdoc'],
            ['application/msword', 'msdoc'],
            ['application/rtf', 'rtf'],
            ['application/vnd.oasis.opendocument.text', 'odt'],
            ['unknown', 'html']
        ];
    }
}

