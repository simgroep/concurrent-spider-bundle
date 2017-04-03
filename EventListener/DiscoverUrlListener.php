<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use Predis\Client;
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
     * @var Client
     */
    private $redis;

    /**
     * @var
     */
    private $ttl;

    /**
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     * @param EventDispatcher $eventDispatcher
     * @param Client $redis
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
        $filteredUris = $this->indexer->filterIndexedAndNotExpired($event['uris'], $crawlJob->getMetadata());

        foreach ($filteredUris as $uri) {
            $uri = UrlCheck::normalizeUri($uri);

            if (
                UrlCheck::isAllowedToCrawl(
                    UrlCheck::fixUrl($uri->normalize()->toString()),
                    (new Uri($crawlJob->getUrl()))->normalize()->toString(),
                    $crawlJob->getBlacklist(),
                    $crawlJob->getWhitelist()
                ) && !$this->isAlreadyInQueue($uri, $crawlJob->getMetadata())

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
    }

    /**
     * Check if uri was added to queue before.
     *
     * @param $uri
     * @param $collectionName
     *
     * @return bool
     */
    protected function isAlreadyInQueue($uri, $collectionName)
    {

        if ($this->queue->getName() == 'discovered_urls') {
            $setUriKey = $collectionName["core"];

            $uriHash = $this->indexer->getHashSolarId(UrlCheck::fixUrl($uri->toString()));
            if (in_array($uriHash, $this->redis->smembers($setUriKey))) {
                return true;
            }

            $crawledUrls[$uriHash] = $this->redis->scard($setUriKey);
            $this->redis->sadd($setUriKey, array_keys($crawledUrls));
            $this->redis->expire($setUriKey, $this->ttl);
        }

        return false;
    }

}
