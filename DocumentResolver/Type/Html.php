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

        if (strlen($content) < self::MINIMAL_CONTENT_LENGTH) {
            throw new InvalidContentException(
            sprintf("Webpage didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $dataExtractor = new DocumentDataExtractor($resource);

        $data = [
            'document' => [
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
                'collection' => ['Alles'],
                'description' => $dataExtractor->getDescription(),
                'keywords' => $dataExtractor->getKeywords(),
                'SIM_archief' => $dataExtractor->getSimArchief(),
                'SIM.simfaq' => $dataExtractor->getSimfaq(),
                'DCTERMS.modified' => $dataExtractor->getDctermsModified(),
                'DCTERMS.identifier' => $dataExtractor->getDctermsIdentifier(),
                'DCTERMS.title' => $dataExtractor->getDctermsTitle(),
                'DCTERMS.available' => $dataExtractor->getDctermsAvailable(),
                'DCTERMS.language' => $dataExtractor->getDctermsLanguage(),
                'DCTERMS.type' => $dataExtractor->getDctermsType(),
            ],
        ];

        $simItemTrefwoorden = $dataExtractor->getSimItemTrefwoorden();
        if (!empty($simItemTrefwoorden)) {
            $data['document']['SIM.item_trefwoorden'] = $simItemTrefwoorden;
        }

        $simSimloketSynoniemen = $dataExtractor->getSimSimloketSynoniemen();
        if (!empty($simSimloketSynoniemen)) {
            $data['document']['SIM.simloket_synoniemen'] = $simSimloketSynoniemen;
        }

        $spatial = $dataExtractor->getDctermsSpatial();
        if (!empty($spatial)) {
            $data['document']['DCTERMS.spatial'] = $spatial;
        }

        $audience = $dataExtractor->getDctermsAudience();
        if (!empty($audience)) {
            $data['document']['DCTERMS.audience'] = $audience;
        }

        $subject = $dataExtractor->getDctermsSubject();
        if(!empty($subject)) {
            $data['document']['DCTERMS.subject'] = $subject;
        }

        return $data;
    }

    /**
     * Extracts all text content from the crawled resource exception javascript.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     */
    public function extractContentFromResource(Resource $resource)
    {
        $crawler = $resource->getCrawler();

        if (null !== $this->cssBlacklist) {
            $crawler->filter($this->cssBlacklist)->each(function (Crawler $crawler) {
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
            });
        }

        $query = '//body//*[not(self::script)]/text()';
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
