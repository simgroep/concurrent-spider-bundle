Concurrent Spider Bundle
========================

[![Build Status](https://travis-ci.org/simgroep/concurrent-spider-bundle.svg?branch=master)](http://travis-ci.org/simgroep/concurrent-spider-bundle)
[![Coverage Status](https://coveralls.io/repos/simgroep/concurrent-spider-bundle/badge.svg?branch=master)](https://coveralls.io/r/simgroep/concurrent-spider-bundle?branch=master)

This bundle provides a set of commands to run a distributed web page crawler. Crawled web pages are saved to Solr.

### Installation

Install it with Composer:

    composer require simgroep/concurrent-spider-bundle dev-master

Then add it to your `AppKernel.php`

    new Simgroep\ConcurrentSpiderBundle\SimgroepConcurrentSpiderBundle(),

It is needed to install http://www.foolabs.com/xpdf/ - only pdftotext is realy to be functional from command line:

    /path_to_command/pdftotext pdffile.pdf


### Configuration

Minimal configuration is necessary. The crawler needs to know the mapping you're using in Solr so it can save documents. The only mandatory part of the config is "mapping". Other values are optional:

    simgroep_concurrent_spider:
        http_user_agent: "PHP Concurrent Spider"

        rabbitmq.host: localhost
        rabbitmq.port: 5672
        rabbitmq.user: guest
        rabbitmq.password: guest

        queue.discoveredurls_queue: discovered_urls
        queue.indexer_queue: indexer

        solr.host: localhost
        solr.port: 8080
        solr.path: /solr

        mapping:
            id: #required
            title: #required
            content: #required
            url: #required
            tstamp: ~
            date: ~
            publishedDate: ~

### How does it work?

You start the crawler with:

    app/console simgroep:start-crawler https://github.com

This will add one job to the queue to crawl the url https://github.com. Then run the following process in background to start crawling:

    app/console simgroep:crawl

It's recommended to use a tool to maintain the crawler process in background. We recommend Supervisord. You can run as many as threads as you like (and your machine can handle), but you should be careful to not flood the website. Every thread acts as a visitor on the website you're crawling.

### Architecture

This bundle uses RabbitMQ to keep track of a queue that has URLs that should be indexed. Also it uses Solr to save the crawled web pages.
