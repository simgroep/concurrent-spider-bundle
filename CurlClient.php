<?php

namespace Simgroep\ConcurrentSpiderBundle;

use VDB\Uri\Uri;

class CurlClient
{
    /**
     * @var resource
     */
    protected $ch;

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @var string
     */
    protected $curlCertCADirectory;

    /**
     * @param string $userAgent
     * @param string $curlCertCADirectory
     */
    public function __construct($userAgent, $curlCertCADirectory)
    {
        $this->userAgent = $userAgent;
        $this->curlCertCADirectory = $curlCertCADirectory;
    }

    public function initClient()
    {
        $this->ch = curl_init();
        $this->setDefaultOptions($this->userAgent, $this->curlCertCADirectory);
    }

    /**
     * @param $userAgent
     * @param $curlCertCADirectory
     */
    protected function setDefaultOptions($userAgent, $curlCertCADirectory)
    {
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($this->ch, CURLOPT_NOBODY, true);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        curl_setopt($this->ch, CURLOPT_USERAGENT, $userAgent);

        if (!empty($curlCertCADirectory)) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($this->ch, CURLOPT_CAPATH, $curlCertCADirectory);
        }
    }

    /**
     * @param Uri $uri
     * @return bool
     * @throws \Exception
     */
    public function isDocument(Uri $uri)
    {
        curl_setopt($this->ch, CURLOPT_URL, $uri->toString());
        $optionsResource = curl_exec($this->ch);

        if ($this->getStatusCode() == 301) {
            $redirectUrl = $this->getRedirectUrl();
            curl_close($this->ch);
            throw new \Exception(sprintf(
                'Page moved to %s',
                $redirectUrl
            ));
        }
        $contentType = $this->getContentType();

        curl_close($this->ch);

        return $this->checkConntentType($contentType);
    }

    /**
     * @return mixed
     */
    protected function getStatusCode()
    {
        return curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
    }

    /**
     * @return mixed
     */
    protected function getContentType()
    {
        return curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
    }

    /**
     * @return mixed
     */
    protected function getRedirectUrl()
    {
        return curl_getinfo($this->ch, CURLINFO_REDIRECT_URL);
    }

    /**
     * @param mixed $contentType
     * @return bool
     */
    protected function checkConntentType($contentType)
    {
        $contentType = explode(';', $contentType);

        switch ($contentType[0]) {
            case 'application/pdf':
            case 'application/octet-stream' :
            case 'application/msword' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template' :
            case 'application/rtf' :
            case 'application/vnd.oasis.opendocument.text' :
                return true;
        }

        return false;
    }
}