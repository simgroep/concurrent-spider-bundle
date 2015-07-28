<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use VDB\Uri\Uri;
use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

class DiscoverUrlListener
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    private $queue;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Indexer
     */
    private $indexer;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     */
    public function __construct(Queue $queue, Indexer $indexer)
    {
        $this->queue = $queue;
        $this->indexer = $indexer;
    }

    /**
     * Writes the found URL as a job on the queue.
     *
     * And URL is only persisted to the queue when it not has been indexed yet.
     *
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public function onDiscoverUrl(Event $event)
    {
        $crawlJob = $event->getSubject()->getCurrentCrawlJob();

        foreach ($event['uris'] as $uri) {
            if (($position = strpos($uri, '#'))) {
                $uri = new Uri(substr($uri, 0, $position));
            }

            if (!$this->indexer->isUrlIndexed($uri->toString(), $crawlJob->getMetadata())) {
                $job = new CrawlJob(
                    $uri->normalize()->toString(),
                    (new Uri($crawlJob->getUrl()))->normalize()->toString(),
                    $crawlJob->getBlacklist(),
                    $crawlJob->getMetadata()
                );

                $this->queue->publishJob($job);
            }
        }
    }
}
