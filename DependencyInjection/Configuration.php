<?php

namespace Simgroep\ConcurrentSpiderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('simgroep_concurrent_spider');

        $rootNode
            ->children()
                ->scalarNode('http_user_agent')
                    ->cannotBeEmpty()
                    ->defaultValue('PHP Concurrent Spider')
                    ->end()
                ->arrayNode('rabbitmq')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('host')->isRequired()->cannotBeEmpty()->defaultValue('localhost')->end()
                        ->integerNode('port')->isRequired()->cannotBeEmpty()->defaultValue('5672')->end()
                        ->scalarNode('user')->isRequired()->cannotBeEmpty()->defaultValue('guest')->end()
                        ->scalarNode('password')->isRequired()->cannotBeEmpty()->defaultValue('guest')->end()
                    ->end()
                ->end()
                ->arrayNode('queue')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('discoveredurls_queue')->isRequired()->cannotBeEmpty()->defaultValue('discovered_urls')->end()
                        ->scalarNode('indexer_queue')->isRequired()->cannotBeEmpty()->defaultValue('indexer')->end()
                    ->end()
                ->end()
                ->arrayNode('solr')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('host')->isRequired()->cannotBeEmpty()->defaultValue('localhost')->end()
                        ->integerNode('port')->isRequired()->cannotBeEmpty()->defaultValue('8080')->end()
                        ->scalarNode('path')->isRequired()->cannotBeEmpty()->defaultValue('/solr')->end()
                    ->end()
                ->end()
                ->scalarNode('logger_service')
                    ->cannotBeEmpty()
                    ->defaultValue('logger')
                    ->end()
                ->arrayNode('mapping')
                    ->isRequired()
                    ->children()
                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('title')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('tstamp')->cannotBeEmpty()->end()
                        ->scalarNode('date')->cannotBeEmpty()->end()
                        ->scalarNode('publishedDate')->cannotBeEmpty()->end()
                        ->scalarNode('content')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('url')->isRequired()->cannotBeEmpty()->end()
                    ->end()
            ->end();

        return $treeBuilder;
    }
}
