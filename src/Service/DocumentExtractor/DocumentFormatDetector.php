<?php

declare(strict_types=1);

namespace App\Service\DocumentExtractor;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Document Format Detection Service
 * 
 * Uses Symfony MIME component + fileinfo extension for reliable format detection
 */
class DocumentFormatDetector
{
    private MimeTypes $mimeTypes;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->mimeTypes = new MimeTypes();
    }

    /**
     * Detect MIME type of a file
     * 
     * @param string $filePath Path to the file
     * @return string Detected MIME type
     * @throws \RuntimeException If detection fails
     */
    public function detectMimeType(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        // Primary: Symfony MIME (uses magic bytes)
        $mimeType = $this->mimeTypes->guessMimeType($filePath);

        if ($mimeType !== null) {
            $this->logger->debug('MIME type detected (Symfony)', [
                'file' => basename($filePath),
                'mime_type' => $mimeType,
                'method' => 'symfony/mime',
            ]);

            return $mimeType;
        }

        // Fallback: PHP fileinfo extension
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            if ($mimeType !== false) {
                $this->logger->debug('MIME type detected (fileinfo)', [
                    'file' => basename($filePath),
                    'mime_type' => $mimeType,
                    'method' => 'fileinfo',
                ]);

                return $mimeType;
            }
        }

        // Last resort: guess from extension
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $extensionMimes = $this->mimeTypes->getMimeTypes($extension);

        if (!empty($extensionMimes)) {
            $mimeType = $extensionMimes[0];

            $this->logger->warning('MIME type guessed from extension (unreliable)', [
                'file' => basename($filePath),
                'extension' => $extension,
                'mime_type' => $mimeType,
                'method' => 'extension',
            ]);

            return $mimeType;
        }

        // Give up
        throw new \RuntimeException("Could not detect MIME type for file: {$filePath}");
    }

    /**
     * Get human-readable format name
     * 
     * @param string $mimeType MIME type
     * @return string Format name
     */
    public function getFormatName(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'PDF Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Microsoft Word (DOCX)',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Microsoft Excel (XLSX)',
            'application/msword' => 'Microsoft Word (DOC)',
            'application/vnd.ms-excel' => 'Microsoft Excel (XLS)',
            'text/plain' => 'Plain Text',
            'text/markdown' => 'Markdown',
            'text/csv' => 'CSV',
            'text/html' => 'HTML',
            'application/rtf' => 'Rich Text Format',
            'application/xml', 'text/xml' => 'XML',
            'application/json' => 'JSON',
            default => $mimeType,
        };
    }
}

