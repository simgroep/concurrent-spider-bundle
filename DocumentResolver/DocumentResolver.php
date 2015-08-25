<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver;

use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc as MsDocType;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt;

/**
 * Determine and extract document content from resource
 */
class DocumentResolver
{
    /**
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html
     */
    private $html;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf
     */
    private $pdf;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc
     */
    private $msdoc;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007
     */
    private $word2007;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf
     */
    private $rtf;

    /**
     * @var \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt
     */
    private $odt;

    /**
     * @var string
     */
    private $data;

    /**
     * Cosntructor
     *
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html $html
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf $pdf
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc $msdoc
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007 $msdoc
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf $rtf
     * @param \Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt $odt
     */
    public function __construct(Html $html, Pdf $pdf, MsDocType $msdoc, Word2007 $word2007, Rtf $rtf, Odt $odt)
    {
        $this->html = $html;
        $this->pdf = $pdf;
        $this->msdoc = $msdoc;
        $this->word2007 = $word2007;
        $this->rtf = $rtf;
        $this->odt = $odt;
    }

    /**
     * Determine docuemnt type with mime type from resource
     *
     * @param VDB\Spider\Resource $resource
     */
    public function resolveTypeFromResource(Resource $resource)
    {
        $this->data = '';

        switch ($resource->getResponse()->getContentType()) {
            case 'application/pdf':
            case 'application/octet-stream' :
                $this->data = $this->pdf->getData($resource);
                break;

            case 'application/msword' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template' :
                if (false !== stripos($resource->getUri()->toString(), '.docx')) {
                    $this->data = $this->word2007->getData($resource);
                    break;
                }
                $this->data = $this->msdoc->getData($resource);
                break;

            case 'application/rtf' :
                $this->data = $this->rtf->getData($resource);
                break;

            case 'application/vnd.oasis.opendocument.text' :
                $this->data = $this->odt->getData($resource);
                break;

            case 'text/html':
            default:
                $this->data = $this->html->getData($resource);
                break;
        }
    }

    /**
     * Data extracted from resource
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

}
