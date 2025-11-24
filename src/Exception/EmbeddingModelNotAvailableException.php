<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when an embedding model is not available in Ollama
 */
class EmbeddingModelNotAvailableException extends \RuntimeException
{
    public function __construct(
        string $modelName,
        array $availableModels = [],
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Embedding model '%s' is not available in Ollama. " .
            "Please install the model using: ollama pull %s. " .
            "Supported embedding models: nomic-embed-text, mxbai-embed-large, all-minilm. %s",
            $modelName,
            $modelName,
            empty($availableModels) 
                ? "No embedding models found in Ollama." 
                : "Available models: " . implode(', ', $availableModels) . "."
        );

        parent::__construct($message, 0, $previous);
    }
}

