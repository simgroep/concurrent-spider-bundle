<?php

namespace Simgroep\ConcurrentSpiderBundle\EventListener;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;
use Monolog\Logger;

class UrlBlacklistedListener extends Event
{
    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param \Monolog\Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function onBlacklisted(GenericEvent $event)
    {
        $url = $event->getArgument('uri')->toString();
        $this->logger->info(sprintf('blacklisted %s', $url), ['tags' => [parse_url($url, PHP_URL_HOST)]]);
    }
}
