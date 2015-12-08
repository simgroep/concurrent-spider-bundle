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
     * Constrcutor.
     *
     * @var string $url
     * @var string $baseUrl
     * @var array  $blacklist
     * @var array  $metadata
     * @var array  $whitelist
     */
    public function __construct($url, $baseUrl, array $blacklist = [], array $metadata = [], array $whitelist = [])
    {
        $this->url = $url;
        $this->baseUrl = $baseUrl;
        $this->blacklist = $blacklist;
        $this->metadata = $metadata;
        $this->whitelist = $whitelist;
    }

    /**
     * Factory method for creating a job.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public static function create(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);

        $urlToCrawl = $data['url'];
        $baseUrl = $data['base_url'];
        $blacklist = $data['blacklist'];
        $metadata = $data['metadata'];
        $whitelist = $data['whitelist'];

        return new self($urlToCrawl, $baseUrl, $blacklist, $metadata, $whitelist);
    }

    /**
     * Check if url is whitelisted
     *
     * @return boolean
     */
    private function isUrlWhitelisted()
    {
        if (count($this->whitelist) == 0) {
            return false;
        }

        $isWhitelisted = false;
        $url = $this->url;

        array_walk(
            $this->whitelist,
            function ($whitelistUrl) use ($url, &$isWhitelisted) {
                if (@preg_match('#' . $whitelistUrl . '#', $url)) {
                    $isWhitelisted = true;
                }
            }
        );

        return $isWhitelisted;
    }

    /**
     * Indicates whether the hostname parts of url and base_url are equal.
     * 
     * @return boolean
     */
    private function areHostsEqual()
    {
        $firstHost = parse_url($this->url, PHP_URL_HOST);
        $secondHost = parse_url($this->baseUrl, PHP_URL_HOST);

        if (is_null($firstHost) || is_null($secondHost)) {
            return false;
        }

        return ($firstHost === $secondHost);
    }

    /**
     * Indicated wether the url of the crawljob is blacklisted.
     *
     * @return boolean
     */
    private function isUrlBlacklisted()
    {
        $isBlacklisted = false;
        $url = $this->url;

        array_walk(
            $this->blacklist,
            function ($blacklistUrl) use ($url, &$isBlacklisted) {
                if (@preg_match('#' . $blacklistUrl . '#', $url)) {
                    $isBlacklisted = true;
                }
            }
        );

        return $isBlacklisted;
    }

    /**
     * Check if url form job is allowed to be crawled
     *
     * @return boolean
     */
    public function isAllowedToCrawl()
    {
        if ($this->isUrlBlacklisted()) {
            return false;
        }

        if ($this->areHostsEqual()) {
            return true;
        }

        if (!$this->areHostsEqual() && $this->isUrlWhiteListed()) {
            return true;
        }

        return false;
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
        ];
    }

    /**
     * Returns the URL of this job.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Returns the base url of this job.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
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
}
