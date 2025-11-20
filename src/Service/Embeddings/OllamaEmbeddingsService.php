<?php

declare(strict_types=1);

namespace App\Service\Embeddings;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
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
}

