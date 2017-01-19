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
                ->scalarNode('maximum_resource_size')->cannotBeEmpty()->defaultValue('16mb')->end()
                ->scalarNode('http_user_agent')
                    ->cannotBeEmpty()
                    ->defaultValue('PHP Concurrent Spider')
                ->end()
                ->scalarNode('curl_cert_ca_directory')
                    ->cannotBeEmpty()
                    ->defaultValue('/usr/local/share/certs/')
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
                ->integerNode('minimal_revisit_factor')->isRequired()->cannotBeEmpty()->defaultValue(60)->end()
                ->integerNode('maximum_revisit_factor')->isRequired()->cannotBeEmpty()->defaultValue(20160)->end()
                ->integerNode('default_revisit_factor')->isRequired()->cannotBeEmpty()->defaultValue(1560)->end()
                ->arrayNode('queue')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('discoveredurls_queue')->isRequired()->cannotBeEmpty()->defaultValue('discovered_urls')->end()
                        ->scalarNode('indexer_queue')->isRequired()->cannotBeEmpty()->defaultValue('indexer')->end()
                        ->scalarNode('recrawl_queue')->isRequired()->cannotBeEmpty()->defaultValue('recrawl')->end()
                    ->end()
                ->end()
                ->scalarNode('solr_client')->isRequired()->cannotBeEmpty()->defaultValue('default')->end()
                ->scalarNode('logger_service')
                    ->cannotBeEmpty()
                    ->defaultValue('logger')
                ->end()
                ->scalarNode('css_blacklist')
                    ->defaultValue(null)
                ->end()
                ->integerNode('minimal_document_save_amount')
                    ->isRequired()
                    ->defaultValue(50)
                ->end()
                ->scalarNode('pdf_to_txt_command')
                    ->isRequired()
                    ->defaultValue('/usr/local/sbin/pdftotext')
                ->end()
                ->arrayNode('mapping')
                    ->addDefaultsIfNotSet()
                    ->children()
                        # required fields
                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->defaultValue('id')->end()
                        ->scalarNode('title')->isRequired()->cannotBeEmpty()->defaultValue('title')->end()
                        ->scalarNode('tstamp')->cannotBeEmpty()->defaultValue('tstamp')->end()
                        ->scalarNode('date')->cannotBeEmpty()->defaultValue('date')->end()
                        ->scalarNode('publishedDate')->cannotBeEmpty()->defaultValue('publishedDate')->end()
                        ->scalarNode('content')->isRequired()->cannotBeEmpty()->defaultValue('content')->end()
                        ->scalarNode('url')->isRequired()->cannotBeEmpty()->defaultValue('url')->end()
                        ->scalarNode('revisit_after')->isRequired()->cannotBeEmpty()->defaultValue('revisit_after')->end()
                        ->scalarNode('revisit_expiration')->isRequired()->cannotBeEmpty()->defaultValue('revisit_expiration')->end()
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
