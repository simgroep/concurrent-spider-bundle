<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use VDB\Spider\Resource;
use PhpOffice\PhpWord\Reader\MsDoc;
use PhpOffice\PhpWord\Writer\HTML as HtmlWriter;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use InvalidArgumentException;

/**
 * Description of MsDoc
 *
 * @author lkalinka
 */
class MsDocx implements DocumentTypeInterface
{
    const MINIMAL_CONTENT_LENGTH = 3;

    private $reader;

    public function __construct(MsDoc $reader)
    {
        $this->reader = $reader;
    }

    public function getData(Resource $resource)
    {
        $url = $resource->getUri()->toString();
        $title = $this->getTitleByUrl($url) ? : '';

        $tempFile = tempnam(sys_get_temp_dir(), 'doc');
        if (false === $tempFile) {
            throw new \Exception("Cannot create tempFile!)");
        }

        file_put_contents($tempFile, $resource->getResponse()->getBody());

        if (false === $this->reader->canRead($tempFile)) {
            throw new \Exception("TempFile cannot be read by phpword!)");
        }

        //remove notice from library
        $errorReportingLevel = error_reporting();
        error_reporting($errorReportingLevel ^ E_NOTICE);

        $phpword = $this->reader->load($tempFile);

        unlink($tempFile);

        //back error reporting to previous state
        error_reporting($errorReportingLevel);

        $writer = new HtmlWriter($phpword);

        $content = strip_tags($this->stripBinaryContent($writer->getContent()));


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

        if (false !== stripos($url, '.doc') || false !== stripos($url, '.docx')) {
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
