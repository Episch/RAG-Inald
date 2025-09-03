<?php

// src/Service/TokenChunker.php
namespace App\Service;

use App\Constants\SystemConstants;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Token-based text chunking service using tiktoken for accurate token counting.
 * 
 * Provides functionality to count tokens and split large text into manageable chunks
 * while maintaining context through overlap.
 */
class TokenChunker
{
    private EncoderProvider $provider;

    /**
     * Initialize chunker with configurable parameters.
     * 
     * @param int $chunkSize Maximum tokens per chunk
     * @param int $overlap Number of tokens to overlap between chunks
     */
    public function __construct(
        private readonly int $chunkSize = SystemConstants::TOKEN_CHUNK_SIZE,
        private readonly int $overlap = SystemConstants::TOKEN_CHUNK_OVERLAP
    ) {
        $this->provider = new EncoderProvider();
    }

    /**
     * Count tokens in a text using tiktoken encoder.
     * Falls back to gpt-3.5-turbo for unsupported models (e.g., Ollama models).
     * 
     * @param string $text Text to count tokens for
     * @param string $model Model name for token counting
     * 
     * @return int Actual token count
     */
    public function countTokens(string $text, string $model = 'gpt-3.5-turbo'): int
    {
        // Map unsupported models to compatible tiktoken models
        $tikTokenModel = $this->mapToTikTokenModel($model);
        
        try {
            $encoder = $this->provider->getForModel($tikTokenModel);
            $tokens = $encoder->encode($text);
            return count($tokens);
        } catch (\Exception $e) {
            // Ultimate fallback: use gpt-3.5-turbo if model mapping fails
            $encoder = $this->provider->getForModel('gpt-3.5-turbo');
            $tokens = $encoder->encode($text);
            return count($tokens);
        }
    }

    /**
     * Map non-OpenAI models to compatible tiktoken models for token counting.
     * 
     * @param string $model Original model name
     * 
     * @return string Compatible tiktoken model name
     */
    private function mapToTikTokenModel(string $model): string
    {
        $model = strtolower($model);
        
        // Ollama models mapping
        if (str_contains($model, 'llama') || str_contains($model, 'mistral') || str_contains($model, 'codellama')) {
            return 'gpt-3.5-turbo'; // Similar tokenization pattern
        }
        
        if (str_contains($model, 'phi') || str_contains($model, 'gemma')) {
            return 'gpt-3.5-turbo';
        }
        
        // OpenAI models (direct support)
        if (str_contains($model, 'gpt-4') || str_contains($model, 'gpt4')) {
            return 'gpt-4';
        }
        
        if (str_contains($model, 'gpt-3.5') || str_contains($model, 'gpt3.5')) {
            return 'gpt-3.5-turbo';
        }
        
        if (str_contains($model, 'text-davinci') || str_contains($model, 'davinci')) {
            return 'text-davinci-003';
        }
        
        // Default fallback
        return 'gpt-3.5-turbo';
    }

    /**
     * Chunk text into smaller pieces with token-based chunking.
     * 
     * @param string $text Text to chunk
     * @param string $model Model name for token counting
     * 
     * @return array Array of text chunks
     */
    public function chunk(string $text, string $model = 'gpt-3.5-turbo'): array
    {
        // Use the same model mapping logic for consistency
        $tikTokenModel = $this->mapToTikTokenModel($model);
        
        try {
            $encoder = $this->provider->getForModel($tikTokenModel);
        } catch (\Exception $e) {
            $encoder = $this->provider->getForModel('gpt-3.5-turbo');
        }
        
        $tokens = $encoder->encode($text);
        $chunks = [];

        $i = 0;
        $total = count($tokens);
        while ($i < $total) {
            $slice = array_slice($tokens, $i, $this->chunkSize);
            $chunks[] = $encoder->decode($slice);
            $i += $this->chunkSize - $this->overlap;
        }

        return $chunks;
    }
}