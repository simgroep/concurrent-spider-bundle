#!/usr/bin/env bash

mkdir -p /home/vagrant/concurrent-spider

sudo echo "deb http://www.rabbitmq.com/debian/ testing main" >> /etc/apt/sources.list
wget https://www.rabbitmq.com/rabbitmq-signing-key-public.asc
sudo apt-key add rabbitmq-signing-key-public.asc

sudo apt-get update
sudo apt-get -y upgrade
sudo apt-get install -y php5-cli php5-curl git rabbitmq-server solr-tomcat

sudo cp /home/vagrant/concurrent-spider/schema.xml /etc/solr/conf/schema.xml
sudo service tomcat6 restart

sudo echo "[{rabbit, [{loopback_users, []}]}]." >> /etc/rabbitmq/rabbitmq.config
sudo service rabbitmq-server restart

# install Composer
curl -s https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
