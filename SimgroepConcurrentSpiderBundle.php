<?php

namespace Simgroep\ConcurrentSpiderBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

class SimgroepConcurrentSpiderBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(
            new RegisterListenersPass(
                'simgroep_concurrent_spider.event_dispatcher',
                'simgroep_concurrent_spider.event_listener',
                'simgroep_concurrent_spider.event_subscriber'
            ),
            PassConfig::TYPE_BEFORE_REMOVING
        );
    }
}
