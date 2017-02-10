<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Exception;

class QueueFactory
{
    const QUEUE_URLS = 'urls';
    const QUEUE_DOCUMENTS = 'documents';

    /**
     * @var Queue
     */
    protected $queueUrls;

    /**
     * @var Queue
     */
    protected $queueDocuments;

    /**
     * QueueFactory constructor.
     *
     * @param Queue $queueUrls
     * @param Queue $queueDocuments
     */
    public function __construct(Queue $queueUrls, Queue $queueDocuments)
    {
        $this->queueUrls = $queueUrls;
        $this->queueDocuments = $queueDocuments;
    }

    /**
     * @param string $queueName
     *
     * @return Queue
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
