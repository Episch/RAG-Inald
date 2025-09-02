<?php

namespace App\Controller;

use App\Service\Connector\LlmConnector;
use App\Service\Connector\OllamaModelManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LlmTestController
{
    public function __construct(
        private LlmConnector $llmConnector,
        private OllamaModelManager $modelManager
    ) {}

    #[Route('/test/llm/sync', name: 'test_llm_sync', methods: ['GET'])]
    public function testSyncGeneration(): JsonResponse
    {
        try {
            // Step 1: Check model availability
            $modelInfo = $this->modelManager->getBestAvailableModel();
            
            if ($modelInfo['status'] === 'needs_install') {
                return new JsonResponse([
                    'status' => 'model_required',
                    'message' => $modelInfo['message'],
                    'recommended_action' => 'Run: ollama pull ' . $modelInfo['recommended'],
                    'model_info' => $modelInfo
                ], 200);
            }
            
            $modelName = $modelInfo['model'];
            
            // Step 2: Try simple generation
            $startTime = microtime(true);
            
            try {
                $response = $this->llmConnector->generateText(
                    "Say 'Hello from Ollama!' and nothing else.",
                    $modelName,
                    ['temperature' => 0.1, 'num_predict' => 10]
                );
                
                $processingTime = round(microtime(true) - $startTime, 2);
                $content = $response->getContent();
                $responseData = json_decode($content, true);
                
                return new JsonResponse([
                    'status' => 'success',
                    'model' => $modelName,
                    'processing_time' => $processingTime,
                    'response' => $responseData,
                    'raw_content' => $content,
                    'model_info' => $modelInfo
                ], 200);
                
            } catch (\Exception $e) {
                return new JsonResponse([
                    'status' => 'generation_failed',
                    'model' => $modelName,
                    'error' => $e->getMessage(),
                    'processing_time' => round(microtime(true) - $startTime, 2),
                    'model_info' => $modelInfo,
                    'suggestions' => [
                        'Check if Ollama is running: curl http://localhost:11434',
                        'Check available models: ollama list',
                        'Pull a model: ollama pull llama3.2',
                        'Test directly: curl http://localhost:11434/api/generate -d \'{"model":"llama3.2","prompt":"test"}\''
                    ]
                ], 500);
            }
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'test_failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/test/llm/models', name: 'test_llm_models', methods: ['GET'])]
    public function testModels(): JsonResponse
    {
        try {
            $response = $this->llmConnector->getModels();
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            $recommendations = $this->modelManager->getRecommendedModels();
            $bestModel = $this->modelManager->getBestAvailableModel();
            
            return new JsonResponse([
                'status' => 'success',
                'models_response' => [
                    'status_code' => $response->getStatusCode(),
                    'content' => $data,
                    'models_count' => count($data['models'] ?? [])
                ],
                'best_available' => $bestModel,
                'recommendations' => $recommendations,
                'actions' => $this->getModelActions($data)
            ], 200);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'suggestions' => [
                    'Ensure Ollama is running',
                    'Check Ollama version: ollama --version',
                    'List models: ollama list',
                    'Pull a model: ollama pull llama3.2'
                ]
            ], 500);
        }
    }

    private function getModelActions(array $data): array
    {
        $actions = [];
        
        if (empty($data['models'] ?? [])) {
            $actions[] = [
                'action' => 'pull_model',
                'command' => 'ollama pull llama3.2',
                'description' => 'Download the recommended model'
            ];
        } else {
            $actions[] = [
                'action' => 'test_generation',
                'endpoint' => '/test/llm/sync',
                'description' => 'Test text generation with available model'
            ];
        }
        
        return $actions;
    }
}
