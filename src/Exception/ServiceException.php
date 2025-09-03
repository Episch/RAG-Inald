<?php

namespace App\Exception;

/**
 * Base exception for service-related errors.
 * 
 * Provides consistent error handling across all service components
 * with context information and proper error codes.
 */
class ServiceException extends \RuntimeException
{
    /**
     * Create service exception with context.
     * 
     * @param string $message Error message
     * @param string $service Service name that failed
     * @param int $code Error code (HTTP status or internal code)
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message,
        private readonly string $service = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the service that caused the exception.
     * 
     * @return string Service name
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * Create exception for connection failures.
     * 
     * @param string $service Service name
     * @param \Throwable $previous Original exception
     * 
     * @return self Service exception instance
     */
    public static function connectionFailed(string $service, \Throwable $previous): self
    {
        return new self(
            "Failed to connect to {$service}: " . $previous->getMessage(),
            $service,
            503,
            $previous
        );
    }

    /**
     * Create exception for configuration errors.
     * 
     * @param string $service Service name
     * @param string $parameter Missing or invalid parameter
     * 
     * @return self Service exception instance
     */
    public static function configurationError(string $service, string $parameter): self
    {
        return new self(
            "Configuration error for {$service}: {$parameter} is required or invalid",
            $service,
            500
        );
    }

    /**
     * Create exception for invalid data.
     * 
     * @param string $service Service name
     * @param string $reason Validation failure reason
     * 
     * @return self Service exception instance
     */
    public static function invalidData(string $service, string $reason): self
    {
        return new self(
            "Invalid data for {$service}: {$reason}",
            $service,
            400
        );
    }
}
