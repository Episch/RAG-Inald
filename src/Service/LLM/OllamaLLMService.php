<?php

declare(strict_types=1);

namespace App\Service\LLM;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ollama LLM Service
 * 
 * Provides integration with Ollama for LLM text generation
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
     * Generate text with JSON-formatted context
     * 
     * @param string $prompt The main prompt
     * @param array $context Context data (will be converted to JSON format)
     * @param array $options Generation options (model, temperature, etc.)
     * @return array LLM response with metadata
     */
    public function generate(string $prompt, array $context = [], array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 32768;  // Very high for complete extraction (depends on model capacity)

        // Convert context to JSON format
        $jsonContext = !empty($context) ? json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';

        // Build complete prompt with JSON context
        $fullPrompt = $this->buildPromptWithContext($prompt, $jsonContext);

        $startTime = microtime(true);

        try {
            $response = $this->client->request('POST', "{$this->ollamaUrl}/api/generate", [
                'json' => [
                    'model' => $model,
                    'prompt' => $fullPrompt,
                    'stream' => false,
                    'format' => 'json',  // Force JSON output (Ollama 0.5+)
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
                'json_context_size' => strlen($jsonContext),
            ]);

            return [
                'response' => $data['response'] ?? '',
                'model' => $model,
                'duration_seconds' => $duration,
                'json_context_size' => strlen($jsonContext),
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
     * Build prompt with JSON context
     */
    private function buildPromptWithContext(string $prompt, string $jsonContext): string
    {
        if (empty($jsonContext)) {
            return $prompt;
        }

        return <<<PROMPT
Context (JSON format):
```json
{$jsonContext}
```

{$prompt}
PROMPT;
    }

}

