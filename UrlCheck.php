<?php

namespace Simgroep\ConcurrentSpiderBundle;

class UrlCheck
{

    /**
     * Check if url form job is allowed to be crawled
     *
     * @param string $url
     * @param string $secondHost
     * @param array $blacklist
     * @param array $whitelist
     * @return boolean
     */
    public static function isAllowedToCrawl($url, $secondHost = "", array $blacklist = [], array $whitelist = [])
    {
        if (self::isUrlBlacklisted($url, $blacklist)) {
            return false;
        }

        if (self::areHostsEqual($url, $secondHost)) {
            return true;
        }

        if (!self::areHostsEqual($url, $secondHost) && self::isUrlWhiteListed($url, $whitelist)) {
            return true;
        }

        return false;
    }

    /**
     * Indicated wether the url of the crawljob is blacklisted.
     *
     * @param string $url
     * @param array $blacklist
     * @return boolean
     */
    public static function isUrlBlacklisted($url, array $blacklist = [])
    {
        $isBlacklisted = false;

        array_walk(
            $blacklist,
            function ($blacklistUrl) use ($url, &$isBlacklisted) {
                if (@preg_match('#' . $blacklistUrl . '#i', $url)) {
                    $isBlacklisted = true;
                }
            }
        );

        return $isBlacklisted;
    }

    /**
     * Check if url is whitelisted
     *
     * @param string $url
     * @param array $whitelist
     * @return boolean
     */
    public static function isUrlWhitelisted($url, array $whitelist = [])
    {
        if (count($whitelist) == 0) {
            return false;
        }

        $isWhitelisted = false;

        array_walk(
            $whitelist,
            function ($whitelistUrl) use ($url, &$isWhitelisted) {
                if (@preg_match('#' . $whitelistUrl . '#i', $url)) {
                    $isWhitelisted = true;
                }
            }
        );

        return $isWhitelisted;
    }

    /**
     * Indicates whether the hostname parts of url and base_url are equal.
     *
     * @param string $firstHost
     * @param string $secondHost
     * @return boolean
     */
    public static function areHostsEqual($firstHost, $secondHost)
    {
        $firstHost = parse_url($firstHost, PHP_URL_HOST);
        $secondHost = parse_url($secondHost, PHP_URL_HOST);

        if (is_null($firstHost) || is_null($secondHost)) {
            return false;
        }

        return ($firstHost === $secondHost);
    }

    /**
     * Changing url
     * @param string $url
     * @return string
     */
    public static function fixUrl ($url) {
        $url = str_replace(' ', '%20', $url);
        $url = preg_replace('#\/\??$#i', "", $url);

        if (!strpos($url, '?') && !strpos($url, '#') && !preg_match('#\.[a-z0-9]{2,5}$#i', $url)) {
            $url = rtrim($url, '/') . '/';
        }

        return $url;
    }
}
