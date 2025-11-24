<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Refresh Token Service using Redis for token storage
 * 
 * Manages JWT refresh tokens with Redis as storage backend
 * for fast, scalable token management
 */
class RefreshTokenService
{
    private const TOKEN_PREFIX = 'refresh_token:';
    private const USER_TOKENS_PREFIX = 'user_tokens:';
    private const DEFAULT_TTL = 604800; // 7 days in seconds

    private Client $redis;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $redisUrl = 'tcp://127.0.0.1:6379'
    ) {
        $this->redis = new Client($redisUrl);
    }

    /**
     * Store a refresh token for a user
     * 
     * @param string $token The refresh token (hashed)
     * @param string $userId The user ID
     * @param int $ttl Time to live in seconds (default: 7 days)
     * @return bool Success status
     */
    public function storeToken(string $token, string $userId, int $ttl = self::DEFAULT_TTL): bool
    {
        try {
            $tokenKey = self::TOKEN_PREFIX . $token;
            $userTokensKey = self::USER_TOKENS_PREFIX . $userId;

            // Store token with user ID
            $this->redis->setex($tokenKey, $ttl, $userId);

            // Add token to user's token set
            $this->redis->sadd($userTokensKey, [$token]);
            $this->redis->expire($userTokensKey, $ttl);

            $this->logger->info('Refresh token stored', [
                'user_id' => $userId,
                'ttl' => $ttl,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to store refresh token', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify and get user ID from refresh token
     * 
     * @param string $token The refresh token to verify
     * @return string|null User ID if valid, null otherwise
     */
    public function verifyToken(string $token): ?string
    {
        try {
            $tokenKey = self::TOKEN_PREFIX . $token;
            $userId = $this->redis->get($tokenKey);

            if ($userId === null) {
                $this->logger->warning('Invalid or expired refresh token', [
                    'token_hash' => substr(hash('sha256', $token), 0, 8),
                ]);
                return null;
            }

            $this->logger->info('Refresh token verified', [
                'user_id' => $userId,
            ]);

            return $userId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify refresh token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Revoke a specific refresh token
     * 
     * @param string $token The token to revoke
     * @return bool Success status
     */
    public function revokeToken(string $token): bool
    {
        try {
            $tokenKey = self::TOKEN_PREFIX . $token;
            
            // Get user ID before deleting
            $userId = $this->redis->get($tokenKey);
            
            if ($userId) {
                $userTokensKey = self::USER_TOKENS_PREFIX . $userId;
                $this->redis->srem($userTokensKey, $token);
            }

            $deleted = $this->redis->del([$tokenKey]);

            $this->logger->info('Refresh token revoked', [
                'user_id' => $userId,
                'success' => $deleted > 0,
            ]);

            return $deleted > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to revoke refresh token', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Revoke all refresh tokens for a user
     * 
     * @param string $userId The user ID
     * @return int Number of tokens revoked
     */
    public function revokeAllUserTokens(string $userId): int
    {
        try {
            $userTokensKey = self::USER_TOKENS_PREFIX . $userId;
            $tokens = $this->redis->smembers($userTokensKey);

            $revokedCount = 0;
            foreach ($tokens as $token) {
                $tokenKey = self::TOKEN_PREFIX . $token;
                $revokedCount += $this->redis->del([$tokenKey]);
            }

            // Delete user's token set
            $this->redis->del([$userTokensKey]);

            $this->logger->info('All user tokens revoked', [
                'user_id' => $userId,
                'count' => $revokedCount,
            ]);

            return $revokedCount;
        } catch (\Exception $e) {
            $this->logger->error('Failed to revoke all user tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get remaining TTL for a token
     * 
     * @param string $token The refresh token
     * @return int|null TTL in seconds, null if token doesn't exist
     */
    public function getTokenTTL(string $token): ?int
    {
        try {
            $tokenKey = self::TOKEN_PREFIX . $token;
            $ttl = $this->redis->ttl($tokenKey);

            return $ttl > 0 ? $ttl : null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get token TTL', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if Redis is available
     * 
     * @return bool True if Redis is reachable
     */
    public function isAvailable(): bool
    {
        try {
            $this->redis->ping();
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('Redis is not available', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get Redis connection info for health checks
     * 
     * @return array Connection status information
     */
    public function getConnectionInfo(): array
    {
        try {
            $info = $this->redis->info();
            
            return [
                'connected' => true,
                'version' => $info['Server']['redis_version'] ?? 'unknown',
                'uptime_seconds' => $info['Server']['uptime_in_seconds'] ?? 0,
                'used_memory_human' => $info['Memory']['used_memory_human'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

