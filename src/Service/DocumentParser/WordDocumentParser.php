<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use PhpOffice\PhpWord\IOFactory;
use Psr\Log\LoggerInterface;

/**
 * Word Document Parser
 * 
 * Native PHP parser for Word files using PhpOffice/PhpWord
 * Supports: DOCX, DOC, ODT, RTF
 */
class WordDocumentParser implements DocumentParserInterface
{
    private const SUPPORTED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
        'application/msword', // DOC
        'application/vnd.oasis.opendocument.text', // ODT
        'application/rtf', // RTF
        'text/rtf', // RTF alternative
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function extractText(string $filePath): string
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $extractedText = [];

            // Iterate through all sections
            foreach ($phpWord->getSections() as $section) {
                // Extract text from section elements
                foreach ($section->getElements() as $element) {
                    $text = $this->extractElementText($element);
                    if (!empty($text)) {
                        $extractedText[] = $text;
                    }
                }
            }

            $text = implode("\n\n", $extractedText);

            $this->logger->info('Word document extraction successful', [
                'file' => basename($filePath),
                'sections_count' => count($phpWord->getSections()),
                'text_length' => strlen($text),
            ]);

            return $text;

        } catch (\Exception $e) {
            $this->logger->error('Word document extraction failed', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to extract text from Word document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract text from Word element recursively
     */
    private function extractElementText($element): string
    {
        $text = '';

        // Text elements
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
        }

        // TextRun elements (formatted text)
        if (method_exists($element, 'getElements')) {
            $childTexts = [];
            foreach ($element->getElements() as $childElement) {
                if (method_exists($childElement, 'getText')) {
                    $childTexts[] = $childElement->getText();
                }
            }
            $text = implode('', $childTexts);
        }

        // Table elements
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $tableTexts = [];
            foreach ($element->getRows() as $row) {
                $rowTexts = [];
                foreach ($row->getCells() as $cell) {
                    $cellTexts = [];
                    foreach ($cell->getElements() as $cellElement) {
                        $cellTexts[] = $this->extractElementText($cellElement);
                    }
                    $rowTexts[] = implode(' ', $cellTexts);
                }
                $tableTexts[] = implode(' | ', $rowTexts);
            }
            $text = implode("\n", $tableTexts);
        }

        return $text;
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function getPriority(): int
    {
        // Higher priority than Tika for Word documents
        return 100;
    }

    public function getName(): string
    {
        return 'PHPWord Native Parser';
    }
}

