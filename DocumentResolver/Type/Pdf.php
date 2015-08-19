<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use VDB\Spider\Resource;
use Smalot\PdfParser\Parser;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use InvalidArgumentException;

/**
 * Pdf type of document
 *
 * @author lkalinka
 */
class Pdf implements DocumentTypeInterface
{
    const MINIMAL_CONTENT_LENGTH = 3;

    /**
     * @var Smalot\PdfParser\Parser
     */
    private $pdfParser;

    /**
     * @param Smalot\PdfParser\Parser $pdfParser
     * @param VDB\Spider\Resource $resource
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
     */
    public function getData(Resource $resource)
    {
        $pdf = $this->pdfParser->parseContent($resource->getResponse()->getBody(true));
        $url = $resource->getUri()->toString();
        $title = $this->getTitleByUrl($url) ?: '';
        $content = $this->stripBinaryContent($pdf->getText());

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
                sprintf("PDF didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $lastModifiedDateTime = new \DateTime($resource->getResponse()->getLastModified());
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
     * Assumes that the path of the URL contains the title of the document and extracts it.
     *
     * @param string $url
     *
     * @return string
     */
    protected function getTitleByUrl($url)
    {
        $title = null;

        if (false !== stripos($url, '.pdf')) {
            $urlParts = parse_url($url);
            $title = basename($urlParts['path']);
        }

        return $title;
    }

    /**
     * Strip away binary content since it doesn't make sense to index it.
     *
     * @param string $content
     *
     * @return string
     */
    protected function stripBinaryContent($content)
    {
        return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', '', $content);
    }
}
