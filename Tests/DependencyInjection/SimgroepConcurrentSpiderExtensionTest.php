<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests\DependencyInjection;

use PHPUnit_Framework_TestCase;

class SimgroepConcurrentSpiderExtensionTest extends PHPUnit_Framework_TestCase
{
    public function testIfAllParametersAreSet()
    {
        $extension = $this
            ->getMockBuilder('Simgroep\ConcurrentSpiderBundle\DependencyInjection\SimgroepConcurrentSpiderExtension')
            ->disableOriginalConstructor()
            ->setMethods(array('processConfiguration'))
            ->getMock();

        $container = $this
            ->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->setMethods(array('setParameter'))
            ->getMock();

        $container
            ->expects($this->exactly(12))
            ->method('setParameter')
            ->withConsecutive(
                array($this->equalTo('simgroep_concurrent_spider.http_user_agent'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.rabbitmq.host'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.rabbitmq.port'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.rabbitmq.user'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.rabbitmq.password'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.queue.discoveredurls_queue'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.queue.indexer_queue'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.solr.host'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.solr.port'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.solr.path'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.logger_service'), $this->anything()),
                array($this->equalTo('simgroep_concurrent_spider.mapping'), $this->anything())
            );

        $config = array(
            'simgroep_concurrent_spider' => array(
                'http_user_agent' => "PHP Concurrent Spider",
                'rabbitmq' => array(
                    'host' => 'localhost',
                    'port' => 5672,
                    'user' => 'guest',
                    'password' => 'guest'
                ),
                'queue' => array(
                    'discoveredurls_queue' => 'discovered_urls',
                    'indexer_queue' => 'indexer',
                ),
                'solr' => array(
                    'host' => 'localhost',
                    'port' => 8080,
                    'path' => '/solr',
                ),
                'mapping' => array(
                    'id' => 'id',
                    'url' => 'url',
                    'content' => 'content',
                    'title' => 'title',
                )
            )
        );

        $extension->load($config, $container);
    }
}
