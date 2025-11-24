<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use Psr\Log\LoggerInterface;

/**
 * Plain Text Document Parser
 * 
 * Native PHP parser for plain text files
 * Supports: .txt, .text files and other text formats
 */
class PlainTextDocumentParser implements DocumentParserInterface
{
    private const SUPPORTED_MIME_TYPES = [
        'text/plain',
        'text/html', // Can be read as text
        'application/xml',
        'text/xml',
        'application/json',
        'text/csv', // Fallback if Spreadsheet doesn't handle it
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function extractText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File not readable: {$filePath}");
        }

        $text = file_get_contents($filePath);

        if ($text === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
            
            $this->logger->debug('Text encoding converted', [
                'file' => basename($filePath),
                'from' => $encoding,
                'to' => 'UTF-8',
            ]);
        }

        $this->logger->info('Plain text extraction successful', [
            'file' => basename($filePath),
            'text_length' => strlen($text),
            'encoding' => $encoding ?: 'UTF-8',
        ]);

        return $text;
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function getPriority(): int
    {
        // Very high priority for plain text (simple format)
        // But lower than CSV/Spreadsheet parsers for structured data
        return 120;
    }

    public function getName(): string
    {
        return 'Plain Text Parser';
    }
}

