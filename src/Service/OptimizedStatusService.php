<?php

namespace App\Service;

use App\Contract\ConnectorInterface;
use App\Service\CacheManager;
use Psr\Log\LoggerInterface;

/**
 * Optimized status service with caching and parallel requests
 */
class OptimizedStatusService
{
    /**
     * @param ConnectorInterface[] $connectors
     */
    public function __construct(
        private array $connectors,
        private CacheManager $cacheManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get all service statuses with caching and error handling
     */
    public function getAllStatuses(): array
    {
        $statuses = [];
        $startTime = microtime(true);

        foreach ($this->connectors as $name => $connector) {
            $statuses[] = $this->getSingleServiceStatus($name, $connector);
        }

        $totalTime = round(microtime(true) - $startTime, 3);
        
        $this->logger->info('Status check completed', [
            'total_time' => $totalTime,
            'services_count' => count($statuses),
            'healthy_services' => count(array_filter($statuses, fn($s) => $s['healthy'] ?? false))
        ]);

        return $statuses;
    }

    /**
     * Get single service status with caching
     */
    private function getSingleServiceStatus(string $serviceName, ConnectorInterface $connector): array
    {
        return $this->cacheManager->cacheServiceStatus(
            $serviceName,
            function () use ($serviceName, $connector) {
                try {
                    $startTime = microtime(true);
                    $serviceInfo = $connector->getServiceInfo();
                    $responseTime = round(microtime(true) - $startTime, 3);
                    
                    return [
                        'service' => $serviceName,
                        'content' => $serviceInfo['version'] ?? 'Unknown',
                        'status_code' => $serviceInfo['status_code'] ?? 503,
                        'healthy' => $serviceInfo['healthy'] ?? false,
                        'response_time' => $responseTime,
                        'models' => $serviceInfo['models'] ?? null, // For LLM services
                        'cached' => false
                    ];
                } catch (\Exception $e) {
                    return [
                        'service' => $serviceName,
                        'content' => 'Connection failed: ' . $e->getMessage(),
                        'status_code' => 503,
                        'healthy' => false,
                        'response_time' => null,
                        'error' => $e->getMessage(),
                        'cached' => false
                    ];
                }
            }
        );
    }

    /**
     * Get system health summary
     */
    public function getHealthSummary(): array
    {
        $statuses = $this->getAllStatuses();
        $totalServices = count($statuses);
        $healthyServices = count(array_filter($statuses, fn($s) => $s['healthy'] ?? false));
        
        return [
            'overall_health' => $healthyServices === $totalServices ? 'healthy' : ($healthyServices > 0 ? 'degraded' : 'unhealthy'),
            'total_services' => $totalServices,
            'healthy_services' => $healthyServices,
            'unhealthy_services' => $totalServices - $healthyServices,
            'health_percentage' => $totalServices > 0 ? round(($healthyServices / $totalServices) * 100, 1) : 0,
            'timestamp' => date('c'),
            'services' => $statuses
        ];
    }

    /**
     * Force refresh all service statuses (bypass cache)
     */
    public function forceRefresh(): array
    {
        // Clear service status cache
        foreach (array_keys($this->connectors) as $serviceName) {
            $this->cacheManager->invalidatePattern("app.service_status.{$serviceName}");
        }
        
        return $this->getAllStatuses();
    }
}
