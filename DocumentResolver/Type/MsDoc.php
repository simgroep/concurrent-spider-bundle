<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\TypeAbstract;
use VDB\Spider\Resource;
use PhpOffice\PhpWord\Reader\MsDoc as MsDocReader;
use PhpOffice\PhpWord\Writer\HTML as HtmlWriter;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use InvalidArgumentException;

/**
 * MsDoc Resolver Document Type
 */
class MsDoc extends TypeAbstract implements DocumentTypeInterface
{

    /**
     * Extracts content from a msdoc and returns document data.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return array
     *
     * @throws \InvalidContentException
     */
    public function getData(Resource $resource)
    {
        $url = $resource->getUri()->toString();
        $title = $this->getTitleByUrl($url) ? : '';

        $content = $this->extractContentFromResource($resource);

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
            sprintf("MsDoc didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
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
     * Extract content from resource
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    public function extractContentFromResource(Resource $resource)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'doc');

        file_put_contents($tempFile, $resource->getResponse()->getBody());

        $reader = $this->getReader();

        //remove notice from library
        $errorReportingLevel = error_reporting();
        error_reporting($errorReportingLevel ^ E_NOTICE);

        $phpword = $reader->load($tempFile);

        //back error reporting to previous state
        error_reporting($errorReportingLevel);

        unlink($tempFile);

        $writer = $this->getWriter($phpword);

//       to remove strange characters use preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/", "", $contentUnescaped);
        return strip_tags($this->stripBinaryContent($writer->getContent()));
    }

    /**
     * Return Reader Object
     *
     * @return \PhpOffice\PhpWord\Reader\MsDoc
     */
    protected function getReader()
    {
        return new MsDocReader();
    }

    /**
     * Return Writer Object
     *
     * @param \PhpOffice\PhpWord\Reader\MsDoc $reader
     *
     * @return \PhpOffice\PhpWord\Writer\HTML
     */
    protected function getWriter($reader)
    {
        return new HtmlWriter($reader);
    }

}
