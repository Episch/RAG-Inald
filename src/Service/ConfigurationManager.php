<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Centralized configuration manager with validation and defaults
 */
class ConfigurationManager
{
    private array $config = [];
    private array $validationErrors = [];

    public function __construct(private ParameterBagInterface $params)
    {
        $this->loadConfiguration();
        $this->validateConfiguration();
    }

    /**
     * Load configuration from various sources
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'services' => [
                'tika' => [
                    'url' => $this->getEnvVar('DOCUMENT_EXTRACTOR_URL', 'http://localhost:9998'),
                    'timeout' => $this->getEnvVar('TIKA_TIMEOUT', 60),
                    'max_file_size' => $this->getEnvVar('TIKA_MAX_FILE_SIZE', 50 * 1024 * 1024) // 50MB
                ],
                'neo4j' => [
                    'url' => $this->getEnvVar('NEO4J_RAG_DATABASE', 'http://localhost:7474'),
                    'timeout' => $this->getEnvVar('NEO4J_TIMEOUT', 30),
                    'max_retries' => $this->getEnvVar('NEO4J_MAX_RETRIES', 3)
                ],
                'llm' => [
                    'url' => $this->getEnvVar('LMM_URL', 'http://localhost:11434'),
                    'timeout' => $this->getEnvVar('LLM_TIMEOUT', 300),
                    'default_model' => $this->getEnvVar('LLM_DEFAULT_MODEL', 'llama3.2'),
                    'max_tokens' => $this->getEnvVar('LLM_MAX_TOKENS', 4096)
                ]
            ],
            'storage' => [
                'document_path' => $this->getEnvVar('DOCUMENT_STORAGE_PATH', $this->params->get('kernel.project_dir') . '/public/storage'),
                'output_path' => $this->getEnvVar('OUTPUT_PATH', $this->params->get('kernel.project_dir') . '/var/output'),
                'max_storage_size' => $this->getEnvVar('MAX_STORAGE_SIZE', 1024 * 1024 * 1024) // 1GB
            ],
            'security' => [
                'allowed_file_extensions' => explode(',', $this->getEnvVar('ALLOWED_FILE_EXTENSIONS', 'pdf,docx,txt,md')),
                'max_file_size' => $this->getEnvVar('MAX_UPLOAD_SIZE', 20 * 1024 * 1024), // 20MB
                'path_validation_regex' => $this->getEnvVar('PATH_VALIDATION_REGEX', '/^[a-zA-Z0-9\/_-]+$/')
            ],
            'performance' => [
                'cache_ttl' => $this->getEnvVar('CACHE_TTL', 300),
                'status_cache_ttl' => $this->getEnvVar('STATUS_CACHE_TTL', 60),
                'enable_caching' => $this->getEnvVar('ENABLE_CACHING', true, 'bool'),
                'max_concurrent_requests' => $this->getEnvVar('MAX_CONCURRENT_REQUESTS', 10)
            ],
            'logging' => [
                'level' => $this->getEnvVar('LOG_LEVEL', 'info'),
                'max_files' => $this->getEnvVar('LOG_MAX_FILES', 10),
                'enable_performance_logging' => $this->getEnvVar('ENABLE_PERFORMANCE_LOGGING', false, 'bool')
            ]
        ];
    }

    /**
     * Validate configuration values
     */
    private function validateConfiguration(): void
    {
        $this->validationErrors = [];

        // Validate URLs
        $urls = [
            'services.tika.url' => $this->config['services']['tika']['url'],
            'services.neo4j.url' => $this->config['services']['neo4j']['url'],
            'services.llm.url' => $this->config['services']['llm']['url']
        ];

        foreach ($urls as $key => $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->validationErrors[] = "Invalid URL for {$key}: {$url}";
            }
        }

        // Validate paths
        $paths = [
            'storage.document_path' => $this->config['storage']['document_path'],
            'storage.output_path' => $this->config['storage']['output_path']
        ];

        foreach ($paths as $key => $path) {
            if (!is_dir(dirname($path))) {
                $this->validationErrors[] = "Invalid directory for {$key}: {$path}";
            }
        }

        // Validate numeric values
        $numericValues = [
            'services.tika.timeout' => $this->config['services']['tika']['timeout'],
            'services.neo4j.timeout' => $this->config['services']['neo4j']['timeout'],
            'services.llm.timeout' => $this->config['services']['llm']['timeout'],
            'performance.cache_ttl' => $this->config['performance']['cache_ttl']
        ];

        foreach ($numericValues as $key => $value) {
            if (!is_numeric($value) || $value <= 0) {
                $this->validationErrors[] = "Invalid numeric value for {$key}: {$value}";
            }
        }
    }

    /**
     * Get configuration value with dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get all configuration
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Get validation errors
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Check if configuration is valid
     */
    public function isValid(): bool
    {
        return empty($this->validationErrors);
    }

    /**
     * Get environment variable with type casting and defaults
     */
    private function getEnvVar(string $name, mixed $default = null, string $type = 'string'): mixed
    {
        $value = $_ENV[$name] ?? $default;

        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$value,
            'array' => is_string($value) ? explode(',', $value) : (array)$value,
            default => (string)$value
        };
    }

    /**
     * Generate configuration report
     */
    public function getConfigReport(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->validationErrors,
            'services' => [
                'tika' => [
                    'configured' => !empty($this->config['services']['tika']['url']),
                    'url' => $this->config['services']['tika']['url']
                ],
                'neo4j' => [
                    'configured' => !empty($this->config['services']['neo4j']['url']),
                    'url' => $this->config['services']['neo4j']['url']
                ],
                'llm' => [
                    'configured' => !empty($this->config['services']['llm']['url']),
                    'url' => $this->config['services']['llm']['url'],
                    'default_model' => $this->config['services']['llm']['default_model']
                ]
            ],
            'storage' => [
                'document_path_exists' => is_dir($this->config['storage']['document_path']),
                'output_path_writable' => is_writable(dirname($this->config['storage']['output_path']))
            ],
            'performance' => [
                'caching_enabled' => $this->config['performance']['enable_caching'],
                'cache_ttl' => $this->config['performance']['cache_ttl']
            ]
        ];
    }
}
