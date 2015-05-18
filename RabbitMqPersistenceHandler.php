<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
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
            $content = $resource->getCrawler()->filterXpath('//body')->text();

            $data = array(
                'document' => array(
                    'id' => sha1($resource->getUri()),
                    'title' => $title,
                    'tstamp' => date('Y-m-d\TH:i:s\Z'),
                    'date' => date('Y-m-d\TH:i:s\Z'),
                    'publishedDate' => date('Y-m-d\TH:i:s\Z'),
                    'content' => $content,
                ),
            );

            $message = new AMQPMessage(json_encode($data), array('delivery_mode' => 1));

            $this->queue->publish($message);
        } catch (InvalidArgumentException $e) {
            //Content couldn't be extracted so saving the document would be silly.
        }

    }
}
