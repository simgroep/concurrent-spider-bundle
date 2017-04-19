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
            ->expects($this->atLeastOnce())
            ->method('setParameter')
            ->withConsecutive(
                [$this->equalTo('simgroep_concurrent_spider.maximum_resource_size'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.http_user_agent'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.curl_cert_ca_directory'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.host'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.port'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.user'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.password'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.rabbitmq.vhost'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.queue.discoveredurls_queue'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.queue.discovereddocuments_queue'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.queue.recrawl_queue'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.queue.indexer_queue'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.solr_client'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.logger_service'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.minimal_document_save_amount'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.pdf_to_txt_command'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.minimal_revisit_factor'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.maximum_revisit_factor'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.default_revisit_factor'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.mapping'), $this->anything()],
                [$this->equalTo('simgroep_concurrent_spider.css_blacklist'), $this->anything()]
            );

        $config = [
            'simgroep_concurrent_spider' => [
                'http_user_agent' => "PHP Concurrent Spider",
                'curl_cert_ca_directory' => '/usr/local/share/certs/',
                'rabbitmq' => [
                    'host' => 'localhost',
                    'port' => 5672,
                    'user' => 'guest',
                    'password' => 'guest',
                    'vhost' => 'simsearch'
                ],
                'queue' => [
                    'discoveredurls_queue' => 'discovered_urls',
                    'discovereddocuments_queue' => 'discovered_documents',
                    'indexer_queue' => 'indexer',
                    'recrawl_queue' => 'recrawl',
                ],
                'solr_client' => 'default',
                'minimal_document_save_amount' => 50,
                'pdf_to_txt_command' => '/usr/share/sbin/pdftotxt',
                'minimal_revisit_factor' => 10,
                'maximum_revisit_factor' => 1000,
                'default_revisit_factor' => 400,
                'mapping' => [
                    'id' => 'id',
                    'url' => 'url',
                    'content' => 'content',
                    'title' => 'title',
                    'revisit_after' => 'revisit_after',
                    'revisit_expiration' => 'revisit_expiration',
                ]
            ]
        ];

        $extension->load($config, $container);
    }
}
