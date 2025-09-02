<?php

namespace App\Controller;

use ApiPlatform\OpenApi\Model\Response;
use App\Service\Connector\Neo4JConnector;
use App\Service\Connector\TikaConnector;
use App\Service\Connector\LlmConnector;
use Symfony\Component\HttpFoundation\JsonResponse;

class StatusController
{
    private TikaConnector $tikaConnector;
    private Neo4JConnector $neo4JConnector;
    private LlmConnector $llmConnector;

    public function __construct(
        TikaConnector $tikaConnector, 
        Neo4JConnector $neo4JConnector,
        LlmConnector $llmConnector
    ) {
        $this->tikaConnector = $tikaConnector;
        $this->neo4JConnector = $neo4JConnector;
        $this->llmConnector = $llmConnector;
    }

    public function __invoke(): JsonResponse
    {
        try {
            // 🚀 Performance: Cache responses to avoid duplicate API calls
            $tikaStatus = $this->getTikaStatus();
            $neo4jStatus = $this->getNeo4jStatus();
            $llmStatus = $this->getLlmStatus();
            
            return new JsonResponse([
                'status' => [
                    [
                        'service' => 'DocumentConnector',
                        'content' => $tikaStatus['content'],
                        'status_code' => $tikaStatus['status_code'],
                        'healthy' => $tikaStatus['status_code'] >= 200 && $tikaStatus['status_code'] < 300
                    ],
                    [
                        'service' => 'RagConnector', 
                        'content' => $neo4jStatus['content'],
                        'status_code' => $neo4jStatus['status_code'],
                        'healthy' => $neo4jStatus['status_code'] >= 200 && $neo4jStatus['status_code'] < 300
                    ],
                    [
                        'service' => 'LlmConnector',
                        'content' => $llmStatus['content'],
                        'status_code' => $llmStatus['status_code'],
                        'healthy' => $llmStatus['status_code'] >= 200 && $llmStatus['status_code'] < 300,
                        'models' => $llmStatus['models'] ?? []
                    ],
                ]
            ], 200);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage(),
                'status_code' => $exception->getCode() ?: 500
            ], $exception->getCode() ?: 500);
        }
    }

    /**
     * 🚀 Performance: Get Tika status with single API call
     */
    private function getTikaStatus(): array
    {
        try {
            $response = $this->tikaConnector->getStatus();
            return [
                'content' => $response->getContent(),
                'status_code' => $response->getStatusCode()
            ];
        } catch (\Exception $e) {
            return [
                'content' => 'Connection failed: ' . $e->getMessage(),
                'status_code' => 503 // Service Unavailable
            ];
        }
    }

    /**
     * 🚀 Performance: Get Neo4J status with single API call and safe JSON parsing
     */
    private function getNeo4jStatus(): array
    {
        try {
            $response = $this->neo4JConnector->getStatus();
            $content = $response->getContent();
            $statusCode = $response->getStatusCode();
            
            // 🛡️ Safe JSON parsing
            $jsonData = json_decode($content, true);
            $version = $jsonData['neo4j_version'] ?? 'Unknown version';
            
            return [
                'content' => $version,
                'status_code' => $statusCode
            ];
        } catch (\Exception $e) {
            return [
                'content' => 'Connection failed: ' . $e->getMessage(),
                'status_code' => 503 // Service Unavailable
            ];
        }
    }

    /**
     * 🚀 Performance: Get LLM status with single API call and model information
     */
    private function getLlmStatus(): array
    {
        try {
            // Get version/status
            $statusResponse = $this->llmConnector->getStatus();
            $statusContent = $statusResponse->getContent();
            $statusCode = $statusResponse->getStatusCode();
            
            // Parse version info
            $versionData = json_decode($statusContent, true);
            $version = $versionData['version'] ?? 'Unknown version';
            
            // Try to get available models (optional - don't fail if this doesn't work)
            $models = [];
            try {
                $modelsResponse = $this->llmConnector->getModels();
                if ($modelsResponse->getStatusCode() === 200) {
                    $modelsData = json_decode($modelsResponse->getContent(), true);
                    $models = array_map(fn($model) => $model['name'] ?? $model, $modelsData['models'] ?? []);
                }
            } catch (\Exception $e) {
                // Models fetch failed, but that's OK - we still have status
            }
            
            return [
                'content' => $version,
                'status_code' => $statusCode,
                'models' => $models
            ];
        } catch (\Exception $e) {
            return [
                'content' => 'Connection failed: ' . $e->getMessage(),
                'status_code' => 503, // Service Unavailable
                'models' => []
            ];
        }
    }
}
