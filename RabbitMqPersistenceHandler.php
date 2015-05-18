<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
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
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
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
     */
    public function persist(Resource $resource)
    {
        try {
            $title = $resource->getCrawler()->filterXpath('//title')->text();

            $content = $this->getContentFromResource($resource);
            $data = array(
                'document' => array(
                    'id' => sha1($resource->getUri()),
                    'title' => $title,
                    'tstamp' => date('Y-m-d\TH:i:s\Z'),
                    'date' => date('Y-m-d\TH:i:s\Z'),
                    'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                    'content' => $content,
                    'url' => $resource->getUri()->toString(),
                ),
            );

            $message = new AMQPMessage(json_encode($data), array('delivery_mode' => 1));

            $this->queue->publish($message);
        } catch (InvalidArgumentException $e) {
            //Content couldn't be extracted so saving the document would be silly.
        }

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
