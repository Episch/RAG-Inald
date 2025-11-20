<?php

declare(strict_types=1);

namespace App\Service\LLM;

use HelgeSverre\Toon\Toon;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ollama LLM Service with TOON Format Support
 * 
 * Uses TOON (Token-Oriented Object Notation) for efficient token usage
 * @see https://github.com/HelgeSverre/toon-php
 */
class OllamaLLMService
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly string $ollamaUrl,
        private readonly string $defaultModel,
        private readonly LoggerInterface $logger
    ) {
        $this->client = HttpClient::create([
            'timeout' => 120,
            'max_duration' => 300,
        ]);
    }

    /**
     * Generate text with TOON-formatted context
     * 
     * @param string $prompt The main prompt
     * @param array $context Context data (will be converted to TOON format)
     * @param array $options Generation options (model, temperature, etc.)
     * @return array LLM response with metadata
     */
    public function generate(string $prompt, array $context = [], array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;

        // Convert context to TOON format (50% token savings vs JSON!)
        $toonContext = !empty($context) ? $this->encodeToToon($context) : '';

        // Build complete prompt with TOON context
        $fullPrompt = $this->buildPromptWithContext($prompt, $toonContext);

        $startTime = microtime(true);

        try {
            $response = $this->client->request('POST', "{$this->ollamaUrl}/api/generate", [
                'json' => [
                    'model' => $model,
                    'prompt' => $fullPrompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => $temperature,
                        'num_predict' => $maxTokens,
                    ],
                ],
            ]);

            $data = $response->toArray();
            $duration = microtime(true) - $startTime;

            $this->logger->info('LLM generation completed', [
                'model' => $model,
                'prompt_length' => strlen($fullPrompt),
                'response_length' => strlen($data['response'] ?? ''),
                'duration_seconds' => round($duration, 3),
                'toon_context_size' => strlen($toonContext),
            ]);

            return [
                'response' => $data['response'] ?? '',
                'model' => $model,
                'duration_seconds' => $duration,
                'toon_context_size' => strlen($toonContext),
                'total_tokens' => $data['eval_count'] ?? null,
                'prompt_tokens' => $data['prompt_eval_count'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('LLM generation failed', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("LLM generation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Generate embeddings for text
     */
    public function generateEmbeddings(string $text, ?string $model = null): array
    {
        $model = $model ?? 'nomic-embed-text';

        try {
            $response = $this->client->request('POST', "{$this->ollamaUrl}/api/embeddings", [
                'json' => [
                    'model' => $model,
                    'prompt' => $text,
                ],
            ]);

            $data = $response->toArray();

            return [
                'embeddings' => $data['embedding'] ?? [],
                'model' => $model,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Embeddings generation failed', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Embeddings generation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * List available models
     */
    public function listModels(): array
    {
        try {
            $response = $this->client->request('GET', "{$this->ollamaUrl}/api/tags");
            $data = $response->toArray();

            return array_map(
                fn($model) => $model['name'],
                $data['models'] ?? []
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to list Ollama models', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->request('GET', "{$this->ollamaUrl}/api/version", [
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Encode data to TOON format
     * 
     * TOON achieves ~50% token savings compared to JSON
     */
    private function encodeToToon(array $data): string
    {
        return Toon::encode($data, indent: 2);
    }

    /**
     * Build prompt with TOON context
     */
    private function buildPromptWithContext(string $prompt, string $toonContext): string
    {
        if (empty($toonContext)) {
            return $prompt;
        }

        return <<<PROMPT
Context (TOON format):
```
{$toonContext}
```

{$prompt}
PROMPT;
    }

    /**
     * Parse LLM response as TOON format
     */
    public function parseToonResponse(string $response): array
    {
        // Extract TOON block from response
        if (preg_match('/```(?:toon)?\s*(.*?)```/s', $response, $matches)) {
            $toonContent = trim($matches[1]);
            
            try {
                return Toon::decode($toonContent);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse TOON response', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: try to parse entire response
        try {
            return Toon::decode($response);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse response as TOON', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

