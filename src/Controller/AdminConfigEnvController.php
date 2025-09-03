<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\HttpFoundation\JsonResponse;

#[ApiResource(
    shortName: 'AdminMonitoring',
    operations: [
        new Get(
            uriTemplate: '/admin/config/env',
            controller: AdminConfigEnvController::class,
            description: 'Environment variables, system information and PHP configuration. Contains sensitive data - admin only.',
            normalizationContext: ['groups' => ['admin:read']],
            output: false,
            security: "is_granted('ROLE_ADMIN')"
        )
    ]
)]
class AdminConfigEnvController
{
    public function __invoke(): JsonResponse
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
}
