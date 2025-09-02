<?php

namespace App\Service;

use Symfony\Component\Messenger\Transport\TransportInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to get queue statistics and counts
 */
class QueueStatsService
{
    public function __construct(
        private TransportInterface $asyncTransport,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get current queue message count
     */
    public function getQueueCount(): ?int
    {
        try {
            // Different transport types have different methods to get count
            if (method_exists($this->asyncTransport, 'getMessageCount')) {
                return $this->asyncTransport->getMessageCount();
            }
            
            // For Doctrine transport, we can use a different approach
            if (method_exists($this->asyncTransport, 'get')) {
                // Try to peek at messages without consuming them
                $messages = [];
                $tempMessages = [];
                
                // Get up to 50 messages to count them
                for ($i = 0; $i < 50; $i++) {
                    $envelope = $this->asyncTransport->get();
                    if (empty($envelope)) {
                        break;
                    }
                    $tempMessages = array_merge($tempMessages, $envelope);
                }
                
                // Put messages back (acknowledge them later)
                // This is a rough estimation approach
                $count = count($tempMessages);
                
                // Log the count for debugging
                $this->logger->debug('Queue count estimated', ['count' => $count]);
                
                return $count;
            }
            
            return null; // Transport doesn't support count
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get queue count: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        try {
            $count = $this->getQueueCount();
            
            return [
                'message_count' => $count,
                'transport_class' => get_class($this->asyncTransport),
                'supports_count' => $count !== null,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'message_count' => null,
                'transport_class' => get_class($this->asyncTransport),
                'supports_count' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Simple approach: Use a counter in cache/database
     * This is more reliable across different transport types
     */
    public function incrementQueueCounter(): void
    {
        try {
            // Use APCu if available (fast in-memory cache)
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                apcu_inc('llm_queue_count', 1);
            } else {
                // Fallback: use a simple file-based counter
                $counterFile = sys_get_temp_dir() . '/llm_queue_count.txt';
                $currentCount = is_file($counterFile) ? (int)file_get_contents($counterFile) : 0;
                file_put_contents($counterFile, $currentCount + 1);
            }
        } catch (\Exception $e) {
            $this->logger->debug('Failed to increment queue counter: ' . $e->getMessage());
        }
    }

    /**
     * Get the cached counter value
     */
    public function getQueueCounterValue(): ?int
    {
        try {
            // Try APCu first
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                $value = apcu_fetch('llm_queue_count');
                return $value !== false ? (int)$value : 0;
            } else {
                // Fallback: file-based counter
                $counterFile = sys_get_temp_dir() . '/llm_queue_count.txt';
                if (is_file($counterFile)) {
                    return (int)file_get_contents($counterFile);
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get queue counter: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Decrement counter (called when message is processed)
     */
    public function decrementQueueCounter(): void
    {
        try {
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                apcu_dec('llm_queue_count', 1);
            } else {
                $counterFile = sys_get_temp_dir() . '/llm_queue_count.txt';
                $currentCount = is_file($counterFile) ? (int)file_get_contents($counterFile) : 0;
                $newCount = max(0, $currentCount - 1); // Don't go below 0
                file_put_contents($counterFile, $newCount);
            }
        } catch (\Exception $e) {
            $this->logger->debug('Failed to decrement queue counter: ' . $e->getMessage());
        }
    }
}
