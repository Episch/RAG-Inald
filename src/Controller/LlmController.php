<?php

namespace App\Controller;

use App\Dto\LlmPrompt;
use App\Dto\QueueResponse;
use App\Message\LlmMessage;
use App\Service\Connector\LlmConnector;
use App\Service\TokenChunker;
use App\Service\QueueStatsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class LlmController
{
    public function __construct(
        private MessageBusInterface $bus,
        private LlmConnector $llmConnector,
        private TokenChunker $tokenChunker,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private QueueStatsService $queueStats
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var LlmPrompt $data */
        $data = $this->serializer->deserialize($request->getContent(), LlmPrompt::class, 'json');
        
        // Generate unique request ID
        $requestId = uniqid('llm_', true);
        
        // Log the request
        $this->logger->info('LLM request received', [
            'request_id' => $requestId,
            'model' => $data->getModel(),
            'async' => $data->isAsync(),
            'prompt_length' => strlen($data->getPrompt()),
            'temperature' => $data->getTemperature(),
        ]);
        
        if ($data->isAsync()) {
            // Asynchronous processing via Message Queue
            return $this->handleAsyncRequest($data, $requestId);
        } else {
            // Synchronous processing (be careful with timeouts!)
            return $this->handleSyncRequest($data, $requestId);
        }
    }

    private function handleAsyncRequest(LlmPrompt $data, string $requestId): JsonResponse
    {
        // Dispatch message to queue
        $message = new LlmMessage(
            prompt: $data->getPrompt(),
            model: $data->getModel(),
            temperature: $data->getTemperature(),
            maxTokens: $data->getMaxTokens(),
            requestId: $requestId,
            type: 'generate'
        );
        
        $this->bus->dispatch($message);
        
        // Increment queue counter and get current count
        $this->queueStats->incrementQueueCounter();
        $queueCount = $this->queueStats->getQueueCounterValue();
        
        // Calculate actual token count instead of string length using the specified model
        $tokenCount = $this->tokenChunker->countTokens($data->getPrompt(), $data->getModel());
        
        // Create standardized queue response using DTO
        $response = QueueResponse::createLlmResponse(
            requestId: $requestId,
            model: $data->getModel(),
            promptLength: $tokenCount,
            queueCount: $queueCount,
            estimatedTime: $this->estimateProcessingTime($data, $tokenCount)
        );

        return new JsonResponse($response, 202);
    }

    private function handleSyncRequest(LlmPrompt $data, string $requestId): JsonResponse
    {
        try {
            // Validate token count to prevent timeout
            $chunks = $this->tokenChunker->chunk($data->getPrompt(), 'gpt-3.5-turbo'); // Rough estimation
            $estimatedTokens = count($chunks) * 800; // Rough estimation
            
            if ($estimatedTokens > 4000) {
                return new JsonResponse([
                    'error' => 'Prompt too long for synchronous processing',
                    'estimated_tokens' => $estimatedTokens,
                    'recommendation' => 'Use async=true for large prompts',
                    'request_id' => $requestId
                ], 413); // Request Entity Too Large
            }

            // Process immediately
            $options = [
                'temperature' => $data->getTemperature(),
                'num_predict' => $data->getMaxTokens(),
            ];

            $startTime = microtime(true);
            $response = $this->llmConnector->generateText(
                $data->getPrompt(), 
                $data->getModel(), 
                $options
            );
            $processingTime = round(microtime(true) - $startTime, 2);

            $content = $response->getContent();
            $llmResponse = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from LLM');
            }

            $this->logger->info('LLM sync request completed', [
                'request_id' => $requestId,
                'processing_time' => $processingTime,
                'model' => $data->getModel(),
            ]);

            return new JsonResponse([
                'status' => 'completed',
                'request_id' => $requestId,
                'model' => $data->getModel(),
                'processing_time_seconds' => $processingTime,
                'response' => $llmResponse['response'] ?? $llmResponse,
                'metadata' => [
                    'total_duration' => $llmResponse['total_duration'] ?? null,
                    'load_duration' => $llmResponse['load_duration'] ?? null,
                    'prompt_eval_count' => $llmResponse['prompt_eval_count'] ?? null,
                    'eval_count' => $llmResponse['eval_count'] ?? null,
                ]
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('LLM sync request failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'model' => $data->getModel(),
            ]);

            return new JsonResponse([
                'error' => 'LLM processing failed',
                'message' => $e->getMessage(),
                'request_id' => $requestId,
                'status' => 'failed'
            ], 500);
        }
    }

    private function estimateProcessingTime(LlmPrompt $data, int $tokenCount): string
    {
        // More accurate estimation based on actual token count
        $baseTime = 5; // base seconds
        
        // Token-based complexity (more accurate than string length)
        $tokenComplexity = intval($tokenCount / 100); // ~100 tokens per second processing
        
        // Model-based multiplier
        $modelMultiplier = 1.0;
        if (str_contains(strtolower($data->getModel()), 'large') || str_contains(strtolower($data->getModel()), '70b')) {
            $modelMultiplier = 2.0;
        }
        
        $totalSeconds = intval(($baseTime + $tokenComplexity) * $modelMultiplier);
        
        if ($totalSeconds < 60) {
            return "{$totalSeconds} seconds";
        } elseif ($totalSeconds < 3600) {
            $minutes = intval($totalSeconds / 60);
            return "{$minutes} minute(s)";
        } else {
            $hours = round($totalSeconds / 3600, 1);
            return "{$hours} hour(s)";
        }
    }
}
