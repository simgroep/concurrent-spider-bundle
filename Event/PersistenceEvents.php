<?php

namespace Simgroep\ConcurrentSpiderBundle\Event;

final class PersistenceEvents
{
    /**
     * Event that is fired after data has been pulled from the resource
     * and before this data is being send to the persistence layer.
     *
     * By modifying the document object in this event you can influence the data data is eventually saved.
     */
    const PRE_PERSIST = 'simgroep_concurrent_spider.pre_persist';
}
