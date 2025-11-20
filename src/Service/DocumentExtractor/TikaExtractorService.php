<?php

declare(strict_types=1);

namespace App\Service\DocumentExtractor;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Apache Tika Document Extractor Service
 */
class TikaExtractorService
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly string $tikaUrl,
        private readonly LoggerInterface $logger
    ) {
        $this->client = HttpClient::create([
            'timeout' => 60,
            'max_duration' => 120,
        ]);
    }

    /**
     * Extract text from document
     * 
     * @throws \RuntimeException
     */
    public function extractText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $startTime = microtime(true);

        try {
            $response = $this->client->request('PUT', "{$this->tikaUrl}/tika", [
                'headers' => [
                    'Accept' => 'text/plain',
                ],
                'body' => fopen($filePath, 'r'),
            ]);

            $text = $response->getContent();
            $duration = microtime(true) - $startTime;

            $this->logger->info('Tika extraction completed', [
                'file' => basename($filePath),
                'text_length' => strlen($text),
                'duration_seconds' => round($duration, 3),
            ]);

            return $text;
        } catch (\Exception $e) {
            $this->logger->error('Tika extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to extract text from document: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract metadata from document
     */
    public function extractMetadata(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        try {
            $response = $this->client->request('PUT', "{$this->tikaUrl}/meta", [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => fopen($filePath, 'r'),
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Tika metadata extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if Tika service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->request('GET', "{$this->tikaUrl}/version", [
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}

