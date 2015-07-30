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
            ->setMethods(['processConfiguration'])
            ->getMock();

        $container = $this
            ->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->setMethods(['setParameter'])
            ->getMock();

        $container
            ->expects($this->exactly(12))
            ->method('setParameter')
            ->withConsecutive(
                [$this->equalTo('simgroep_concurrent_spider.http_user_agent'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.host'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.port'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.user'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.password'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.queue.discoveredurls_queue'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.queue.indexer_queue'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.solr.host'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.solr.port'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.solr.path'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.logger_service'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.mapping'), $this->anything()]
            );

        $config = [
            'simgroep_concurrent_spider' => [
                'http_user_agent' => "PHP Concurrent Spider",
                'rabbitmq' => [
                    'host' => 'localhost',
                    'port' => 5672,
                    'user' => 'guest',
                    'password' => 'guest'
                ],
                'queue' => [
                    'discoveredurls_queue' => 'discovered_urls',
                    'indexer_queue' => 'indexer',
                ],
                'solr' => [
                    'host' => 'localhost',
                    'port' => 8080,
                    'path' => '/solr',
                ],
                'mapping' => [
                    'id' => 'id',
                    'url' => 'url',
                    'content' => 'content',
                    'title' => 'title',
                ]
            ]
        ];

        $extension->load($config, $container);
    }
}
