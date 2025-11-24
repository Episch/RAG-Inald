<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use Psr\Log\LoggerInterface;

/**
 * Markdown Document Parser
 * 
 * Native PHP parser for Markdown files
 * Supports: .md, .markdown files
 */
class MarkdownDocumentParser implements DocumentParserInterface
{
    private const SUPPORTED_MIME_TYPES = [
        'text/markdown',
        'text/x-markdown',
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

        // Optional: Strip Markdown syntax for cleaner text extraction
        $cleanText = $this->stripMarkdownSyntax($text);

        $this->logger->info('Markdown extraction successful', [
            'file' => basename($filePath),
            'text_length' => strlen($cleanText),
            'original_length' => strlen($text),
        ]);

        return $cleanText;
    }

    /**
     * Strip Markdown syntax to get plain text
     */
    private function stripMarkdownSyntax(string $markdown): string
    {
        // Remove code blocks
        $text = preg_replace('/```[\s\S]*?```/m', '', $markdown);
        $text = preg_replace('/`[^`]+`/m', '', $text);

        // Remove headers (keep text)
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '$1', $text);

        // Remove bold/italic
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text);
        $text = preg_replace('/__(.+?)__/s', '$1', $text);
        $text = preg_replace('/_(.+?)_/s', '$1', $text);

        // Remove links (keep text)
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);

        // Remove images
        $text = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '', $text);

        // Remove horizontal rules
        $text = preg_replace('/^[\*\-_]{3,}$/m', '', $text);

        // Remove list markers
        $text = preg_replace('/^\s*[\*\-\+]\s+/m', '', $text);
        $text = preg_replace('/^\s*\d+\.\s+/m', '', $text);

        // Remove blockquotes
        $text = preg_replace('/^>\s+/m', '', $text);

        // Clean up excessive whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function getPriority(): int
    {
        // Very high priority for Markdown (simple format)
        return 150;
    }

    public function getName(): string
    {
        return 'Markdown Native Parser';
    }
}

