<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Interface for document parsers
 * 
 * Each parser implementation handles a specific document format
 */
interface DocumentParserInterface
{
    /**
     * Extract text content from a document
     * 
     * @param string $filePath Path to the document file
     * @return string Extracted text content
     * @throws \RuntimeException If extraction fails
     */
    public function extractText(string $filePath): string;

    /**
     * Check if this parser supports the given MIME type
     * 
     * @param string $mimeType MIME type to check
     * @return bool True if supported, false otherwise
     */
    public function supports(string $mimeType): bool;

    /**
     * Get the priority of this parser (higher = preferred)
     * 
     * @return int Priority value (default: 100)
     */
    public function getPriority(): int;

    /**
     * Get the name of this parser
     * 
     * @return string Parser name for logging
     */
    public function getName(): string;
}

