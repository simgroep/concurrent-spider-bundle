<?php

namespace Simgroep\ConcurrentSpiderBundle\Tests;

use PHPUnit_Framework_TestCase;
use Simgroep\ConcurrentSpiderBundle\UrlCheck;

class UrlCheckTest extends PHPUnit_Framework_TestCase
{
    public function testFixUrl() {
        $this->assertEquals("http://example.com/Some%20Thing/", UrlCheck::fixUrl("http://example.com/Some Thing"));
        $this->assertEquals("http://example.com/Some%20Thing/", UrlCheck::fixUrl("http://example.com/Some%20Thing"));
        $this->assertEquals("http://example.com/SomeThing/", UrlCheck::fixUrl("http://example.com/SomeThing/"));
        $this->assertEquals("http://example.com/SomeThing/", UrlCheck::fixUrl("http://example.com/SomeThing"));
        $this->assertEquals("http://example.com/Some%20Thing/", UrlCheck::fixUrl("http://example.com/Some Thing/"));
        $this->assertEquals("http://example.com/Some%20Thing.html", UrlCheck::fixUrl("http://example.com/Some Thing.html"));
    }
    public function testIsUrlBlacklisted() {
        $blacklist = [
            '^(file|ftp|mailto):',
            '&type\=rubriek',
            'http://example.com/Some%20Thing/'
        ];

        $this->assertFalse(UrlCheck::isUrlBlacklisted("http://example.com/Some%20Thing", $blacklist));
        $this->assertTrue(UrlCheck::isUrlBlacklisted("http://example.com/Some%20Thing/", $blacklist));
        $this->assertTrue(UrlCheck::isUrlBlacklisted("mailto:admin@example.com", $blacklist));
        $this->assertTrue(UrlCheck::isUrlBlacklisted("http://example.com/article?param1=6&type=rubriek", $blacklist));
        $this->assertFalse(UrlCheck::isUrlBlacklisted("http://example.com", $blacklist));
    }

    public function testIsUrlWhitelisted () {
        $whitelist = [
            "^http://example.com",
            "^https://facebook.com/user1"
        ];

        $this->assertFalse(UrlCheck::isUrlWhitelisted("http://example.com/Some%20Thing", []));
        $this->assertTrue(UrlCheck::isUrlWhitelisted("http://example.com", $whitelist));
        $this->assertTrue(UrlCheck::isUrlWhitelisted("http://example.com/Some%20Thing", $whitelist));
        $this->assertFalse(UrlCheck::isUrlWhitelisted("http://facebook.com/user1", $whitelist));
        $this->assertTrue(UrlCheck::isUrlWhitelisted("https://facebook.com/user1", $whitelist));
        $this->assertFalse(UrlCheck::isUrlWhitelisted("https://facebook.com/user3", $whitelist));
        $this->assertTrue(UrlCheck::isUrlWhitelisted("https://facebook.com/user1/details", $whitelist));
        $this->assertFalse(UrlCheck::isUrlWhitelisted("http://github.com", $whitelist));
    }

    public function testAreHostsEqual () {
        $this->assertTrue(UrlCheck::areHostsEqual("http://example.com/Some%20Thing", "http://example.com/item/article1"));
        $this->assertFalse(UrlCheck::areHostsEqual("http://example.com/Some%20Thing", "http://github.com/Some%20Thing"));
        $this->assertTrue(UrlCheck::areHostsEqual("https://example.com/Some%20Thing", "http://example.com/Some%20Thing"));
        $this->assertFalse(UrlCheck::areHostsEqual("https://example.com/Some%20Thing", null));
    }

    public function testIsAllowedToCrawl () {
        $whitelist = [
            "^http://example.com",
            "^https://facebook.com/user1"
        ];
        $blacklist = [
            '^(file|ftp|mailto):',
            '&type\=rubriek',
            'http://example.com/Some%20Thing/'
        ];
        $this->assertTrue(UrlCheck::isAllowedToCrawl("http://example.com/Some%20Thing", "http://example.com/item/article1", $blacklist, $whitelist));
        $this->assertFalse(UrlCheck::isAllowedToCrawl("http://example.com/Some%20Thing/article1", "http://example.com/item/article1", $blacklist, $whitelist));
        $this->assertTrue(UrlCheck::isAllowedToCrawl("https://facebook.com/user1", "http://example.com/item/article1", $blacklist, $whitelist));
    }
}
