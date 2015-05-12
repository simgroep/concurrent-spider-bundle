<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use Simgroep\ConcurrentSpiderBundle\Indexer;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

class DiscoverUrlListener
{
    private $queue;
    private $indexer;

    public function __construct(Queue $queue, Indexer $indexer)
    {
        $this->queue = $queue;
        $this->indexer = $indexer;
    }

    public function onDiscoverUrl(Event $event)
    {
        foreach ($event['uris'] as $uri) {
            if (!$this->indexer->isUrlIndexed($uri->toString())) {
                $data = array(
                    'uri' => $uri->toString(),
                    'base_url' => $event->getSubject()->getStartUri()
                );
                $data = json_encode($data);

                $message = new AMQPMessage($data, array('delivery_mode' => 1));
                $this->queue->publish($message);
            }
        }
    }
}
