<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Service\QueueStatsService;
use App\Service\Connector\RedisConnector;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Debug controller for queue discrepancy analysis.
 * 
 * Helps identify differences between QueueStatsService counters
 * and actual Redis queue contents.
 */
#[ApiResource(
    shortName: 'Monitoring',
    operations: [
        new Get(
            uriTemplate: '/admin/debug/queue',
            controller: self::class,
            description: 'Debug queue counting discrepancies between QueueStatsService and Redis. Admin only.',
            normalizationContext: ['groups' => ['admin:read']],
            security: "is_granted('ROLE_ADMIN')"
        )
    ]
)]
class DebugQueueController
{
    /**
     * Initialize debug controller with queue services.
     * 
     * @param QueueStatsService $queueStats Service for queue statistics
     * @param RedisConnector $redisConnector Redis service connector
     */
    public function __construct(
        private readonly QueueStatsService $queueStats,
        private readonly RedisConnector $redisConnector
    ) {}

    /**
     * Debug queue counting discrepancies.
     * 
     * @return JsonResponse Comprehensive queue debug information
     */
    public function __invoke(): JsonResponse
    {
        try {
            $queueStatsData = $this->getQueueStatsDebugInfo();
            $redisQueueData = $this->getRedisQueueDebugInfo();
            $discrepancyAnalysis = $this->analyzeDiscrepancies($queueStatsData, $redisQueueData);
            
            return new JsonResponse([
                'queue_debug_analysis' => [
                    'queue_stats_service' => $queueStatsData,
                    'redis_connector' => $redisQueueData,
                    'discrepancy_analysis' => $discrepancyAnalysis,
                    'recommendations' => $this->getRecommendations($discrepancyAnalysis),
                    'timestamp' => date('c')
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Queue debug failed',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get QueueStatsService debug information.
     * 
     * @return array QueueStatsService data and counter information
     */
    private function getQueueStatsDebugInfo(): array
    {
        $stats = $this->queueStats->getQueueStats();
        $counterValue = $this->queueStats->getQueueCounterValue();
        
        // Check counter storage type
        $storageType = 'unknown';
        $counterLocation = null;
        
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $storageType = 'apcu';
            $counterLocation = 'apcu://llm_queue_count';
        } else {
            $storageType = 'file';
            $counterLocation = sys_get_temp_dir() . '/llm_queue_count.txt';
        }
        
        $counterFileExists = false;
        $counterFileContent = null;
        
        if ($storageType === 'file') {
            $counterFileExists = is_file($counterLocation);
            if ($counterFileExists) {
                $counterFileContent = file_get_contents($counterLocation);
            }
        }
        
        return [
            'service_name' => 'QueueStatsService',
            'method' => 'Counter-based (increment/decrement)',
            'queue_stats' => $stats,
            'counter_value' => $counterValue,
            'storage_type' => $storageType,
            'storage_location' => $counterLocation,
            'counter_file_exists' => $counterFileExists,
            'counter_file_content' => $counterFileContent,
            'transport_class' => $stats['transport_class'] ?? 'unknown',
            'supports_direct_count' => $stats['supports_count'] ?? false
        ];
    }

    /**
     * Get Redis connector debug information.
     * 
     * @return array Redis queue data and analysis
     */
    private function getRedisQueueDebugInfo(): array
    {
        try {
            $serviceInfo = $this->redisConnector->getServiceInfo();
            $messageQueueInfo = $serviceInfo['message_queue'] ?? [];
            
            return [
                'service_name' => 'RedisConnector',
                'method' => 'Direct Redis key scanning',
                'redis_healthy' => $serviceInfo['healthy'] ?? false,
                'redis_version' => $serviceInfo['version'] ?? 'unknown',
                'message_queue_status' => $messageQueueInfo['status'] ?? 'unknown',
                'total_messages' => $messageQueueInfo['total_messages'] ?? 0,
                'failed_messages' => $messageQueueInfo['failed_messages'] ?? 0,
                'processing_messages' => $messageQueueInfo['processing_messages'] ?? 0,
                'queue_count' => $messageQueueInfo['queue_count'] ?? 0,
                'active_queues' => $messageQueueInfo['queues'] ?? [],
                'queue_types' => $messageQueueInfo['queue_types'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'service_name' => 'RedisConnector',
                'method' => 'Direct Redis key scanning',
                'error' => $e->getMessage(),
                'redis_healthy' => false
            ];
        }
    }

    /**
     * Analyze discrepancies between the two systems.
     * 
     * @param array $queueStatsData QueueStatsService data
     * @param array $redisQueueData RedisConnector data
     * 
     * @return array Discrepancy analysis
     */
    private function analyzeDiscrepancies(array $queueStatsData, array $redisQueueData): array
    {
        $queueStatsCount = $queueStatsData['counter_value'] ?? 0;
        $redisCount = $redisQueueData['total_messages'] ?? 0;
        $difference = $queueStatsCount - $redisCount;
        
        $analysis = [
            'queue_stats_count' => $queueStatsCount,
            'redis_count' => $redisCount,
            'difference' => $difference,
            'discrepancy_detected' => $difference !== 0,
            'likely_causes' => []
        ];
        
        // Analyze likely causes
        if ($difference > 0) {
            $analysis['likely_causes'][] = 'QueueStatsService counter is higher - messages may have been processed but counter not decremented';
            $analysis['likely_causes'][] = 'Messages may be in different Redis transport or database';
            $analysis['likely_causes'][] = 'Counter file/APCu may be stale or not synchronized';
        } elseif ($difference < 0) {
            $analysis['likely_causes'][] = 'Redis has more messages than counter - new messages added without incrementing counter';
            $analysis['likely_causes'][] = 'Counter may have been reset while messages remained in Redis';
        } else {
            $analysis['likely_causes'][] = 'Counts match - systems are synchronized';
        }
        
        // Check if Redis is accessible
        if (!($redisQueueData['redis_healthy'] ?? false)) {
            $analysis['likely_causes'][] = 'Redis connection failed - Redis data may be inaccurate';
        }
        
        return $analysis;
    }

    /**
     * Get recommendations based on discrepancy analysis.
     * 
     * @param array $analysis Discrepancy analysis data
     * 
     * @return array Recommendations for fixing issues
     */
    private function getRecommendations(array $analysis): array
    {
        $recommendations = [];
        $difference = $analysis['difference'] ?? 0;
        
        if ($difference > 0) {
            $recommendations[] = 'Reset counter to match Redis queue count';
            $recommendations[] = 'Check if message handlers are properly decrementing counters';
            $recommendations[] = 'Consider using Redis-based counting instead of file/APCu counters';
        } elseif ($difference < 0) {
            $recommendations[] = 'Reset counter to match Redis queue count';
            $recommendations[] = 'Check if messages are being added without incrementing counter';
        } else {
            $recommendations[] = 'Systems are synchronized - no action needed';
        }
        
        $recommendations[] = 'Consider implementing queue monitoring dashboard';
        $recommendations[] = 'Set up alerts for large discrepancies';
        
        return $recommendations;
    }
}
