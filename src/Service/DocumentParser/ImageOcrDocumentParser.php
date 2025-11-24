<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use Psr\Log\LoggerInterface;

/**
 * Image OCR Document Parser
 * 
 * Extracts text from images using Tesseract OCR
 * Requires: tesseract binary installed on system
 * 
 * Optional parser - gracefully degrades if Tesseract not available
 */
class ImageOcrDocumentParser implements DocumentParserInterface
{
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/tiff',
        'image/bmp',
        'image/webp',
    ];

    private bool $tesseractAvailable;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->tesseractAvailable = $this->checkTesseractAvailable();
    }

    public function extractText(string $filePath): string
    {
        if (!$this->tesseractAvailable) {
            throw new \RuntimeException("Tesseract OCR is not available on this system. Install via: sudo apt-get install tesseract-ocr");
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        try {
            // Use Tesseract via command line
            $outputFile = tempnam(sys_get_temp_dir(), 'ocr_');
            
            // Run tesseract (outputs to file without extension, adds .txt automatically)
            $command = sprintf(
                'tesseract %s %s 2>&1',
                escapeshellarg($filePath),
                escapeshellarg($outputFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException("Tesseract failed: " . implode("\n", $output));
            }

            // Read extracted text (tesseract adds .txt extension)
            $textFile = $outputFile . '.txt';
            
            if (!file_exists($textFile)) {
                throw new \RuntimeException("Tesseract output file not found: {$textFile}");
            }

            $text = file_get_contents($textFile);

            // Cleanup
            @unlink($outputFile);
            @unlink($textFile);

            $this->logger->info('Image OCR extraction successful', [
                'file' => basename($filePath),
                'text_length' => strlen($text),
            ]);

            return $text;

        } catch (\Exception $e) {
            $this->logger->error('Image OCR extraction failed', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to extract text from image: {$e->getMessage()}", 0, $e);
        }
    }

    public function supports(string $mimeType): bool
    {
        // Only support if Tesseract is available
        return $this->tesseractAvailable && in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function getPriority(): int
    {
        // Lower priority than native parsers (OCR is slower and less accurate)
        return 50;
    }

    public function getName(): string
    {
        return 'Tesseract OCR Parser';
    }

    /**
     * Check if Tesseract is available on the system
     */
    private function checkTesseractAvailable(): bool
    {
        exec('which tesseract 2>/dev/null', $output, $returnCode);
        
        $available = $returnCode === 0;

        if ($available) {
            $this->logger->info('Tesseract OCR is available');
        } else {
            $this->logger->warning('Tesseract OCR is not available - image parsing disabled');
        }

        return $available;
    }
}

