<?php

namespace Simgroep\ConcurrentSpiderBundle;

use PhpAmqpLib\Message\AMQPMessage;

class CrawlJob
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var array
     */
    private $blacklist;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var array
     */
    private $whitelist;

    /**
     * @var null|string
     */
    private $queueName;

    /**
     * Constrcutor.
     *
     * @var string      $url
     * @var string      $baseUrl
     * @var array       $blacklist
     * @var array       $metadata
     * @var array       $whitelist
     * @var null|string $queueName
     */
    public function __construct(
        $url,
        $baseUrl,
        array $blacklist = [],
        array $metadata = [],
        array $whitelist = [],
        $queueName = null
    )
    {
        $this->url = $url;
        $this->baseUrl = $baseUrl;
        $this->blacklist = $blacklist;
        $this->metadata = $metadata;
        $this->whitelist = $whitelist;
        $this->queueName = $queueName;
    }

    /**
     * Factory method for creating a job.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @return \Simgroep\ConcurrentSpiderBundle\CrawlJob
     */
    public static function create(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        $urlToCrawl = $data['url'];
        $baseUrl = $data['base_url'];
        $blacklist = $data['blacklist'];
        $metadata = $data['metadata'];
        $whitelist = $data['whitelist'];
        $queueName = $data['queueName'];

        return new self($urlToCrawl, $baseUrl, $blacklist, $metadata, $whitelist, $queueName);
    }

    /**
     * Check if url form job is allowed to be crawled
     *
     * @return boolean
     */
    public function isAllowedToCrawl()
    {
        return UrlCheck::isAllowedToCrawl($this->url, $this->baseUrl, $this->blacklist, $this->whitelist);
    }

    /**
     * Returns an array representation of this object.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'url' => $this->url,
            'base_url' => $this->baseUrl,
            'blacklist' => $this->blacklist,
            'metadata' => $this->metadata,
            'whitelist' => $this->whitelist,
            'queueName' => $this->queueName,
        ];
    }

    /**
     * Returns the URL of this job.
     *
     * @return string
     */
    public function getUrl()
    {
        return UrlCheck::fixUrl($this->url);
    }

    /**
     * Returns the base url of this job.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return UrlCheck::fixUrl($this->baseUrl);
    }

    /**
     * Returns the metadata that belongs to this job.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Returns the blacklist that belongs to this job.
     *
     * @return array
     */
    public function getBlacklist()
    {
        return $this->blacklist;
    }

    /**
     * Returns the whitelist that belongs to this job.
     *
     * @return array
     */
    public function getWhitelist()
    {
        return $this->whitelist;
    }

    /**
     * Returns queueName if passed
     *
     * @return null|string
     */
    public function getQueueName()
    {
        return $this->queueName;
    }
}
