<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\TypeAbstract;
use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentDataExtractor;
use Smalot\PdfParser\Parser;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;

/**
 * Pdf Resolver Document Type
 */
class Pdf extends TypeAbstract implements DocumentTypeInterface
{
    /**
     * @var \Smalot\PdfParser\Parser
     */
    private $pdfParser;

    /**
     * @param \Smalot\PdfParser\Parser $pdfParser
     * @param \VDB\Spider\Resource $resource
     */
    public function __construct(Parser $pdfParser)
    {
        $this->pdfParser = $pdfParser;
    }

    /**
     * Returns the filename of the resource. If not found it returns null.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    protected function getFileNameFromResource(Resource $resource)
    {
        $regex = '/.*filename=[\'\"]([^\'\"]+)/';

        if (preg_match($regex, $resource->getResponse()->getContentDisposition(), $matches)) {
            return $matches['1'];
        }
    }

    /**
     * Extracts content from a PDF File and returns document data.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return array
     *
     * @throws \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function getData(Resource $resource)
    {
        $content = $this->extractContentFromResource($resource);

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
                sprintf("PDF didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $dataExtractor = new DocumentDataExtractor($resource);

        $url = $dataExtractor->getUrl();
        $title = $this->getFileNameFromResource($resource);

        if (null === $title) {
            $title = $this->getTitleByUrl($url) ? : '';
        }

        $data = [
            'document' => [
                'id' => $dataExtractor->getId(),
                'url' => $url,
                'content' => $content,
                'strippedContent' => $content,
                'title' => $title,
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'contentLength' => strlen($content),
                'lastModified' => $dataExtractor->getLastModified(),
                'date' => date('Y-m-d\TH:i:s\Z'),
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'SIM_archief' => $dataExtractor->getSimArchief(),
                'SIM.simfaq' => $dataExtractor->getSimfaq(),
                'type' => ['appliation/pdf']
            ],
        ];

        return $data;
    }

    /**
     *
     * Extract content from resource
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    public function extractContentFromResource(Resource $resource)
    {
        $pdf = $this->pdfParser->parseContent($resource->getResponse()->getBody(true));

        return $this->stripBinaryContent($pdf->getText());
    }

}
