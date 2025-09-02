<?php

namespace App\Controller;

use App\Service\ConfigurationManager;
use App\Service\Factory\ConnectorFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for configuration management and diagnostics
 */
class ConfigurationController
{
    public function __construct(
        private ConfigurationManager $configManager,
        private ConnectorFactory $connectorFactory
    ) {
    }

    #[Route('/config/status', name: 'config_status', methods: ['GET'])]
    public function getConfigurationStatus(): JsonResponse
    {
        $configReport = $this->configManager->getConfigReport();
        
        return new JsonResponse([
            'configuration' => $configReport,
            'timestamp' => date('c'),
            'environment' => $_ENV['APP_ENV'] ?? 'prod'
        ], 200);
    }

    #[Route('/config/test', name: 'config_test', methods: ['GET'])]
    public function testConfiguration(): JsonResponse
    {
        $configValid = $this->configManager->isValid();
        $connectorTests = [];
        
        if ($configValid) {
            $connectorTests = $this->connectorFactory->testAllConfigurations();
        }

        $overallSuccess = $configValid && count(array_filter($connectorTests, fn($test) => $test['success'])) === count($connectorTests);

        return new JsonResponse([
            'overall_success' => $overallSuccess,
            'configuration_valid' => $configValid,
            'configuration_errors' => $this->configManager->getValidationErrors(),
            'connector_tests' => $connectorTests,
            'recommendations' => $this->getRecommendations($configValid, $connectorTests),
            'timestamp' => date('c')
        ], $overallSuccess ? 200 : 500);
    }

    #[Route('/config/env', name: 'config_env', methods: ['GET'])]
    public function getEnvironmentInfo(): JsonResponse
    {
        $envVars = [
            'APP_ENV',
            'DOCUMENT_EXTRACTOR_URL',
            'NEO4J_RAG_DATABASE', 
            'LMM_URL',
            'CACHE_TTL',
            'LOG_LEVEL'
        ];

        $environment = [];
        foreach ($envVars as $var) {
            $environment[$var] = [
                'set' => isset($_ENV[$var]),
                'value' => $_ENV[$var] ?? null,
                'sensitive' => in_array($var, ['NEO4J_RAG_DATABASE']) // Mark sensitive vars
            ];
        }

        return new JsonResponse([
            'environment_variables' => $environment,
            'php_version' => PHP_VERSION,
            'symfony_version' => class_exists('Symfony\\Component\\HttpKernel\\Kernel') ? 
                \Symfony\Component\HttpKernel\Kernel::VERSION : 'unknown',
            'system_info' => [
                'os' => PHP_OS,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'timestamp' => date('c')
        ], 200);
    }

    /**
     * Generate recommendations based on configuration and tests
     */
    private function getRecommendations(bool $configValid, array $connectorTests): array
    {
        $recommendations = [];

        if (!$configValid) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Configuration validation failed. Check environment variables.',
                'action' => 'Review and fix configuration errors'
            ];
        }

        foreach ($connectorTests as $service => $test) {
            if (!$test['success']) {
                $recommendations[] = [
                    'type' => 'warning',
                    'message' => "Service {$service} is not accessible: " . ($test['error'] ?? 'Unknown error'),
                    'action' => "Check if {$service} service is running and accessible"
                ];
            } elseif (isset($test['response_time_ms']) && $test['response_time_ms'] > 5000) {
                $recommendations[] = [
                    'type' => 'info',
                    'message' => "Service {$service} has slow response time ({$test['response_time_ms']}ms)",
                    'action' => "Consider optimizing {$service} service or network connection"
                ];
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'All configurations are valid and services are accessible',
                'action' => 'No action needed'
            ];
        }

        return $recommendations;
    }
}
