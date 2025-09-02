<?php

namespace App\Exception;

use RuntimeException;

/**
 * Custom exception for document extraction related errors
 */
class DocumentExtractionException extends RuntimeException
{
    public static function pathNotFound(string $path): self
    {
        return new self("Document path not found: {$path}");
    }

    public static function fileNotFound(string $filename): self
    {
        return new self("Document file not found: {$filename}");
    }

    public static function extractionFailed(string $reason): self
    {
        return new self("Document extraction failed: {$reason}");
    }

    public static function invalidContent(string $reason): self
    {
        return new self("Invalid document content: {$reason}");
    }
}
