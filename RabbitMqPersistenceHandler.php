<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;
use Simgroep\ConcurrentSpiderBundle\Queue;
use VDB\Spider\PersistenceHandler\PersistenceHandler;
use VDB\Spider\Resource;

class RabbitMqPersistenceHandler implements PersistenceHandler
{
    private $queue;

    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    public function setSpiderId($spiderId)
    {
    }

    public function persist(Resource $resource)
    {
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
    }
}
