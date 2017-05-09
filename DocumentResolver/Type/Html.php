<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\TypeAbstract;
use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentDataExtractor;
use Symfony\Component\DomCrawler\Crawler;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;

/**
 * Html Resolver Document Type
 */
class Html extends TypeAbstract implements DocumentTypeInterface
{
    /**
     * @var string
     */
    private $cssBlacklist;

    /**
     * @param string $cssBlacklist
     */
    public function __construct($cssBlacklist)
    {
        $this->cssBlacklist = $cssBlacklist;
    }

    /**
     * Extracts content from a webpage and returns document data.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return array
     *
     * @throws \Simgroep\ConcurrentSpiderBundle\InvalidContentException
     */
    public function getData(Resource $resource)
    {
        $content = $this->extractContentFromResource($resource);

        $dataExtractor = new DocumentDataExtractor($resource);

        $data = [
            'id' => $dataExtractor->getId(),
            'url' => $dataExtractor->getUrl(),
            'content' => $content,
            'title' => $dataExtractor->getTitle(),
            'tstamp' => date('Y-m-d\TH:i:s\Z'),
            'type' => $dataExtractor->getType(),
            'contentLength' => strlen($content),
            'lastModified' => $dataExtractor->getLastModified(),
            'date' => date('Y-m-d\TH:i:s\Z'),
            'lang' => 'nl-NL',
            'author' => $dataExtractor->getAuthor(),
            'publishedDate' => date('Y-m-d\TH:i:s\Z'),
            'updatedDate' => date('Y-m-d\TH:i:s\Z'),
            'strippedContent' => strip_tags($content),
            'description' => $dataExtractor->getDescription(),
            'keywords' => $dataExtractor->getKeywords(),
        ];

        return $data;
    }

    /**
     * Extracts all text content from the crawled resource exception javascript and style.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    public function extractContentFromResource(Resource $resource)
    {
        $crawler = $resource->getCrawler();

        // Check if script block contains div tags
        $content = $resource->getResponse()->getBody(true);
        if (preg_match('/["|\']<div(.*?)<\/div>["|\']/is', $content)) {
            $content = preg_replace('/<script(.*?)<\/script>/is', '', $content);
            $crawler->clear();
            $crawler->addContent($content);
        }

        if (null !== $this->cssBlacklist) {
            $crawler->filter($this->cssBlacklist)->each(function (Crawler $crawler) {
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
            });
        }

        $query = '//body//*[not(self::script|self::style)]/text()';
        $content = '';
        $crawler->filterXpath($query)->each(
            function (Crawler $crawler) use (&$content) {
                $text = trim($crawler->text());
                if (strlen($text) > 0) {
                    $content .= $text . ' ';
                }
            }
        );

        return trim($content);
    }
}
