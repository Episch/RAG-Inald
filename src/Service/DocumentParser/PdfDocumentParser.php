<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use Psr\Log\LoggerInterface;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * PDF Document Parser
 * 
 * Native PHP parser for PDF files using smalot/pdfparser
 */
class PdfDocumentParser implements DocumentParserInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function extractText(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            
            $text = $pdf->getText();
            
            // Get metadata
            $details = $pdf->getDetails();
            $pageCount = count($pdf->getPages());

            $this->logger->info('PDF extraction successful', [
                'file' => basename($filePath),
                'pages' => $pageCount,
                'text_length' => strlen($text),
                'title' => $details['Title'] ?? null,
                'author' => $details['Author'] ?? null,
            ]);

            return $text;

        } catch (\Exception $e) {
            $this->logger->error('PDF extraction failed', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to extract text from PDF: {$e->getMessage()}", 0, $e);
        }
    }

    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    public function getPriority(): int
    {
        // Higher priority than Tika for PDFs
        return 100;
    }

    public function getName(): string
    {
        return 'PDF Native Parser';
    }
}

