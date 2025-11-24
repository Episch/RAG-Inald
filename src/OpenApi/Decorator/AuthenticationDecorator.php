<?php

declare(strict_types=1);

namespace App\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Customizes authentication endpoints (Login + JWT Security)
 */
final class AuthenticationDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $this->customizeLoginEndpoint($openApi);
        $this->applyJwtSecurity($openApi);

        return $openApi;
    }

    private function customizeLoginEndpoint(OpenApi $openApi): void
    {
        $pathItem = $openApi->getPaths()->getPath('/api/login');
        
        if (!$pathItem) {
            return;
        }

        $operation = $pathItem->getPost();
        
        if (!$operation) {
            return;
        }

        $content = [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'username' => [
                            'type' => 'string',
                            'example' => 'admin',
                            'description' => 'Username for authentication'
                        ],
                        'password' => [
                            'type' => 'string',
                            'example' => 'password',
                            'description' => 'Password for authentication'
                        ],
                    ],
                    'required' => ['username', 'password']
                ],
                'example' => [
                    'username' => 'admin',
                    'password' => 'password'
                ]
            ]
        ];

        $requestBody = new RequestBody(
            description: 'User credentials for JWT authentication',
            content: new \ArrayObject($content),
            required: true
        );

        $newOperation = new Operation(
            operationId: $operation->getOperationId() ?? 'postCredentialsItem',
            tags: ['Authentication'],
            responses: $operation->getResponses() ?? [],
            summary: $operation->getSummary() ?? 'Creates a user token.',
            description: $operation->getDescription() ?? 'Creates a JWT token for API authentication. Use the returned token in the Authorization header as "Bearer {token}".',
            externalDocs: $operation->getExternalDocs(),
            parameters: $operation->getParameters() ?? [],
            requestBody: $requestBody,
            callbacks: $operation->getCallbacks(),
            deprecated: $operation->getDeprecated() ?? false,
            security: $operation->getSecurity(),
            servers: $operation->getServers(),
            extensionProperties: $operation->getExtensionProperties() ?? []
        );

        $pathItem = $pathItem->withPost($newOperation);
        $openApi->getPaths()->addPath('/api/login', $pathItem);
    }

    private function applyJwtSecurity(OpenApi $openApi): void
    {
        $jwtSecurity = [['JWT' => []]];
        $noSecurity = [];
        
        // Public endpoints that don't require authentication
        $publicPaths = [
            '/api/login',    // Authentication endpoint
            '/api/health',   // System health check
            '/api/models',   // Available models list
            '/api/docs'      // API documentation
        ];
        
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            $isPublic = in_array($path, $publicPaths);
            $security = $isPublic ? $noSecurity : $jwtSecurity;
            
            $operations = [
                'get' => $pathItem->getGet(),
                'post' => $pathItem->getPost(),
                'put' => $pathItem->getPut(),
                'patch' => $pathItem->getPatch(),
                'delete' => $pathItem->getDelete(),
            ];
            
            foreach ($operations as $method => $operation) {
                if ($operation) {
                    $newOperation = $operation->withSecurity($security);
                    
                    $pathItem = match($method) {
                        'get' => $pathItem->withGet($newOperation),
                        'post' => $pathItem->withPost($newOperation),
                        'put' => $pathItem->withPut($newOperation),
                        'patch' => $pathItem->withPatch($newOperation),
                        'delete' => $pathItem->withDelete($newOperation),
                        default => $pathItem,
                    };
                }
            }
            
            $openApi->getPaths()->addPath($path, $pathItem);
        }
    }
}

