<?php

namespace App\Service\Connector;

use App\Constants\SystemConstants;
use App\Exception\ServiceException;
use App\Contract\ConnectorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Redis connector for cache and message queue monitoring.
 * 
 * Provides functionality to check Redis service health, including
 * connection status, memory usage, and key statistics.
 */
class RedisConnector implements ConnectorInterface
{
    private string $redisHost;
    private int $redisPort;
    private ?\Redis $redis = null;

    /**
     * Initialize Redis connector with validated configuration.
     * 
     * @throws ServiceException If Redis configuration is invalid
     */
    public function __construct()
    {
        // Get Redis configuration from environment
        $this->redisHost = $_ENV['REDIS_HOST'] ?? 'localhost';
        $this->redisPort = (int) ($_ENV['REDIS_PORT'] ?? 6379);
        
        if (!class_exists('\Redis')) {
            throw ServiceException::configurationError('Redis', 'Redis PHP extension is not installed');
        }
    }

    /**
     * Get Redis connection instance.
     * 
     * @return \Redis Connected Redis instance
     * @throws ServiceException If connection fails
     */
    private function getRedisConnection(): \Redis
    {
        if ($this->redis === null) {
            $this->redis = new \Redis();
            
            try {
                $connected = $this->redis->connect(
                    $this->redisHost, 
                    $this->redisPort, 
                    SystemConstants::TIMEOUT_DEFAULT
                );
                
                if (!$connected) {
                    throw ServiceException::connectionFailed('Redis', new \Exception('Connection refused'));
                }
            } catch (\RedisException $e) {
                throw ServiceException::connectionFailed('Redis', $e);
            }
        }
        
        return $this->redis;
    }

    /**
     * Get Redis service status (dummy ResponseInterface for consistency).
     * 
     * @return ResponseInterface Mock response for interface compliance
     * @throws ServiceException If Redis is unavailable
     */
    public function getStatus(): ResponseInterface
    {
        // Since Redis doesn't have HTTP endpoints, we create a mock response
        $serviceInfo = $this->getServiceInfo();
        
        // Create a simple mock response for interface consistency
        return new class($serviceInfo) implements ResponseInterface {
            public function __construct(private array $serviceInfo) {}
            
            public function getStatusCode(): int 
            { 
                return $this->serviceInfo['healthy'] ? 200 : 503; 
            }
            
            public function getHeaders(bool $throw = true): array 
            { 
                return ['content-type' => ['application/json']]; 
            }
            
            public function getContent(bool $throw = true): string 
            { 
                return json_encode($this->serviceInfo); 
            }
            
            public function toArray(bool $throw = true): array 
            { 
                return $this->serviceInfo; 
            }
            
            public function cancel(): void {}
            public function getInfo(string $type = null): mixed { return null; }
        };
    }

    /**
     * Get comprehensive Redis service information.
     * 
     * @return array Service status with detailed metrics
     */
    public function getServiceInfo(): array
    {
        try {
            $redis = $this->getRedisConnection();
            $info = $redis->info();
            
            return [
                'name' => 'Redis',
                'version' => $info['redis_version'] ?? 'unknown',
                'healthy' => true,
                'status_code' => 200,
                'metrics' => [
                    'connected_clients' => (int) ($info['connected_clients'] ?? 0),
                    'used_memory' => $this->formatMemory((int) ($info['used_memory'] ?? 0)),
                    'used_memory_peak' => $this->formatMemory((int) ($info['used_memory_peak'] ?? 0)),
                    'total_commands_processed' => (int) ($info['total_commands_processed'] ?? 0),
                    'keyspace_hits' => (int) ($info['keyspace_hits'] ?? 0),
                    'keyspace_misses' => (int) ($info['keyspace_misses'] ?? 0),
                    'uptime_in_seconds' => (int) ($info['uptime_in_seconds'] ?? 0)
                ],
                'databases' => $this->getDatabaseInfo($redis),
                'config' => [
                    'host' => $this->redisHost,
                    'port' => $this->redisPort
                ]
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Redis',
                'version' => 'unknown',
                'healthy' => false,
                'status_code' => 503,
                'error' => $e->getMessage(),
                'config' => [
                    'host' => $this->redisHost,
                    'port' => $this->redisPort
                ]
            ];
        }
    }

    /**
     * Get database information from Redis.
     * 
     * @param \Redis $redis Connected Redis instance
     * 
     * @return array Database information
     */
    private function getDatabaseInfo(\Redis $redis): array
    {
        try {
            $info = $redis->info('keyspace');
            $databases = [];
            
            foreach ($info as $key => $value) {
                if (str_starts_with($key, 'db')) {
                    $dbNumber = (int) substr($key, 2);
                    
                    // Parse db info (format: "keys=X,expires=Y,avg_ttl=Z")
                    $metrics = [];
                    $parts = explode(',', $value);
                    
                    foreach ($parts as $part) {
                        if (str_contains($part, '=')) {
                            [$metricKey, $metricValue] = explode('=', $part, 2);
                            $metrics[trim($metricKey)] = (int) trim($metricValue);
                        }
                    }
                    
                    $databases[] = [
                        'database' => $dbNumber,
                        'keys' => $metrics['keys'] ?? 0,
                        'expires' => $metrics['expires'] ?? 0,
                        'avg_ttl' => $metrics['avg_ttl'] ?? 0
                    ];
                }
            }
            
            return $databases;
        } catch (\Exception $e) {
            return [['error' => 'Unable to get database info: ' . $e->getMessage()]];
        }
    }

    /**
     * Format memory usage in human-readable format.
     * 
     * @param int $bytes Memory in bytes
     * 
     * @return string Formatted memory string
     */
    private function formatMemory(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' B';
    }

    /**
     * Get Message Queue information from Redis.
     * 
     * @param \Redis $redis Connected Redis instance
     * 
     * @return array Message queue statistics
     */
    private function getMessageQueueInfo(\Redis $redis): array
    {
        try {
            $queueInfo = [
                'status' => 'online',
                'queues' => [],
                'total_messages' => 0,
                'failed_messages' => 0,
                'processing_messages' => 0
            ];
            
            // Symfony Messenger uses these key patterns
            $queuePatterns = [
                'messenger:*:queue:*',      // Regular queue messages
                'messenger:*:retry:*',      // Retry queue messages  
                'messenger:*:failure:*',    // Failed messages
                'messenger:*:delayed:*'     // Delayed messages
            ];
            
            $totalMessages = 0;
            $failedMessages = 0;
            $processingMessages = 0;
            $queues = [];
            
            foreach ($queuePatterns as $pattern) {
                $keys = $redis->keys($pattern);
                
                foreach ($keys as $key) {
                    $type = $redis->type($key);
                    $length = 0;
                    
                    // Get queue length based on Redis data type
                    switch ($type) {
                        case \Redis::REDIS_LIST:
                            $length = $redis->lLen($key);
                            break;
                        case \Redis::REDIS_ZSET:
                            $length = $redis->zCard($key);
                            break;
                        case \Redis::REDIS_SET:
                            $length = $redis->sCard($key);
                            break;
                        case \Redis::REDIS_HASH:
                            $length = $redis->hLen($key);
                            break;
                    }
                    
                    // Categorize queue types
                    $queueType = 'unknown';
                    $queueName = $key;
                    
                    if (str_contains($key, ':queue:')) {
                        $queueType = 'regular';
                        $queueName = $this->extractQueueName($key, 'queue');
                        $totalMessages += $length;
                    } elseif (str_contains($key, ':retry:')) {
                        $queueType = 'retry';
                        $queueName = $this->extractQueueName($key, 'retry');
                        $processingMessages += $length;
                    } elseif (str_contains($key, ':failure:')) {
                        $queueType = 'failed';
                        $queueName = $this->extractQueueName($key, 'failure');
                        $failedMessages += $length;
                    } elseif (str_contains($key, ':delayed:')) {
                        $queueType = 'delayed';
                        $queueName = $this->extractQueueName($key, 'delayed');
                    }
                    
                    if ($length > 0) {
                        $queues[] = [
                            'name' => $queueName,
                            'type' => $queueType,
                            'messages' => $length,
                            'redis_type' => $this->getRedisTypeName($type),
                            'key' => $key
                        ];
                    }
                }
            }
            
            $queueInfo['queues'] = $queues;
            $queueInfo['total_messages'] = $totalMessages;
            $queueInfo['failed_messages'] = $failedMessages;
            $queueInfo['processing_messages'] = $processingMessages;
            $queueInfo['queue_count'] = count($queues);
            
            // Add comprehensive detection for all additional message queue patterns
            $additionalQueues = $this->detectAdditionalQueues($redis, $queues);
            $queues = array_merge($queues, $additionalQueues);
            
            // Recalculate totals after adding additional queues
            foreach ($additionalQueues as $queue) {
                if (str_contains($queue['type'], 'failed')) {
                    $failedMessages += $queue['messages'];
                } elseif (str_contains($queue['type'], 'retry')) {
                    $processingMessages += $queue['messages'];
                } else {
                    $totalMessages += $queue['messages'];
                }
            }
            
            // Update final counts
            $queueInfo['queues'] = $queues;
            $queueInfo['total_messages'] = $totalMessages;
            $queueInfo['failed_messages'] = $failedMessages;
            $queueInfo['processing_messages'] = $processingMessages;
            $queueInfo['queue_count'] = count($queues);
            $queueInfo['queue_types'] = $this->getQueueTypeBreakdown($queues);
            
            return $queueInfo;
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => 'Unable to get queue info: ' . $e->getMessage(),
                'queues' => [],
                'total_messages' => 0
            ];
        }
    }

    /**
     * Extract queue name from Redis key.
     * 
     * @param string $key Redis key
     * @param string $type Queue type (queue, retry, failure, delayed)
     * 
     * @return string Queue name
     */
    private function extractQueueName(string $key, string $type): string
    {
        // Pattern: messenger:transport_name:queue:queue_name
        $parts = explode(':', $key);
        
        if (count($parts) >= 4) {
            $transportName = $parts[1] ?? 'default';
            $queueName = $parts[3] ?? 'unknown';
            return "{$transportName}:{$queueName}";
        }
        
        return $key;
    }

    /**
     * Get queue length based on Redis data type.
     * 
     * @param \Redis $redis Connected Redis instance
     * @param string $key Redis key
     * @param int $type Redis data type
     * 
     * @return int Number of items in the data structure
     */
    private function getKeyLength(\Redis $redis, string $key, int $type): int
    {
        try {
            switch ($type) {
                case \Redis::REDIS_LIST:
                    return $redis->lLen($key);
                case \Redis::REDIS_ZSET:
                    return $redis->zCard($key);
                case \Redis::REDIS_SET:
                    return $redis->sCard($key);
                case \Redis::REDIS_HASH:
                    return $redis->hLen($key);
                case \Redis::REDIS_STRING:
                    // For string types, check if it's a counter or single message
                    $value = $redis->get($key);
                    return is_numeric($value) ? (int)$value : (empty($value) ? 0 : 1);
                default:
                    return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Guess message type from Redis key name.
     * 
     * @param string $key Redis key
     * 
     * @return string Guessed message type
     */
    private function guessMessageType(string $key): string
    {
        $key = strtolower($key);
        
        // Failed message patterns
        if (str_contains($key, 'failed') || str_contains($key, 'failure') || str_contains($key, 'error')) {
            return 'failed';
        }
        
        // Delayed message patterns
        if (str_contains($key, 'delayed') || str_contains($key, 'scheduled')) {
            return 'delayed';
        }
        
        // Retry message patterns
        if (str_contains($key, 'retry') || str_contains($key, 'retries')) {
            return 'retry';
        }
        
        // Specific message handler types (all message types in your system)
        if (str_contains($key, 'extract') || str_contains($key, 'document') || str_contains($key, 'tika')) {
            return 'extractor_queue';
        }
        
        if (str_contains($key, 'llm') || str_contains($key, 'ai') || str_contains($key, 'generate') || str_contains($key, 'ollama')) {
            return 'llm_queue';
        }
        
        if (str_contains($key, 'index') || str_contains($key, 'neo4j') || str_contains($key, 'graph')) {
            return 'indexing_queue';
        }
        
        // Additional common message types
        if (str_contains($key, 'email') || str_contains($key, 'mail')) {
            return 'email_queue';
        }
        
        if (str_contains($key, 'notification') || str_contains($key, 'notify')) {
            return 'notification_queue';
        }
        
        if (str_contains($key, 'batch') || str_contains($key, 'bulk')) {
            return 'batch_queue';
        }
        
        if (str_contains($key, 'priority') || str_contains($key, 'urgent')) {
            return 'priority_queue';
        }
        
        if (str_contains($key, 'scheduled') || str_contains($key, 'cron')) {
            return 'scheduled_queue';
        }
        
        // Doctrine transport
        if (str_contains($key, 'doctrine') || str_contains($key, 'messenger_messages')) {
            return 'doctrine_transport';
        }
        
        // Generic patterns
        if (str_contains($key, 'queue')) {
            return 'regular';
        }
        
        if (str_contains($key, 'async') || str_contains($key, 'worker')) {
            return 'async_processing';
        }
        
        return 'unknown';
    }

    /**
     * Get human-readable Redis type name.
     * 
     * @param int $type Redis type constant
     * 
     * @return string Type name
     */
    private function getRedisTypeName(int $type): string
    {
        return match ($type) {
            \Redis::REDIS_STRING => 'string',
            \Redis::REDIS_LIST => 'list',
            \Redis::REDIS_SET => 'set',
            \Redis::REDIS_ZSET => 'sorted_set',
            \Redis::REDIS_HASH => 'hash',
            default => 'unknown'
        };
    }

    /**
     * Detect additional queue patterns beyond standard Symfony Messenger.
     * 
     * @param \Redis $redis Connected Redis instance
     * @param array $existingQueues Already detected queues
     * 
     * @return array Additional queue information
     */
    private function detectAdditionalQueues(\Redis $redis, array $existingQueues): array
    {
        $additionalQueues = [];
        $existingKeys = array_column($existingQueues, 'key');
        
        // Extended patterns for all possible message queue implementations
        $additionalPatterns = [
            'doctrine_*',               // Doctrine transport tables
            'queue_*',                  // Generic queue patterns
            'async_*',                  // Async processing queues
            'delayed_*',                // Delayed job patterns
            '*_queue',                  // Alternative queue naming
            'extract*',                 // ExtractorMessage queues
            'llm*',                     // LlmMessage queues
            'index*',                   // IndexingMessage queues
            'worker_*',                 // Worker-specific queues
            'job_*',                    // Generic job queues
            'task_*',                   // Task queues
            'batch_*',                  // Batch processing
            'email_*',                  // Email queues
            'notification_*',           // Notification queues
            '*_failed',                 // Failed message patterns
            '*_retry',                  // Retry patterns
            'processing_*',             // Currently processing
            'scheduled_*',              // Scheduled jobs
            'background_*',             // Background tasks
            'priority_*'                // Priority queues
        ];
        
        foreach ($additionalPatterns as $pattern) {
            try {
                $keys = $redis->keys($pattern);
                
                foreach ($keys as $key) {
                    // Skip if already processed
                    if (in_array($key, $existingKeys)) {
                        continue;
                    }
                    
                    $type = $redis->type($key);
                    $length = $this->getKeyLength($redis, $key, $type);
                    
                    if ($length > 0) {
                        $messageType = $this->guessMessageType($key);
                        $additionalQueues[] = [
                            'name' => $key,
                            'type' => $messageType,
                            'messages' => $length,
                            'redis_type' => $this->getRedisTypeName($type),
                            'key' => $key,
                            'detected_via' => 'extended_pattern_search',
                            'pattern' => $pattern
                        ];
                        
                        // Track this key as processed
                        $existingKeys[] = $key;
                    }
                }
            } catch (\Exception $e) {
                // Continue with other patterns if one fails
                continue;
            }
        }
        
        return $additionalQueues;
    }

    /**
     * Get queue type breakdown for monitoring.
     * 
     * @param array $queues Queue information array
     * 
     * @return array Queue type statistics
     */
    private function getQueueTypeBreakdown(array $queues): array
    {
        $breakdown = [];
        
        foreach ($queues as $queue) {
            $type = $queue['type'];
            
            if (!isset($breakdown[$type])) {
                $breakdown[$type] = [
                    'count' => 0,
                    'total_messages' => 0,
                    'queues' => []
                ];
            }
            
            $breakdown[$type]['count']++;
            $breakdown[$type]['total_messages'] += $queue['messages'];
            $breakdown[$type]['queues'][] = $queue['name'];
        }
        
        return $breakdown;
    }

    /**
     * Test Redis connection and basic operations.
     * 
     * @return array Test results
     */
    public function runHealthCheck(): array
    {
        $tests = [];
        
        try {
            $redis = $this->getRedisConnection();
            
            // Test 1: Ping
            $pingResult = $redis->ping();
            $tests['ping'] = [
                'status' => $pingResult === '+PONG' || $pingResult === 'PONG',
                'message' => 'Ping test',
                'result' => $pingResult
            ];
            
            // Test 2: Set/Get test
            $testKey = 'health_check_' . uniqid();
            $testValue = 'test_' . time();
            
            $setResult = $redis->set($testKey, $testValue, 10); // 10 second TTL
            $getValue = $redis->get($testKey);
            $redis->del($testKey); // Cleanup
            
            $tests['set_get'] = [
                'status' => $setResult && $getValue === $testValue,
                'message' => 'Set/Get operations test',
                'set_result' => $setResult,
                'get_result' => $getValue === $testValue
            ];
            
        } catch (\Exception $e) {
            $tests['connection'] = [
                'status' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage()
            ];
        }
        
        return $tests;
    }
}
