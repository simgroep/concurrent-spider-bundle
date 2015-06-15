<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Smalot\PdfParser\Parser;
use Symfony\Component\DomCrawler\Crawler;
use VDB\Spider\PersistenceHandler\PersistenceHandler;
use VDB\Spider\Resource;
use InvalidArgumentException;

/**
 * The content of crawled webpages is saved to a seperate queue that is designed for indexing documents.
 */
class RabbitMqPersistenceHandler implements PersistenceHandler
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Smalot\PdfParser\Parser
     */
    private $pdfParser;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     * @param \Smalot\PdfParser\Parser               $pdfParser
     */
    public function __construct(Queue $queue, Parser $pdfParser)
    {
        $this->queue = $queue;
        $this->pdfParser = $pdfParser;
    }

    /**
     * @{inheritDoc}
     */
    public function setSpiderId($spiderId)
    {
    }

    /**
     * Grabs the content from the crawled page and publishes a job on the queue.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @throws \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function persist(Resource $resource)
    {
        switch ($resource->getResponse()->getContentType()) {
            case 'application/pdf':
                $data = json_encode($this->getDataFromPdfFile($resource));

                if (!$data) {
                    throw new InvalidContentException("Couldn't create a JSON string while extracting PDF.");
                }

                break;

            case 'text/html':
            default:
                try {
                    $data = json_encode($this->getDataFromWebPage($resource));
                } catch (InvalidArgumentException $e) {
                    throw new InvalidContentException("Couldn't crawl though DOM to obtain the web page contents.");
                }

                break;
        }

        if (isset($data)) {
            $message = new AMQPMessage($data, array('delivery_mode' => 1));
            $this->queue->publish($message);
        }
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
        $title = '';

        if (false !== stripos($url, '.pdf')) {
            $parts = parse_url($url);
            $title = basename($parts['path']);
        }

        $data = array(
            'document' => array(
                'id' => sha1($url),
                'title' => $title,
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'date' => date('Y-m-d\TH:i:s\Z'),
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'content' => $pdf->getText(),
                'url' => $url,
            ),
        );

        return $data;
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
        $title = $resource->getCrawler()->filterXpath('//title')->text();
        $url = $resource->getUri()->toString();

        $content = $this->getContentFromResource($resource);
        $data = array(
            'document' => array(
                'id' => sha1($url),
                'title' => $title,
                'tstamp' => date('Y-m-d\TH:i:s\Z'),
                'date' => date('Y-m-d\TH:i:s\Z'),
                'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                'content' => $content,
                'url' => $url,
            ),
        );

        return $data;
    }

    /**
     * Extracts all text content from the crawled resource exception javascript.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    private function getContentFromResource(Resource $resource)
    {
        $query = '//*[not(self::script)]/text()';
        $content = '';
        $resource->getCrawler()->filterXpath($query)->each(
            function (Crawler $crawler) use (&$content) {
                $text = trim($crawler->text());

                if (strlen($text) > 0) {
                    $content .= $text . ' ';
                }
            }
        );

        return $content;
    }
}
