<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DocumentExtractor\TikaExtractorService;
use App\Service\LLM\OllamaLLMService;
use App\Service\Neo4j\Neo4jConnectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health Check Controller for Service Status
 */
class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly TikaExtractorService $tikaExtractor,
        private readonly OllamaLLMService $llmService,
        private readonly Neo4jConnectorService $neo4jConnector,
        private readonly ParameterBagInterface $params
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $tikaAvailable = $this->tikaExtractor->isAvailable();
        $ollamaAvailable = $this->llmService->isAvailable();
        $neo4jAvailable = $this->neo4jConnector->isAvailable();
        $messengerStatus = $this->checkMessengerStatus();

        $allHealthy = $tikaAvailable && $ollamaAvailable && $neo4jAvailable && $messengerStatus['available'];

        return $this->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'services' => [
                'tika' => [
                    'status' => $tikaAvailable ? 'up' : 'down',
                    'description' => 'Apache Tika Document Extractor',
                ],
                'ollama' => [
                    'status' => $ollamaAvailable ? 'up' : 'down',
                    'description' => 'Ollama LLM Service',
                ],
                'neo4j' => [
                    'status' => $neo4jAvailable ? 'up' : 'down',
                    'description' => 'Neo4j Graph Database',
                ],
                'messenger' => [
                    'status' => $messengerStatus['available'] ? 'up' : 'down',
                    'description' => 'Message Queue for Async Processing',
                    'transport' => $messengerStatus['transport'],
                    'info' => $messengerStatus['info'],
                ],
            ],
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Check Messenger status
     */
    private function checkMessengerStatus(): array
    {
        try {
            // Check if messenger is configured
            $messengerConfig = $this->params->get('kernel.bundles');
            
            // Determine transport type from environment
            $transport = 'in-memory';
            $info = 'In-Memory Queue (Development)';
            
            if (isset($_ENV['MESSENGER_TRANSPORT_DSN'])) {
                $dsn = $_ENV['MESSENGER_TRANSPORT_DSN'];
                
                if (str_starts_with($dsn, 'redis://')) {
                    $transport = 'redis';
                    $info = 'Redis Queue';
                } elseif (str_starts_with($dsn, 'amqp://')) {
                    $transport = 'rabbitmq';
                    $info = 'RabbitMQ Queue';
                } elseif (str_starts_with($dsn, 'doctrine://')) {
                    $transport = 'doctrine';
                    $info = 'Doctrine Database Queue';
                } else {
                    $transport = 'custom';
                    $info = 'Custom Transport';
                }
            }
            
            return [
                'available' => true,
                'transport' => $transport,
                'info' => $info,
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'transport' => 'unknown',
                'info' => 'Messenger not configured',
            ];
        }
    }

    #[Route('/api/models', name: 'api_models', methods: ['GET'])]
    public function models(): JsonResponse
    {
        try {
            $models = $this->llmService->listModels();

            return $this->json([
                'models' => $models,
                'default_model' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.2',
                'embedding_model' => $_ENV['OLLAMA_EMBEDDING_MODEL'] ?? 'nomic-embed-text',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to list models',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

