<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver;

use VDB\Spider\Resource;
use DateTime;

/**
 * Extract data from given resource
 */
class DocumentDataExtractor
{
    private $resource;

    /**
     * @param \VDB\Spider\Resource $resource
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Get Id
     * @return string
     */
    public function getId()
    {
        return sha1($this->getUrl());
    }

    /**
     * Get Title
     * @return string
     */
    public function getTitle()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//title');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->resource->getUri()->toString();
    }

    /**
     * Get list of content types
     *
     * @return array
     */
    public function getType()
    {
        $contentType = explode(';', $this->resource->getResponse()->getContentType());
        $type = [];
        if (array_key_exists(0, $contentType)) {
            $contentType = array_slice($contentType, 0, 1);
            $type = array_merge($contentType, explode('/', $contentType[0]));
        }

        //remove duplicates and empty values
        return array_filter($type);
    }

    /**
     * @return string
     */
    public function getLastModified()
    {
        try {
            $lastModifiedDateTime = new DateTime($this->resource->getResponse()->getLastModified());
        } catch(Exception $e) {
            $lastModifiedDateTime = new DateTime();
        }

        return $lastModifiedDateTime->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="author"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getDescription()
    {

        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="description"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getKeywords()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="keywords"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getSimArchief()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM_archief"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return 'no';
    }

    /**
     * @return array
     */
    public function getSimfaq()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM.simfaq"]/@content');
        if ($nodeList->count() === 1) {
            return [$nodeList->text()];
        }

        return ['no'];
    }

    /**
     * @return string
     */
    public function getDctermsModified()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="DCTERMS.modified"]/@content');
        if ($nodeList->count() === 1) {
            $dctermsModifiedDateTime = new DateTime($nodeList->text());
            return $dctermsModifiedDateTime->format('Y-m-d\TH:i:s\Z');
        }

        return date('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return string
     */
    public function getDctermsIdentifier()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="DCTERMS.identifier"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return $this->getUrl();
    }

    /**
     * @return string
     */
    public function getDctermsTitle()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="DCTERMS.title"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return $this->getTitle();
    }

    /**
     * @return string
     */
    public function getDctermsAvailable()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="DCTERMS.available"]/@content');
        if ($nodeList->count() === 1) {
            $dctermsModifiedDateTime = new DateTime($nodeList->text());
            return $dctermsModifiedDateTime->format('Y-m-d\TH:i:s\Z');
        }

        return date('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return string
     */
    public function getDctermsLanguage()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="DCTERMS.language"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return 'nl-NL';
    }

    /**
     * @return string
     */
    public function getDctermsType()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="DCTERMS.type"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return 'webpagina';
    }

    /**
     * @return string
     */
    public function getSimItemTrefwoorden()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM.item_trefwoorden"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getSimSimloketSynoniemen()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM.simloket_synoniemen"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }

        return '';
    }

    /**
     * @return string
     */
    public function getDctermsSpatial()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM.spatial"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }
        return '';
    }

    /**
     * @return string
     */
    public function getDctermsAudience()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM.audience"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }
        return '';
    }

    /**
     * @return string
     */
    public function getDctermsSubject()
    {
        $nodeList = $this->resource->getCrawler()->filterXpath('//head/meta[@name="SIM.subject"]/@content');
        if ($nodeList->count() === 1) {
            return $nodeList->text();
        }
        return '';
    }

}
