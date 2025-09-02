<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Centralized cache manager for performance optimization
 */
class CacheManager
{
    private const DEFAULT_TTL = 300; // 5 minutes
    private const STATUS_CACHE_TTL = 60; // 1 minute for status checks
    private const MODEL_CACHE_TTL = 3600; // 1 hour for model lists

    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Cache service status with shorter TTL
     */
    public function cacheServiceStatus(string $serviceName, callable $callback): array
    {
        return $this->cache->get(
            $this->getCacheKey('service_status', $serviceName),
            function (ItemInterface $item) use ($callback) {
                $item->expiresAfter(self::STATUS_CACHE_TTL);
                return $callback();
            }
        );
    }

    /**
     * Cache LLM models list with longer TTL
     */
    public function cacheModelsList(string $serviceName, callable $callback): array
    {
        return $this->cache->get(
            $this->getCacheKey('models', $serviceName),
            function (ItemInterface $item) use ($callback) {
                $item->expiresAfter(self::MODEL_CACHE_TTL);
                return $callback();
            }
        );
    }

    /**
     * Cache document extraction results
     */
    public function cacheDocumentExtraction(string $filePath, callable $callback): array
    {
        $fileHash = md5_file($filePath);
        
        return $this->cache->get(
            $this->getCacheKey('extraction', $fileHash),
            function (ItemInterface $item) use ($callback, $filePath) {
                $item->expiresAfter(self::DEFAULT_TTL);
                // Add file modification time as tag for cache invalidation
                $item->tag(['file_' . md5($filePath)]);
                return $callback();
            }
        );
    }

    /**
     * Cache prompt rendering results
     */
    public function cachePromptRendering(string $templateHash, array $variables, callable $callback): string
    {
        $variablesHash = md5(serialize($variables));
        
        return $this->cache->get(
            $this->getCacheKey('prompt', $templateHash, $variablesHash),
            function (ItemInterface $item) use ($callback) {
                $item->expiresAfter(self::DEFAULT_TTL);
                return $callback();
            }
        );
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern(string $pattern): bool
    {
        return $this->cache->delete($pattern);
    }

    /**
     * Invalidate all cache for a service
     */
    public function invalidateService(string $serviceName): bool
    {
        return $this->cache->delete($this->getCacheKey('*', $serviceName));
    }

    /**
     * Clear all application cache
     */
    public function clearAll(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Get cache statistics (if supported by the cache adapter)
     */
    public function getStats(): array
    {
        if (method_exists($this->cache, 'getStats')) {
            return $this->cache->getStats();
        }
        
        return ['message' => 'Cache statistics not available for this adapter'];
    }

    /**
     * Generate consistent cache keys
     */
    private function getCacheKey(string $type, string ...$parts): string
    {
        $parts = array_filter($parts); // Remove empty parts
        return sprintf('app.%s.%s', $type, implode('.', $parts));
    }
}
