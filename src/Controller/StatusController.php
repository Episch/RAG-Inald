<?php

namespace App\Controller;

use App\Dto\SystemStatus;
use App\Service\Connector\Neo4JConnector;
use App\Service\Connector\RedisConnector;
use App\Service\Connector\TikaConnector;
use App\Service\Connector\LlmConnector;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * System status controller for monitoring service health.
 * 
 * Provides comprehensive health checks for all connected services
 * including Apache Tika, Neo4j, Redis, and LLM services.
 */
class StatusController
{
    /**
     * Initialize status controller with service connectors.
     * 
     * @param TikaConnector $tikaConnector Apache Tika document extraction service
     * @param Neo4JConnector $neo4JConnector Neo4j graph database service
     * @param RedisConnector $redisConnector Redis cache and message queue service
     * @param LlmConnector $llmConnector Large Language Model service
     */
    public function __construct(
        private readonly TikaConnector $tikaConnector,
        private readonly Neo4JConnector $neo4JConnector,
        private readonly RedisConnector $redisConnector,
        private readonly LlmConnector $llmConnector
    ) {}

    /**
     * Get comprehensive system status.
     * 
     * @return JsonResponse JSON response with service status information
     */
    public function __invoke(): JsonResponse
    {
        try {
            // Performance: Cache responses to avoid duplicate API calls
            $tikaStatus = $this->getTikaStatus();
            $neo4jStatus = $this->getNeo4jStatus();
            $redisStatus = $this->getRedisStatus();
            $llmStatus = $this->getLlmStatus();
            
            $statusDto = SystemStatus::create($tikaStatus, $neo4jStatus, $redisStatus, $llmStatus);
            
            return new JsonResponse([
                'services' => $statusDto->services,
                'overall_status' => $statusDto->overall,
                'timestamp' => $statusDto->timestamp,
                'environment' => $statusDto->environment,
                'queue_debug_endpoint' => '/admin/debug/queue'
            ], 200);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'Failed to retrieve system status',
                'message' => $exception->getMessage(),
                'timestamp' => date('c')
            ], 503);
        }
    }

    /**
     * Get Tika service status with error handling.
     * 
     * @return array Service status information
     */
    private function getTikaStatus(): array
    {
        try {
            $serviceInfo = $this->tikaConnector->getServiceInfo();
            return [
                'name' => $serviceInfo['name'],
                'version' => $serviceInfo['version'],
                'healthy' => $serviceInfo['healthy'],
                'status_code' => $serviceInfo['status_code']
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Apache Tika',
                'version' => 'unknown',
                'healthy' => false,
                'status_code' => 503,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Neo4j service status with error handling.
     * 
     * @return array Service status information
     */
    private function getNeo4jStatus(): array
    {
        try {
            $serviceInfo = $this->neo4JConnector->getServiceInfo();
            return [
                'name' => $serviceInfo['name'],
                'version' => $serviceInfo['version'],
                'healthy' => $serviceInfo['healthy'],
                'status_code' => $serviceInfo['status_code']
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Neo4j',
                'version' => 'unknown',
                'healthy' => false,
                'status_code' => 503,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Redis service status with error handling.
     * 
     * @return array Service status information
     */
    private function getRedisStatus(): array
    {
        try {
            $serviceInfo = $this->redisConnector->getServiceInfo();
            return [
                'name' => $serviceInfo['name'],
                'version' => $serviceInfo['version'],
                'healthy' => $serviceInfo['healthy'],
                'status_code' => $serviceInfo['status_code'],
                'metrics' => $serviceInfo['metrics'] ?? [],
                'databases' => $serviceInfo['databases'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Redis',
                'version' => 'unknown',
                'healthy' => false,
                'status_code' => 503,
                'metrics' => [],
                'databases' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get LLM service status with error handling.
     * 
     * @return array Service status information
     */
    private function getLlmStatus(): array
    {
        try {
            $serviceInfo = $this->llmConnector->getServiceInfo();
            return [
                'name' => $serviceInfo['name'],
                'version' => $serviceInfo['version'],
                'healthy' => $serviceInfo['healthy'],
                'status_code' => $serviceInfo['status_code'],
                'models' => $serviceInfo['models'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Ollama LLM',
                'version' => 'unknown',
                'healthy' => false,
                'status_code' => 503,
                'models' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}