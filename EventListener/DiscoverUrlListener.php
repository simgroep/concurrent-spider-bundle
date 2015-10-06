<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

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
     * Constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue   $queue
     * @param \Simgroep\ConcurrentSpiderBundle\Indexer $indexer
     */
    public function __construct(Queue $queue, Indexer $indexer, EventDispatcher $eventDispatcher )
    {
        $this->queue = $queue;
        $this->indexer = $indexer;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Check if given url is blacklisted
     *
     * @param string $url
     * @param array $blacklist
     *
     * @return boolean
     */
    public function isUrlBlacklisted($url, array $blacklist)
    {
        $isBlacklisted = false;

        array_walk(
            $blacklist,
            function ($blacklistUrl) use ($url, &$isBlacklisted) {
                if (@preg_match('#' . $blacklistUrl . '#', $url)) {
                    $isBlacklisted = true;
                }
            }
        );

        return $isBlacklisted;
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

            $isBlacklisted = $this->isUrlBlacklisted($uri->normalize()->toString(), $crawlJob->getBlacklist());

            if ($isBlacklisted) {
                $this->eventDispatcher->dispatch(
                    "spider.crawl.blacklisted",
                    new Event($this, ['uri' => $uri])
                );

                continue;//url blacklisted, so go to next one
            }

            if (!$this->indexer->isUrlIndexedandNotExpired($uri->toString(), $crawlJob->getMetadata())) {
                $job = new CrawlJob(
                    $uri->normalize()->toString(),
                    (new Uri($crawlJob->getUrl()))->normalize()->toString(),
                    $crawlJob->getBlacklist(),
                    $crawlJob->getWhitelist(),
                    $crawlJob->getMetadata()
                );

                $this->queue->publishJob($job);
            }
        }
    }

}
