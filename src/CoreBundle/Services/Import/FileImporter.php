<?php

namespace Runalyze\Bundle\CoreBundle\Services\Import;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Runalyze\Import\Exception\ParserException;
use Runalyze\Import\Exception\UnsupportedFileException;
use Runalyze\Parser\Activity\Common\Data\ActivityDataContainer;
use Runalyze\Parser\Activity\Common\ParserInterface;
use Runalyze\Parser\Activity\Converter\FitConverter;
use Runalyze\Parser\Activity\Converter\KmzConverter;
use Runalyze\Parser\Activity\Converter\TTbinConverter;
use Runalyze\Parser\Activity\Converter\ZipConverter;
use Runalyze\Parser\Activity\FileExtensionToParserMapping;
use Runalyze\Parser\Common\FileContentAwareParserInterface;
use Runalyze\Parser\Common\FileNameAwareParserInterface;
use Runalyze\Parser\Common\FileTypeConverterInterface;
use Symfony\Component\Filesystem\Filesystem;

class FileImporter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var \Runalyze\Parser\Common\FileTypeConverterInterface[] */
    protected $Converter = [];

    /** @var ZipConverter */
    protected $ZipConverter;

    /** @var FileExtensionToParserMapping */
    protected $ParserMapping;

    /** @var Filesystem */
    protected $Filesystem;

    /** @var string|null */
    protected $DirectoryForFailedImports;

    /**
     * @param FitConverter $fitConverter
     * @param TTbinConverter $ttbinConverter
     * @param string|null $directoryForFailedImports
     */
    public function __construct(
        FitConverter $fitConverter,
        TTbinConverter $ttbinConverter,
        $directoryForFailedImports = null,
        LoggerInterface $logger = null
    )
    {
        $this->ParserMapping = new FileExtensionToParserMapping();
        $this->Converter = [$fitConverter, $ttbinConverter, new KmzConverter()];
        $this->ZipConverter = new ZipConverter($this->getSupportedFileExtensions());
        $this->Filesystem = new Filesystem();
        $this->DirectoryForFailedImports = $directoryForFailedImports;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @return array
     */
    public function getSupportedFileExtensions()
    {
        return array_merge(
            array_keys(FileExtensionToParserMapping::MAPPING),
            array_map(function (FileTypeConverterInterface $converter) {
                return $converter->getConvertibleFileExtension();
            }, $this->Converter),
            ['zip']
        );
    }

    /**
     * @param array $fileNames
     * @return FileImportResultCollection
     */
    public function importFiles(array $fileNames)
    {
        $results = new FileImportResultCollection();

        foreach ($fileNames as $fileName) {
            $results->merge($this->importSingleFile($fileName));
        }

        return $this->handleResults($results);
    }

    /**
     * @param string $fileName
     * @return FileImportResultCollection
     */
    public function importSingleFile($fileName)
    {
        $results = new FileImportResultCollection();
        $convertedFileNames = $this->convertFileNameIfRequired($fileName);

        foreach ($convertedFileNames as $convertedFileName) {
            $results->add($this->getFileImportResultFor($convertedFileName, $fileName));
        }

        return $this->handleResults($results);
    }

    /**
     * @param FileImportResultCollection $results
     * @return FileImportResultCollection
     */
    protected function handleResults(FileImportResultCollection $results)
    {
        // TODO: merge hrm + gpx

        $this->logFileImports($results);

        return $results;
    }

    /**
     * @param string $fileName
     * @param string|null $originalFileName
     * @return FileImportResult
     */
    protected function getFileImportResultFor($fileName, $originalFileName = null)
    {
        if (null === $originalFileName) {
            $originalFileName = $fileName;
        }

        try {
            return new FileImportResult(
                $this->parseSingleFile($fileName),
                $fileName,
                $originalFileName
            );
        } catch (ParserException $e) {
            return new FileImportResult([], $fileName, $originalFileName, $e);
        }
    }

    /**
     * @param string $fileName
     * @return string[] converted file names
     */
    protected function convertFileNameIfRequired($fileName)
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        if ('zip' == $extension) {
            $convertedFileNames = [];
            $zipFiles = $this->ZipConverter->convertFile($fileName);

            foreach ($zipFiles as $zipFile) {
                $convertedFileNames = array_merge($convertedFileNames, $this->convertFileNameIfRequired($zipFile));
                $this->Filesystem->remove($zipFile);
            }

            return $convertedFileNames;
        }

        foreach ($this->Converter as $converter) {
            if ($converter->getConvertibleFileExtension() == $extension) {
                $result = $converter->convertFile($fileName);

                return is_array($result) ? $result : [$result];
            }
        }

        return [$fileName];
    }

    /**
     * @param string $fileName
     * @return ActivityDataContainer[]
     *
     * @throws UnsupportedFileException
     */
    protected function parseSingleFile($fileName)
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $parserClass = $this->ParserMapping->getParserClassFor($extension);

        if (null === $parserClass) {
            throw new UnsupportedFileException();
        }

        /** @var ParserInterface $parser */
        $parser = new $parserClass;
        $this->letParserParseFile($parser, $fileName);

        $container = [];
        $numContainer = $parser->getNumberOfActivities();

        for ($i = 0; $i < $numContainer; ++$i) {
            $container[] = $parser->getActivityDataContainer($i);
        }

        return $container;
    }

    /**
     * @param ParserInterface $parser
     * @param string $fileName
     *
     * @throws ParserException
     */
    protected function letParserParseFile(ParserInterface $parser, $fileName)
    {
        if ($parser instanceof LoggerAwareInterface) {
            $parser->setLogger($this->logger);
        }

        if ($parser instanceof FileNameAwareParserInterface) {
            $parser->setFileName($fileName);
        } elseif ($parser instanceof FileContentAwareParserInterface) {
            $parser->setFileContent(file_get_contents($fileName));
        } else {
            throw new ParserException('Chosen parser has no method to set file name or content.');
        }

        $parser->parse();
    }

    protected function logFileImports(FileImportResultCollection $results)
    {
        foreach ($results as $result) {
            $this->logSingleFileImport($result);
        }
    }

    protected function logSingleFileImport(FileImportResult $result)
    {
        if ($result->isFailed()) {
            $this->logger->error(sprintf('File upload of %s failed.', $this->getFileNameForLog($result)), [
                'exception' => $result->getException()
            ]);

            if (null !== $this->DirectoryForFailedImports && '' != $this->DirectoryForFailedImports) {
                $this->Filesystem->rename($result->getOriginalFileName(), $this->DirectoryForFailedImports.'/'.pathinfo($result->getOriginalFileName(), PATHINFO_BASENAME), true);
            } else {
                $this->Filesystem->remove($result->getOriginalFileName());
            }
        } else {
            $this->logger->info(sprintf('Successfull file upload of %s.', $this->getFileNameForLog($result)));
            $this->Filesystem->remove($result->getOriginalFileName());
        }

        if ($result->getOriginalFileName() != $result->getFileName()) {
            $this->Filesystem->remove($result->getFileName());
        }
    }

    /**
     * @param FileImportResult $result
     * @return string
     */
    protected function getFileNameForLog(FileImportResult $result)
    {
        if ($result->getOriginalFileName() != $result->getFileName()) {
            return sprintf('%s (original %s)', pathinfo($result->getFileName(), PATHINFO_BASENAME), pathinfo($result->getOriginalFileName(), PATHINFO_BASENAME));
        }

        return pathinfo($result->getFileName(), PATHINFO_BASENAME);
    }
}
