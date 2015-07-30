<?php

namespace Simgroep\ConcurrentSpiderBundle\PersistenceHandler;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler;
use Smalot\PdfParser\Parser;
use Symfony\Component\DomCrawler\Crawler;
use VDB\Spider\Resource;
use InvalidArgumentException;

/**
 * The content of crawled webpages is saved to a seperate queue that is designed for indexing documents.
 */
class RabbitMqPersistenceHandler implements PersistenceHandler
{
    const MINIMAL_CONTENT_LENGTH = 3;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Smalot\PdfParser\Parser
     */
    private $pdfParser;

    /**
     * @var string
     */
    private $cssBlacklist;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     * @param \Smalot\PdfParser\Parser               $pdfParser
     * @param string                                 $cssBlacklist
     */
    public function __construct(Queue $queue, Parser $pdfParser, $cssBlacklist)
    {
        $this->queue = $queue;
        $this->pdfParser = $pdfParser;
        $this->cssBlacklist = $cssBlacklist;
    }

    /**
     * Grabs the content from the crawled page and publishes a job on the queue.
     *
     * @param \VDB\Spider\Resource                      $resource
     * @param \Simgroep\ConcurrentSpiderBundle\CrawlJob $crawlJob
     *
     * @throws \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function persist(Resource $resource, CrawlJob $crawlJob)
    {
        switch ($resource->getResponse()->getContentType()) {
            case 'application/pdf':
            case 'application/octet-stream' :
                $data = $this->getDataFromPdfFile($resource);

                if (!json_encode($data)) {
                    throw new InvalidContentException("Couldn't create a JSON string while extracting PDF.");
                }

                break;

            case 'text/html':
            default:
                try {
                    $data = $this->getDataFromWebPage($resource);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidContentException("Couldn't crawl through DOM to obtain the web page contents.");
                }

                break;
        }

        if (isset($data)) {
            $message = new AMQPMessage(
                json_encode(array_merge($data, ['metadata' => $crawlJob->getMetadata()])),
                ['delivery_mode' => 1]
            );

            $this->queue->publish($message);
        }
    }

    /**
     * Strip away binary content since it doesn't make sense to index it.
     *
     * @param string $content
     *
     * @return string
     */
    private function stripBinaryContent($content)
    {
        return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', '', $content);
    }

    /**
     * Extracts content from a PDF File and returns document data.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return array
     */
    protected function getDataFromPdfFile(Resource $resource)
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
        } catch (\InvalidArgumentException $exc) {
            $sIMArchive = 'no';
        }

        try {
            $sIM_simfaq = $resource->getCrawler()->filterXPath('//head/meta[@name="SIM.simfaq"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
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
    private function getTitleByUrl($url)
    {
        $title = null;

        if (false !== stripos($url, '.pdf')) {
            $urlParts = parse_url($url);
            $title = basename($urlParts['path']);
        }

        return $title;
    }

    /**
     * Extracts content from a webpage and returns document data.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return array
     */
    protected function getDataFromWebPage(Resource $resource)
    {
        try {
            $title = $resource->getCrawler()->filterXpath('//title')->text();
        } catch (\InvalidArgumentException $exc) {
            $title = '';
        }

        $url = $resource->getUri()->toString();

        $contentType = explode(';', $resource->getResponse()->getContentType());
        $type = [];
        if (array_key_exists(0, $contentType)) {
            $contentType = array_slice($contentType, 0, 1);
            $type = array_merge($contentType, explode('/', $contentType[0]));
        }
        $lastModifiedDateTime = new \DateTime($resource->getResponse()->getLastModified());
        $lastModified = $lastModifiedDateTime->format('Y-m-d\TH:i:s\Z');

        try {
            $author = $resource->getCrawler()->filterXPath('//head/meta[@name="author"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $author = '';
        }

        try {
            $description = $resource->getCrawler()->filterXPath('//head/meta[@name="description"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $description = '';
        }

        try {
            $keywords = $resource->getCrawler()->filterXPath('//head/meta[@name="keywords"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $keywords = '';
        }

        try {
            $sIMArchive = $resource->getCrawler()->filterXPath('//head/meta[@name="SIM_archief"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $sIMArchive = 'no';
        }

        try {
            $sIM_simfaq = [$resource->getCrawler()->filterXPath('//head/meta[@name="SIM.simfaq"]/@content')->text()];
        } catch (\InvalidArgumentException $exc) {
            $sIM_simfaq = ['no'];
        }

        try {
            $dCTERMS_modifiedDateTime = new \DateTime($resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.modified"]/@content')->text());
            $dCTERMS_modified = $dCTERMS_modifiedDateTime->format('Y-m-d\TH:i:s\Z');
        } catch (\InvalidArgumentException $exc) {
            $dCTERMS_modified = date('Y-m-d\TH:i:s\Z');
        }

        try {
            $dCTERMS_identifier = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.identifier"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $dCTERMS_identifier = $url;
        }

        try {
            $dCTERMS_title = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.title"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $dCTERMS_title = $title;
        }

        try {
            $dCTERMS_availableDateTime = new \DateTime($resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.available"]/@content')->text());
            $dCTERMS_available = $dCTERMS_availableDateTime->format('Y-m-d\TH:i:s\Z');
        } catch (\InvalidArgumentException $exc) {
            $dCTERMS_available = date('Y-m-d\TH:i:s\Z');
        }

        try {
            $dCTERMS_language = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.language"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $dCTERMS_language = 'nl-NL';
        }

        try {
            $dCTERMS_type = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.type"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            $dCTERMS_type = 'webpagina';
        }

        $content = $this->getContentFromResource($resource);

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
                sprintf("Webpage didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $data = [
            'document' => [
                'id' => sha1($url),
//                'boost' => 0,
                'url' => $url,
                'content' => $content,
                'title' => $title,
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'type' => $type,
                'contentLength' => strlen($content),
                'lastModified' => $lastModified,
                'date' => date('Y-m-d\TH:i:s\Z'),
                'lang' => 'nl-NL',
                'author' => $author,
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'updatedDate' => date('Y-m-d\TH:i:s\Z'),
                'strippedContent' => strip_tags($content),
                'collection' => ['Alles'],
                'description' => $description,
                'keywords' => $keywords,
                'SIM_archief' => $sIMArchive,
                'SIM.simfaq' => $sIM_simfaq,
                'DCTERMS.modified' => $dCTERMS_modified,
                'DCTERMS.identifier' => $dCTERMS_identifier,
                'DCTERMS.title' => $dCTERMS_title,
                'DCTERMS.available' => $dCTERMS_available,
                'DCTERMS.language' => $dCTERMS_language,
                'DCTERMS.type' => $dCTERMS_type,
            ],
        ];

        try {
            $data['document']['SIM.item_trefwoorden'] = $resource->getCrawler()->filterXPath('//head/meta[@name="SIM.item_trefwoorden"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            //do nothing field is not existing for that document
        }

        try {
            $data['document']['SIM.simloket_synoniemen'] = $resource->getCrawler()->filterXPath('//head/meta[@name="SIM.simloket_synoniemen"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            //do nothing field is not existing for that document
        }

        try {
            $data['document']['DCTERMS.spatial'] = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.spatial"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            //do nothing field is not existing for that document
        }

        try {
            $data['document']['DCTERMS.audience'] = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.audience"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            //do nothing field is not existing for that document
        }

        try {
            $data['document']['DCTERMS.subject'] = $resource->getCrawler()->filterXPath('//head/meta[@name="DCTERMS.subject"]/@content')->text();
        } catch (\InvalidArgumentException $exc) {
            //do nothing field is not existing for that document
        }

        return $data;
    }

    /**
     * Extracts all text content from the crawled resource exception javascript.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    protected function getContentFromResource(Resource $resource)
    {
        $crawler = $resource->getCrawler();

        if (null !== $this->cssBlacklist) {
            $crawler->filter($this->cssBlacklist)->each(function (Crawler $crawler) {
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
            });
        }

        $query = '//body//*[not(self::script)]/text()';
        $content = '';
        $crawler->filterXpath($query)->each(
            function (Crawler $crawler) use (&$content) {
                $text = trim($crawler->text());

                if (strlen($text) > 0) {
                    $content .= $text . ' ';
                }
            }
        );

        return trim($content);
    }
}
