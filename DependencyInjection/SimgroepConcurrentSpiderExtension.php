<?php

namespace Simgroep\ConcurrentSpiderBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SimgroepConcurrentSpiderExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('simgroep_concurrent_spider.http_user_agent', $config['http_user_agent']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.host', $config['rabbitmq']['host']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.port', $config['rabbitmq']['port']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.user', $config['rabbitmq']['user']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.password', $config['rabbitmq']['password']);
        $container->setParameter('simgroep_concurrent_spider.queue.discoveredurls_queue', $config['queue']['discoveredurls_queue']);
        $container->setParameter('simgroep_concurrent_spider.queue.indexer_queue', $config['queue']['indexer_queue']);
        $container->setParameter('simgroep_concurrent_spider.solr.host', $config['solr']['host']);
        $container->setParameter('simgroep_concurrent_spider.solr.port', $config['solr']['port']);
        $container->setParameter('simgroep_concurrent_spider.solr.path', $config['solr']['path']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }
}
