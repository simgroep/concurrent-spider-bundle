<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver;

use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Html;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Pdf;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\MsDoc as MsDocType;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Word2007;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Rtf;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\Odt;
use Simgroep\ConcurrentSpiderBundle\PersistableDocument;

/**
 * Determine and extract document content from resource.
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
     * Constructor.
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
     * Returns a document that can be persisted based on the resource.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return \Simgroep\ConcurrentSpiderBundle\PersistableDocument
     */
    public function getDocumentByResource(Resource $resource)
    {
        switch ($resource->getResponse()->getContentType()) {
            case 'application/pdf':
            case 'application/octet-stream' :
                $data = $this->pdf->getData($resource);
                break;

            case 'application/msword' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' :
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template' :
                if (false !== stripos($resource->getUri()->toString(), '.docx')) {
                    $data = $this->word2007->getData($resource);
                    break;
                }
                $data = $this->msdoc->getData($resource);
                break;

            case 'application/rtf' :
                $data = $this->rtf->getData($resource);
                break;

            case 'application/vnd.oasis.opendocument.text' :
                $data = $this->odt->getData($resource);
                break;

            case 'text/html':
            default:
                $data = $this->html->getData($resource);
                break;
        }

        return new PersistableDocument($data);
    }
}

