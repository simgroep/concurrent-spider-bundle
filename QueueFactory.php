<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Exception;
use Simgroep\ConcurrentSpiderBundle\Queue;


class QueueFactory
{
    const QUEUE_URLS = 'urls';
    const QUEUE_DOCUMENTS = 'documents';

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    protected $queueUrls;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\Queue
     */
    protected $queueDocuments;

    /**
     * QueueFactory constructor.
     *
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queueUrls
     * @param \Simgroep\ConcurrentSpiderBundle\Queue $queueDocuments
     */
    public function __construct(Queue $queueUrls, Queue $queueDocuments)
    {
        $this->queueUrls = $queueUrls;
        $this->queueDocuments = $queueDocuments;
    }

    /**
     * @param string $queueName
     *
     * @return \Simgroep\ConcurrentSpiderBundle\Queue
     * @throws Exception
     */
    public function getQueue($queueName)
    {

        switch ($queueName) {
            case self::QUEUE_URLS:
                return $this->queueUrls;
                break;
            case self::QUEUE_DOCUMENTS:
                return $this->queueDocuments;
                break;
            default:
                throw new Exception ('Unknown queue!');

        }

    }


}
