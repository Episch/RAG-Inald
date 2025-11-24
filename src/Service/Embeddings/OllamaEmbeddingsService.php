<?php

declare(strict_types=1);

namespace App\Service\Embeddings;

use App\Exception\EmbeddingModelNotAvailableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ollama Embeddings Service
 * 
 * Supports models like:
 * - nomic-embed-text (137M params, 768 dimensions)
 * - mxbai-embed-large (334M params, 1024 dimensions)
 * - all-minilm (23M params, 384 dimensions)
 */
class OllamaEmbeddingsService
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly string $ollamaUrl,
        private readonly string $defaultEmbeddingModel,
        private readonly LoggerInterface $logger
    ) {
        $this->client = HttpClient::create([
            'timeout' => 60,
            'max_duration' => 120,
        ]);
    }

    /**
     * Generate embeddings for text
     * 
     * @return float[] Vector embeddings
     * @throws EmbeddingModelNotAvailableException
     */
    public function embed(string $text, ?string $model = null): array
    {
        $model = $model ?? $this->defaultEmbeddingModel;
        $startTime = microtime(true);

        try {
            $response = $this->client->request('POST', "{$this->ollamaUrl}/api/embeddings", [
                'json' => [
                    'model' => $model,
                    'prompt' => $text,
                ],
            ]);

            $data = $response->toArray();
            $embeddings = $data['embedding'] ?? [];
            $duration = microtime(true) - $startTime;

            $this->logger->info('Embeddings generated', [
                'model' => $model,
                'text_length' => strlen($text),
                'dimensions' => count($embeddings),
                'duration_seconds' => round($duration, 3),
            ]);

            return $embeddings;
        } catch (ClientExceptionInterface $e) {
            // Check if it's a 404 - model not found
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                $availableModels = $this->getAvailableEmbeddingModels();
                
                $this->logger->error('Embedding model not available', [
                    'requested_model' => $model,
                    'available_models' => $availableModels,
                ]);

                throw new EmbeddingModelNotAvailableException($model, $availableModels, $e);
            }

            // Other HTTP errors
            $this->logger->error('Embeddings generation failed (HTTP error)', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to generate embeddings: {$e->getMessage()}", 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Embeddings generation failed', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to generate embeddings: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Generate embeddings for multiple texts (batch)
     * 
     * @param string[] $texts
     * @return array<int, float[]> Array of embeddings
     */
    public function embedBatch(array $texts, ?string $model = null): array
    {
        $embeddings = [];

        foreach ($texts as $index => $text) {
            $embeddings[$index] = $this->embed($text, $model);
        }

        return $embeddings;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            throw new \InvalidArgumentException('Vectors must have the same dimensions');
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] ** 2;
            $magnitudeB += $vectorB[$i] ** 2;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Get embedding dimensions for model
     */
    public function getDimensions(string $model): int
    {
        return match ($model) {
            'nomic-embed-text' => 768,
            'mxbai-embed-large' => 1024,
            'all-minilm' => 384,
            default => 768, // Default fallback
        };
    }

    /**
     * Get list of available embedding models from Ollama
     * 
     * @return string[]
     */
    private function getAvailableEmbeddingModels(): array
    {
        try {
            $response = $this->client->request('GET', "{$this->ollamaUrl}/api/tags");
            $data = $response->toArray();
            
            // Known embedding model patterns
            $embeddingPatterns = ['embed', 'embedding', 'minilm', 'mxbai'];
            
            $embeddingModels = [];
            foreach ($data['models'] ?? [] as $model) {
                $modelName = $model['name'] ?? '';
                
                // Check if it's an embedding model
                foreach ($embeddingPatterns as $pattern) {
                    if (stripos($modelName, $pattern) !== false) {
                        $embeddingModels[] = $modelName;
                        break;
                    }
                }
            }

            return $embeddingModels;
        } catch (\Exception $e) {
            $this->logger->warning('Could not fetch available models from Ollama', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Check if Ollama is running and responsive
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->request('GET', "{$this->ollamaUrl}/api/tags", [
                'timeout' => 2,
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}

