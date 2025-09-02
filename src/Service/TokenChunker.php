<?php

// src/Service/TokenChunker.php
namespace App\Service;

use Yethee\Tiktoken\EncoderProvider;

class TokenChunker
{
    private EncoderProvider $provider;
    private int $chunkSize;
    private int $overlap;

    public function __construct(int $chunkSize = 800, int $overlap = 100)
    {
        $this->provider = new EncoderProvider();
        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
    }


    /**
     * Count tokens in a text using tiktoken encoder
     * Falls back to gpt-3.5-turbo for unsupported models (e.g., Ollama models)
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
     * Map non-OpenAI models to compatible tiktoken models for token counting
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
     * Chunk text into smaller pieces with token-based chunking
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
