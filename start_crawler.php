<?php
include_once('vendor/autoload.php');

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

if (strlen($argv[1]) == 0) {
    echo('You have to pass at least the base url that should be crawled');

    exit(1);
}

$baseUrl = $argv[1];
$connection = new AMQPConnection(
    'localhost',
    '5672',
    'guest',
    'guest'
);

$channel = $connection->channel();
$channel->queue_declare('discovered_urls', false, true, false, false, true);
$channel->basic_qos(null, 1, null);

$data = json_encode(array('uri' => $baseUrl, 'base_url' => $baseUrl, 'force' => true));
$message = new AMQPMessage($data, array('delivery_mode' => 2));

$channel->basic_publish($message, '', 'discovered_urls');
$channel->close();
$connection->close();
