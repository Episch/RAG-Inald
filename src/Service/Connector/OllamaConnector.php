<?php
// src/Service/Connector/OllamaConnector.php
namespace App\Service\Connector;

use App\Service\HttpClientService;
use App\Service\TokenChunker;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class OllamaConnector
{
    private HttpClientService $httpClient;
    private LoggerInterface $logger;
    private string $ollamaBaseUrl;
    private LockFactory $lockFactory;
    private TokenChunker $chunker;

    public function __construct(
        HttpClientService $httpClient,
        LoggerInterface $logger,
        TokenChunker $chunker
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->chunker = $chunker;
        $this->ollamaBaseUrl = rtrim($_ENV['LMM_URL'], '/');

        $store = new FlockStore(sys_get_temp_dir());
        $this->lockFactory = new LockFactory($store);
    }

    public function getStatus(): ResponseInterface
    {
        return $this->httpClient->get($this->ollamaBaseUrl . '/api/version');
    }

    public function promptForCategorization(string $prompt, string $model = 'mistral', bool $stream = false): string
    {
        $lock = $this->lockFactory->createLock('ollama_api_lock', 30);

        $finalOutput = '';

        if (!$lock->acquire()) {
            throw new \RuntimeException('Cannot acquire lock for LLM request');
        }

        try {
            $chunks = $this->chunker->chunk($prompt, $model);

            foreach ($chunks as $chunk) {
                $payload = [
                    'model' => $model,
                    'prompt' => $chunk,
                    'stream' => $stream,
                ];

                $this->logger->info('LLM Request', [
                    'url' => $this->ollamaBaseUrl . '/api/generate',
                    'payload' => $payload,
                ]);

                $response = $this->httpClient->post(
                    $this->ollamaBaseUrl . '/api/generate',
                    [
                        'body' => json_encode($payload),
                        'headers' => ['Content-Type' => 'application/json'],
                    ]
                );

                $content = $response->getContent();
                if (is_array($content)) {
                    $content = implode("\n\n", array_map(
                        fn($c) => is_array($c) ? json_encode($c) : $c,
                        $content
                    ));
                }

                $finalOutput .= $content;
            }
        } finally {
            $lock->release();
        }

        return $finalOutput;
    }
}
