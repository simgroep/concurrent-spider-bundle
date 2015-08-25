<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\TypeAbstract;
use VDB\Spider\Resource;
use Smalot\PdfParser\Parser;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use InvalidArgumentException;
use DateTime;

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

        $url = $resource->getUri()->toString();
        $title = $this->getTitleByUrl($url) ? : '';

        $content = $this->extractContentFromResource($resource);

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
                sprintf("PDF didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $lastModifiedDateTime = new DateTime($resource->getResponse()->getLastModified());
        $lastModified = $lastModifiedDateTime->format('Y-m-d\TH:i:s\Z');

        try {
            $sIMArchive = $resource->getCrawler()->filterXPath('//head/meta[@name="SIM_archief"]/@content')->text();
        } catch (InvalidArgumentException $exc) {
            $sIMArchive = 'no';
        }

        try {
            $sIM_simfaq = $resource->getCrawler()->filterXPath('//head/meta[@name="SIM.simfaq"]/@content')->text();
        } catch (InvalidArgumentException $exc) {
            $sIM_simfaq = ['no'];
        }

        $data = [
            'document' => [
                'id' => sha1($url),
                'url' => $url,
                'content' => $content,
                'title' => $title,
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'contentLength' => strlen($content),
                'lastModified' => $lastModified,
                'date' => date('Y-m-d\TH:i:s\Z'),
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'SIM_archief' => $sIMArchive,
                'SIM.simfaq' => $sIM_simfaq,
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
