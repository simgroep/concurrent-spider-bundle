<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Simgroep\ConcurrentSpiderBundle\UrlCheck;
use VDB\Uri\Uri;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Simgroep\ConcurrentSpiderBundle\CrawlJob;
use Symfony\Component\EventDispatcher\GenericEvent as Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var \Predis\Client
     */
    private $redis;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var array
     */
    private $smembers = [];

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param EventDispatcher $eventDispatcher
     * @param \Predis\Client $redis
     * @param int $ttl
     */
    public function __construct(Queue $queue, Indexer $indexer, EventDispatcher $eventDispatcher, Client $redis, $ttl)
    {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->eventDispatcher = $eventDispatcher;
        $this->redis = $redis;
        $this->ttl = $ttl;
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
        $metadata = $crawlJob->getMetadata();
        $filteredUris = $this->indexer->filterIndexedAndNotExpired($event['uris'], $metadata);
        $setUriKey = sprintf('%s_%s',
            $metadata["core"],
            $this->queue->getName()
        );

        try {
            $this->smembers = $this->redis->smembers($setUriKey);
        } catch (ConnectionException $e) {
            $this->smembers = [];
        }

        foreach ($filteredUris as $uri) {
            $uri = UrlCheck::normalizeUri($uri);

            if (
                UrlCheck::isAllowedToCrawl(
                    UrlCheck::fixUrl($uri->normalize()->toString()),
                    (new Uri($crawlJob->getUrl()))->normalize()->toString(),
                    $crawlJob->getBlacklist(),
                    $crawlJob->getWhitelist()
                ) && !$this->isAlreadyInQueue($uri)

            ) {

                $job = new CrawlJob(
                    UrlCheck::fixUrl($uri->normalize()->toString()),
                    (new Uri($crawlJob->getUrl()))->normalize()->toString(),
                    $crawlJob->getBlacklist(),
                    $crawlJob->getMetadata(),
                    $crawlJob->getWhitelist(),
                    $crawlJob->getQueueName()
                );

                $this->queue->publishJob($job);
            }
        }

        if (count($this->smembers)) {
            try {
                $this->redis->sadd($setUriKey, $this->smembers);
                $this->redis->expire($setUriKey, $this->ttl);
            } catch (ConnectionException $e) {}
        }
    }

    /**
     * Check if uri was added to queue before.
     *
     * @param Uri $uri
     *
     * @return bool
     */
    public function isAlreadyInQueue($uri)
    {
        $uriHash = $this->indexer->getHashSolarId(UrlCheck::fixUrl($uri->toString()));
        if (in_array($uriHash, $this->smembers)) {
            return true;
        }
        $this->smembers[] = $uriHash;

        return false;
    }

}
