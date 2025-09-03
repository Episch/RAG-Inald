<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dto\AdminConfigStatus;
use App\Service\ConfigurationManager;
use Symfony\Component\HttpFoundation\JsonResponse;

#[ApiResource(
    shortName: 'Monitoring',
    operations: [
        new Get(
            uriTemplate: '/admin/config/status',
            controller: AdminConfigStatusController::class,
            description: 'Detailed system configuration status and validation report. Requires admin authentication.',
            normalizationContext: ['groups' => ['admin:read']],
            output: AdminConfigStatus::class,
            security: "is_granted('ROLE_ADMIN')"
        )
    ]
)]
class AdminConfigStatusController
{
    /**
     * Initialize admin config status controller.
     * 
     * @param ConfigurationManager $configManager System configuration manager
     */
    public function __construct(
        private readonly ConfigurationManager $configManager
    ) {}

    /**
     * Get detailed system configuration status.
     * 
     * @return JsonResponse Configuration report and validation results
     */
    public function __invoke(): JsonResponse
    {
        $configReport = $this->configManager->getConfigReport();
        
        return new JsonResponse([
            'configuration' => $configReport,
            'timestamp' => date('c'),
            'environment' => $_ENV['APP_ENV'] ?? 'prod',
            'debug_endpoints' => [
                'queue_analysis' => '/admin/debug/queue',
                'ollama_diagnostics' => '/admin/debug/ollama'
            ]
        ], 200);
    }
}
