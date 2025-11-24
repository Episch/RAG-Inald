<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DocumentExtractor\TikaExtractorService;
use App\Service\LLM\OllamaLLMService;
use App\Service\Neo4j\Neo4jConnectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly ParameterBagInterface $params,
        #[Autowire(service: 'messenger.transport.async')]
        private readonly ?TransportInterface $asyncTransport = null
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
     * Check Messenger status by inspecting the actual transport
     */
    private function checkMessengerStatus(): array
    {
        try {
            if ($this->asyncTransport === null) {
                return [
                    'available' => false,
                    'transport' => 'not-configured',
                    'info' => 'Async transport not available',
                ];
            }

            // Determine transport type from the actual class
            $transportClass = get_class($this->asyncTransport);
            $transport = 'unknown';
            $info = 'Unknown Transport';

            // Check the actual transport class to determine type
            if (str_contains($transportClass, 'InMemory')) {
                $transport = 'in-memory';
                $info = 'In-Memory Queue (Development - not persistent)';
            } elseif (str_contains($transportClass, 'Redis')) {
                $transport = 'redis';
                $info = 'Redis Queue (Production-ready)';
            } elseif (str_contains($transportClass, 'Amqp')) {
                $transport = 'rabbitmq';
                $info = 'RabbitMQ Queue (Production-ready)';
            } elseif (str_contains($transportClass, 'Doctrine')) {
                $transport = 'doctrine';
                $info = 'Doctrine Database Queue';
            } elseif (str_contains($transportClass, 'Sync')) {
                $transport = 'sync';
                $info = 'Synchronous Processing (no queue)';
            } else {
                $transport = 'custom';
                $info = 'Custom Transport: ' . basename(str_replace('\\', '/', $transportClass));
            }

            return [
                'available' => true,
                'transport' => $transport,
                'info' => $info,
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'transport' => 'error',
                'info' => 'Failed to check messenger: ' . $e->getMessage(),
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

