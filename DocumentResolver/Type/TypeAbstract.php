<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

/**
 * Collection of abstract methods for handling types
 */
abstract class TypeAbstract
{
    const MINIMAL_CONTENT_LENGTH = 3;

    /**
     * Assumes that the path of the URL contains the title of the document and extracts it.
     *
     * @param string $url
     *
     * @return string
     */
    protected function getTitleByUrl($url)
    {
        $title = null;

        if (false !== stripos($url, '.doc') || false !== stripos($url, '.docx') || false !== stripos($url, '.odt') || false !== stripos($url, '.odf') || false !== stripos($url, '.pdf') || false !== stripos($url, '.rtf')) {
            $urlParts = parse_url($url);
            $title = basename($urlParts['path']);
        }

        return $title;
    }

    /**
     * Strip away binary content since it doesn't make sense to index it.
     *
     * @param string $content
     *
     * @return string
     */
    protected function stripBinaryContent($content)
    {
        return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', '', $content);
    }

}
