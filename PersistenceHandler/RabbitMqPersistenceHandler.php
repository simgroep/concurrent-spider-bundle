<?php

namespace Simgroep\ConcurrentSpiderBundle\PersistenceHandler;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Simgroep\ConcurrentSpiderBundle\PersistenceHandler\PersistenceHandler;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Simgroep\ConcurrentSpiderBundle\Event\PersistenceEvents;
use Simgroep\ConcurrentSpiderBundle\Event\PersistenceEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use VDB\Spider\Resource;

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
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver
     */
    private $documentResolver;

    /**
     * @var integer
     */
    private $maximumResourceSize;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue                             $queue
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentResolver $documentResolver
     * @param string                                                             $maximumResourceSize
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface        $eventDispatcher
     */
    public function __construct(
        Queue $queue,
        DocumentResolver $documentResolver,
        $maximumResourceSize,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->queue = $queue;
        $this->documentResolver = $documentResolver;
        $this->maximumResourceSize = self::convertToBytes($maximumResourceSize);
        $this->eventDispatcher = $eventDispatcher;
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
        if (strlen($resource->getResponse()->getBody()) >= $this->maximumResourceSize) {
            throw new InvalidContentException(sprintf('Resource size exceeds limits (%s bytes)', $this->maximumResourceSize));
        }

        $document = $this->documentResolver->getDocumentByResource($resource);
        $persistenceEvent = new PersistenceEvent($document, $resource);

        $this->eventDispatcher->dispatch(PersistenceEvents::PRE_PERSIST, $persistenceEvent);

        $message = new AMQPMessage(
            json_encode(array_merge($document->toArray(), ['metadata' => $crawlJob->getMetadata()])),
            ['delivery_mode' => 1]
        );

        $this->queue->publish($message);
    }

    /**
     * Function that returns the human readable size in bytes.
     *
     * @param string $fileSize
     *
     * @return integer
     */
    public static function convertToBytes($fileSize)
    {
        $number = substr($fileSize, 0, -2);

        switch (strtoupper(substr($fileSize,-2))) {
            case "KB":
                return $number * 1024;
            case "MB":
                return $number * pow(1024,2);
            case "GB":
                return $number * pow(1024,3);
            case "TB":
                return $number * pow(1024,4);
            case "PB":
                return $number * pow(1024,5);
            default:
                return $fileSize;
        }
    }
}
