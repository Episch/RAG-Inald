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
    public function __construct(
        private ConfigurationManager $configManager
    ) {}

    public function __invoke(): JsonResponse
    {
        $configReport = $this->configManager->getConfigReport();
        
        return new JsonResponse([
            'configuration' => $configReport,
            'timestamp' => date('c'),
            'environment' => $_ENV['APP_ENV'] ?? 'prod'
        ], 200);
    }
}
