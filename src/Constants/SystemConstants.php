<?php

namespace App\Constants;

/**
 * System-wide constants for consistent configuration values.
 * 
 * Centralizes magic numbers and configuration defaults to improve
 * maintainability and reduce code duplication.
 */
final class SystemConstants
{
    // Token processing limits
    public const TOKEN_SYNC_LIMIT = 4000;           // Max tokens for sync processing
    public const TOKEN_CHUNK_SIZE = 800;            // Default chunk size for token processing
    public const TOKEN_CHUNK_OVERLAP = 100;         // Overlap between chunks
    
    // Service timeouts (in seconds)
    public const TIMEOUT_DEFAULT = 30;              // Default service timeout
    public const TIMEOUT_TIKA = 60;                 // Apache Tika processing timeout
    public const TIMEOUT_LLM = 300;                 // LLM generation timeout (5 minutes)
    public const TIMEOUT_NEO4J = 30;                // Neo4j database timeout
    
    // Cache settings (in seconds)
    public const CACHE_STATUS_TTL = 60;             // Status cache time-to-live (1 minute)
    public const CACHE_CONFIG_TTL = 300;            // Configuration cache TTL (5 minutes)
    
    // Time formatting
    public const SECONDS_PER_MINUTE = 60;           // Seconds in a minute
    
    // Default processing estimates
    public const PROCESSING_TIME_DEFAULT = '30 seconds';
    
    // HTTP status codes (commonly used)
    public const HTTP_OK = 200;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_INTERNAL_ERROR = 500;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
}
