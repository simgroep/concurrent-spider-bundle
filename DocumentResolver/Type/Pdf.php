<?php

namespace Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type;

use Simgroep\ConcurrentSpiderBundle\DocumentResolver\Type\TypeAbstract;
use VDB\Spider\Resource;
use Simgroep\ConcurrentSpiderBundle\DocumentResolver\DocumentDataExtractor;
use Simgroep\ConcurrentSpiderBundle\InvalidContentException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Pdf Resolver Document Type
 */
class Pdf extends TypeAbstract implements DocumentTypeInterface
{
    /**
     * @var string
     */
    private $pdfToTxtCommand;


    /**
     * Constructor
     *
     * @param string $pdfToTxtCommand
     */
    public function __construct($pdfToTxtCommand)
    {
        $this->pdfToTxtCommand = $pdfToTxtCommand;
    }

    /**
     * Returns the filename of the resource. If not found it returns null.
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string|null
     */
    protected function getFileNameFromResource(Resource $resource)
    {
        $regex = '/.*filename=[\'\"]([^\'\"]+)/';
        $response = $resource->getResponse();

        if (!$response->hasHeader('Content-Disposition')) {
            return null;
        }

        if (preg_match($regex, $response->getContentDisposition(), $matches)) {
            return $matches['1'];
        }
    }

    /**
     * Extracts content from a PDF File and returns document data.
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
                sprintf("PDF didn't contain enough content (minimal chars is %s)", self::MINIMAL_CONTENT_LENGTH)
            );
        }

        $dataExtractor = new DocumentDataExtractor($resource);

        $url = $dataExtractor->getUrl();
        $title = $this->getFileNameFromResource($resource);

        if (null === $title) {
            $title = $this->getTitleByUrl($url) ? : '';
        }

        $data = [
            'id' => $dataExtractor->getId(),
            'url' => $url,
            'content' => $content,
            'strippedContent' => $content,
            'title' => $title,
            'tstamp' => date('Y-m-d\TH:i:s\Z'),
            'contentLength' => strlen($content),
            'lastModified' => $dataExtractor->getLastModified(),
            'date' => date('Y-m-d\TH:i:s\Z'),
            'publishedDate' => date('Y-m-d\TH:i:s\Z'),
            'type' => ['application/pdf'],
            'updatedDate' => date('Y-m-d\TH:i:s\Z'),
        ];

        return $data;
    }

    /**
     *
     * Extract content from resource
     *
     * @param \VDB\Spider\Resource $resource
     *
     * @return string
     * @throws InvalidContentException
     * @throws ProcessFailedException
     */
    public function extractContentFromResource(Resource $resource)
    {
        $tempPdfFile = $this->getTempFileName('pdf');

        file_put_contents($tempPdfFile, $resource->getResponse()->getBody());

        $command = sprintf('%s %s', $this->pdfToTxtCommand, $tempPdfFile);
        $process = $this->processCommand($command);

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            unlink($tempPdfFile);
            throw new ProcessFailedException($process);
        }
        //remove tempPdf file
        unlink($tempPdfFile);

        //read txt file created by pdftotext command
        $tempTxtFile = sprintf('%s.txt', $tempPdfFile);
        $content = file_get_contents($tempTxtFile);
        if ($content == false) {
            unlink($tempTxtFile);
            throw new InvalidContentException(
                sprintf("PDF: Cant read temporary txt file converted from pdf: %s", $tempTxtFile)
            );
        }
        //remove tempTxt file
        unlink($tempTxtFile);

        return $content;
    }

    /**
     * @param string $command
     *
     * @return Process
     */
    protected function processCommand($command)
    {
        $process = new Process($command);
        $process->run();
        return $process;
    }


}
