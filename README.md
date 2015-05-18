Concurrent Spider Bundle
========================

This bundle provides a set of commands to run a distributed webpage crawler. Crawled webpages are saved to SOLR.

### Instalation

Install it with Composer:

    composer require simgroep/concurrent-spider-bundle dev-master

Then add it to your `AppKernel.php`

    new Simgroep\ConcurrentSpiderBundle\SimgroepConcurrentSpiderBundle(),

### Configuration

No configuration is mandatory but the following configuration is optional:

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

### How does it works?

You start the crawler with:

    app/console simgroep:start-crawler https://github.com

This will add one job to the queue to crawl the url https://github.com. Then run the following process in background to start crawling:

    app/console simgroep:crawl

It's recommended to use a tool to maintain the crawler process in background. We recommend Supervisord. You can run as many as threas as you like (and your machine can handle) but you should be careful to not flood the website. Every thread can mean a concurrent visitor on the to be crawled website.

### Architecture

This bundle uses RabbitMQ to keep track of a queue that has url's that should be indexed. Also it uses SOLR to save the crawled webpages.
