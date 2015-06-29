<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
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

    public function isUrlBlacklisted($url, $blacklist)
    {
        $isBlacklisted = false;

        array_walk(
            $blacklist,
            function ($blacklistUrl) use ($url, &$isBlacklisted) {
                if (@preg_match('/' . $blacklistUrl . '/', $url)) {
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
        foreach ($event['uris'] as $uri) {
            $blacklist = $event->getSubject()->getBlacklist();
            $url = $uri->normalize()->toString();
            $isBlacklisted = $this->isUrlBlacklisted($url, $blacklist);

            if (!$isBlacklisted) {
                if (!$this->indexer->isUrlIndexed($uri->toString())) {
                    $data = array(
                        'uri' => $url,
                        'base_url' => $event->getSubject()->getCurrentUri()->normalize()->toString(),
                        'blacklist' => $blacklist
                    );
                    $data = json_encode($data);

                    $message = new AMQPMessage($data, array('delivery_mode' => 1));
                    $this->queue->publish($message);
                }
            }
        }
    }
}
