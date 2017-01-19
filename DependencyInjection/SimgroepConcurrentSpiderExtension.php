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

        $container->setParameter('simgroep_concurrent_spider.maximum_resource_size', $config['maximum_resource_size']);
        $container->setParameter('simgroep_concurrent_spider.http_user_agent', $config['http_user_agent']);
        $container->setParameter('simgroep_concurrent_spider.curl_cert_ca_directory', $config['curl_cert_ca_directory']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.host', $config['rabbitmq']['host']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.port', $config['rabbitmq']['port']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.user', $config['rabbitmq']['user']);
        $container->setParameter('simgroep_concurrent_spider.rabbitmq.password', $config['rabbitmq']['password']);
        $container->setParameter('simgroep_concurrent_spider.queue.discoveredurls_queue', $config['queue']['discoveredurls_queue']);
        $container->setParameter('simgroep_concurrent_spider.queue.recrawl_queue', $config['queue']['recrawl_queue']);
        $container->setParameter('simgroep_concurrent_spider.queue.indexer_queue', $config['queue']['indexer_queue']);
        $container->setParameter('simgroep_concurrent_spider.solr_client', $config['solr_client']);
        $container->setParameter('simgroep_concurrent_spider.logger_service', $config['logger_service']);
        $container->setParameter('simgroep_concurrent_spider.minimal_document_save_amount', $config['minimal_document_save_amount']);
        $container->setParameter('simgroep_concurrent_spider.pdf_to_txt_command', $config['pdf_to_txt_command']);
        $container->setParameter('simgroep_concurrent_spider.minimal_revisit_factor', $config['minimal_revisit_factor']);
        $container->setParameter('simgroep_concurrent_spider.maximum_revisit_factor', $config['maximum_revisit_factor']);
        $container->setParameter('simgroep_concurrent_spider.default_revisit_factor', $config['default_revisit_factor']);
        $container->setParameter('simgroep_concurrent_spider.mapping', $config['mapping']);
        $container->setParameter('simgroep_concurrent_spider.css_blacklist', $config['css_blacklist']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }
}
