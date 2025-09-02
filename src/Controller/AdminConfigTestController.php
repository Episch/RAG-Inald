<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Service\ConfigurationManager;
use App\Service\Factory\ConnectorFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

#[ApiResource(
    shortName: 'Monitoring',
    operations: [
        new Get(
            uriTemplate: '/admin/config/test',
            controller: AdminConfigTestController::class,
            description: 'Test all service connections and configurations. Includes performance metrics. Requires admin authentication.',
            normalizationContext: ['groups' => ['admin:read']],
            output: false,
            security: "is_granted('ROLE_ADMIN')"
        )
    ]
)]
class AdminConfigTestController
{
    public function __construct(
        private ConfigurationManager $configManager,
        private ConnectorFactory $connectorFactory
    ) {}

    public function __invoke(): JsonResponse
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
