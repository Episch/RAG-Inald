<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * System status response DTO for service health monitoring.
 * 
 * Provides comprehensive status information for all connected services
 * including Tika, Neo4j, Redis, and LLM services.
 */
#[ApiResource(
    shortName: 'Status',
    operations: [
        new Get(
            uriTemplate: '/status',
            controller: 'App\Controller\StatusController',
            description: 'Comprehensive system health check including all connected services (Tika, Neo4j, Redis, LLM). For queue analysis use /admin/debug/queue.',
            normalizationContext: ['groups' => ['status:read']],
            output: self::class
        )
    ]
)]
class SystemStatus
{
    /**
     * @param array $services Service status information keyed by service name
     * @param string $overall Overall system status ('healthy', 'degraded', 'unhealthy')
     * @param string $timestamp Status check timestamp in ISO 8601 format
     * @param array $environment Environment information (version, etc.)
     */
    public function __construct(
        public readonly array $services = [],
        public readonly string $overall = 'unknown',
        public readonly string $timestamp = '',
        public readonly array $environment = []
    ) {}

    /**
     * Create status response from service status data.
     * 
     * @param array $tikaStatus Tika service status
     * @param array $neo4jStatus Neo4j service status  
     * @param array $llmStatus LLM service status
     * 
     * @return self Populated status DTO
     */
    public static function create(array $tikaStatus, array $neo4jStatus, array $llmStatus): self
    {
        $services = [
            'tika' => $tikaStatus,
            'neo4j' => $neo4jStatus,
            'llm' => $llmStatus
        ];
        
        // Determine overall status
        $healthyCount = 0;
        $totalServices = count($services);
        
        foreach ($services as $status) {
            if (isset($status['healthy']) && $status['healthy'] === true) {
                $healthyCount++;
            }
        }
        
        $overall = match (true) {
            $healthyCount === $totalServices => 'healthy',
            $healthyCount > 0 => 'degraded',
            default => 'unhealthy'
        };
        
        return new self(
            services: $services,
            overall: $overall,
            timestamp: date('c'),
            environment: [
                'php_version' => PHP_VERSION,
                'symfony_version' => class_exists('Symfony\\Component\\HttpKernel\\Kernel') ? 
                    \Symfony\Component\HttpKernel\Kernel::VERSION : 'unknown'
            ]
        );
    }
}
