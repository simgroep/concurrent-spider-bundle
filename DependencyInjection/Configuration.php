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
                ->scalarNode('solarium_adapter')
                    ->cannotBeEmpty()
                    ->defaultValue('Solarium_Client_Adapter_Curl')
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
                ->scalarNode('css_blacklist')
                    ->defaultValue(null)
                ->end()
                ->arrayNode('mapping')
                    ->isRequired()
                    ->children()
                        # required fields
                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('title')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('tstamp')->cannotBeEmpty()->end()
                        ->scalarNode('date')->cannotBeEmpty()->end()
                        ->scalarNode('publishedDate')->cannotBeEmpty()->end()
                        ->scalarNode('content')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('url')->isRequired()->cannotBeEmpty()->end()
                        # end of required
                        ->scalarNode('segment')->cannotBeEmpty()->end()
                        ->scalarNode('digest')->cannotBeEmpty()->end()
                        ->scalarNode('boost')->cannotBeEmpty()->end()
                        ->scalarNode('host')->cannotBeEmpty()->end()
                        ->scalarNode('site')->cannotBeEmpty()->end()
                        ->scalarNode('cache')->cannotBeEmpty()->end()
                        ->scalarNode('anchor')->cannotBeEmpty()->end()
                        ->scalarNode('type')->cannotBeEmpty()->end()
                        ->scalarNode('contentLength')->cannotBeEmpty()->end()
                        ->scalarNode('lastModified')->cannotBeEmpty()->end()
                        ->scalarNode('lang')->cannotBeEmpty()->end()
                        ->scalarNode('subcollection')->cannotBeEmpty()->end()
                        ->scalarNode('author')->cannotBeEmpty()->end()
                        ->scalarNode('tag')->cannotBeEmpty()->end()
                        ->scalarNode('feed')->cannotBeEmpty()->end()
                        ->scalarNode('updatedDate')->cannotBeEmpty()->end()
                        ->scalarNode('cc')->cannotBeEmpty()->end()
                        ->scalarNode('strippedContent')->cannotBeEmpty()->end()
                        ->scalarNode('collection')->cannotBeEmpty()->end()
                        ->scalarNode('description')->cannotBeEmpty()->end()
                        ->scalarNode('keywords')->cannotBeEmpty()->end()
                        ->scalarNode('SIM_archief')->cannotBeEmpty()->end()
                        ->arrayNode('groups')
                            ->children()
                            ->arrayNode('SIM')
                                ->children()
                                    ->scalarNode('item_trefwoorden')->cannotBeEmpty()->end()
                                    ->scalarNode('simloket_synoniemen')->cannotBeEmpty()->end()
                                    ->scalarNode('simfaq')->cannotBeEmpty()->end()
                                ->end()
                            ->end()

                            ->arrayNode('DCTERMS')
                                ->children()
                                    ->scalarNode('modified')->cannotBeEmpty()->end()
                                    ->scalarNode('identifier')->cannotBeEmpty()->end()
                                    ->scalarNode('title')->cannotBeEmpty()->end()
                                    ->scalarNode('available')->cannotBeEmpty()->end()
                                    ->scalarNode('spatial')->cannotBeEmpty()->end()
                                    ->scalarNode('audience')->cannotBeEmpty()->end()
                                    ->scalarNode('subject')->cannotBeEmpty()->end()
                                    ->scalarNode('language')->cannotBeEmpty()->end()
                                    ->scalarNode('type')->cannotBeEmpty()->end()
                                ->end()
                            ->end()

                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
