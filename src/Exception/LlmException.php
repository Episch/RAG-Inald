<?php

namespace App\Exception;

use RuntimeException;

/**
 * Custom exception for LLM related errors
 */
class LlmException extends RuntimeException
{
    public static function modelNotAvailable(string $model): self
    {
        return new self("LLM model not available: {$model}");
    }

    public static function generationFailed(string $reason): self
    {
        return new self("LLM generation failed: {$reason}");
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("Invalid LLM response: {$reason}");
    }

    public static function connectionFailed(string $url): self
    {
        return new self("Failed to connect to LLM service: {$url}");
    }
}
