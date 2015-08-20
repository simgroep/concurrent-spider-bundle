<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver;

use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDocx;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt;

/**
 * Description of DocumentResolver
 *
 * @author lkalinka
 */
class DocumentResolver
{
    /**
     * @var Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html
     */
    private $html;

    /**
     * @var Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf
     */
    private $pdf;

    /**
     * @var Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDocx
     */
    private $msdoc;

    /**
     * @var Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf
     */
    private $rtf;

    /**
     * @var Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt
     */
    private $odt;

    /**
     * @var string
     */
    private $data;

    public function __construct(Html $html, Pdf $pdf, MsDocx $msdoc, Rtf $rtf, Odt $odt)
    {
        $this->html = $html;
        $this->pdf = $pdf;
        $this->msdoc = $msdoc;
        $this->rtf = $rtf;
        $this->odt = $odt;
    }

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

    public function getData()
    {
        return $this->data;
    }

}
