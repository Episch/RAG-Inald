<?php

namespace App\Controller;

use App\Service\Connector\LlmConnector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DebugController
{
    public function __construct(private LlmConnector $llmConnector)
    {
    }

    #[Route('/debug/ollama', name: 'debug_ollama', methods: ['GET'])]
    public function debugOllama(): JsonResponse
    {
        try {
            // Check all endpoints
            $endpoints = $this->llmConnector->debugAvailableEndpoints();
            
            // Get model information
            $modelsResponse = null;
            $modelsError = null;
            try {
                $response = $this->llmConnector->getModels();
                $modelsResponse = [
                    'status_code' => $response->getStatusCode(),
                    'content' => json_decode($response->getContent(), true),
                ];
            } catch (\Exception $e) {
                $modelsError = $e->getMessage();
            }
            
            // Try a simple generate call for debugging
            $generateTest = null;
            $generateError = null;
            try {
                $testResponse = $this->llmConnector->generateText("Test", "llama3.2");
                $generateTest = [
                    'status_code' => $testResponse->getStatusCode(),
                    'content_preview' => substr($testResponse->getContent(), 0, 200),
                ];
            } catch (\Exception $e) {
                $generateError = $e->getMessage();
            }
            
            return new JsonResponse([
                'ollama_debug' => [
                    'base_url' => $_ENV['LMM_URL'] ?? 'http://localhost:11434',
                    'endpoints' => $endpoints,
                    'models' => [
                        'response' => $modelsResponse,
                        'error' => $modelsError
                    ],
                    'generate_test' => [
                        'response' => $generateTest,
                        'error' => $generateError
                    ],
                    'recommendations' => $this->getRecommendations($endpoints, $modelsResponse, $generateError)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Debug failed',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    private function getRecommendations(array $endpoints, ?array $modelsResponse, ?string $generateError): array
    {
        $recommendations = [];
        
        // Check if generate endpoint is available
        if (!($endpoints['generate']['available'] ?? false)) {
            $recommendations[] = "❌ /api/generate endpoint not available - check Ollama version";
        }
        
        // Check if models are loaded
        if (empty($modelsResponse['content']['models'] ?? [])) {
            $recommendations[] = "⚠️ No models loaded. Run: ollama pull llama3.2";
        }
        
        // Check for 404 errors
        if ($generateError && strpos($generateError, '404') !== false) {
            $recommendations[] = "🔧 404 error suggests endpoint doesn't exist. Try updating Ollama";
        }
        
        // General recommendations
        if (empty($recommendations)) {
            $recommendations[] = "✅ Basic connectivity looks good";
        }
        
        $recommendations[] = "💡 Useful commands:";
        $recommendations[] = "  - ollama list (show installed models)";
        $recommendations[] = "  - ollama pull llama3.2 (download model)";
        $recommendations[] = "  - ollama serve (start server)";
        
        return $recommendations;
    }
}
