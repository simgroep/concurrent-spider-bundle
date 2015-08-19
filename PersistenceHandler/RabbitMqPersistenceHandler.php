<?php

namespace Simgroep\ConcurrentSpiderBundle\PersistenceHandler;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler;
use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver;

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
     *
     * @var Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver
     */
    private $documentResolver;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     * @param Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver $documentResolver
     */
    public function __construct(Queue $queue, DocumentResolver $documentResolver)
    {
        $this->queue = $queue;
        $this->documentResolver = $documentResolver;
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
        $this->documentResolver->resolveTypeFromResource($resource);
        $data = $this->documentResolver->getData();

        if (isset($data)) {
            $message = new AMQPMessage(
                json_encode(array_merge($data, ['metadata' => $crawlJob->getMetadata()])),
                ['delivery_mode' => 1]
            );

            $this->queue->publish($message);
        }
    }
}
