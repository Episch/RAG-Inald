<?php

declare(strict_types=1);

namespace App\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
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
        $this->addTokenRefreshEndpoint($openApi);
        $this->addTokenRevokeEndpoint($openApi);
        $this->addTokenRevokeAllEndpoint($openApi);
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

        $responses = [
            '200' => [
                'description' => 'Login successful - returns JWT token and refresh token',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'token' => [
                                    'type' => 'string',
                                    'description' => 'JWT access token (valid for 1 hour)',
                                    'example' => 'eyJ0eXAiOiJKV1QiLCJhbGc...'
                                ],
                                'refresh_token' => [
                                    'type' => 'string',
                                    'description' => 'Refresh token for obtaining new access tokens (valid for 7 days)',
                                    'example' => 'abc123def456...'
                                ],
                                'user' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => ['type' => 'string', 'example' => 'admin@example.com'],
                                        'roles' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                            'example' => ['ROLE_ADMIN', 'ROLE_USER']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'headers' => [
                    'X-RateLimit-Limit' => [
                        'description' => 'Request limit per time window',
                        'schema' => ['type' => 'integer', 'example' => 3]
                    ],
                    'X-RateLimit-Remaining' => [
                        'description' => 'Remaining requests in current window',
                        'schema' => ['type' => 'integer', 'example' => 2]
                    ],
                    'X-RateLimit-Reset' => [
                        'description' => 'Unix timestamp when limit resets',
                        'schema' => ['type' => 'integer', 'example' => 1732550400]
                    ]
                ]
            ],
            '401' => [
                'description' => 'Invalid credentials',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            '429' => [
                'description' => 'Too Many Requests - Rate limit exceeded (see X-RateLimit-* headers in 200 response)',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string', 'example' => 'Too Many Requests'],
                                'message' => ['type' => 'string', 'example' => 'Rate limit exceeded. Please try again later.'],
                                'retry_after' => ['type' => 'integer', 'example' => 1732550400]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $newOperation = new Operation(
            operationId: $operation->getOperationId() ?? 'postCredentialsItem',
            tags: ['Authentication'],
            responses: $responses,
            summary: 'User Login - Get JWT Tokens',
            description: 'Authenticate with username and password to receive JWT access token and refresh token. Use the access token in the Authorization header as "Bearer {token}". Use the refresh token to obtain new access tokens when they expire.',
            externalDocs: $operation->getExternalDocs(),
            parameters: $operation->getParameters() ?? [],
            requestBody: $requestBody,
            callbacks: $operation->getCallbacks(),
            deprecated: $operation->getDeprecated() ?? false,
            security: [],
            servers: $operation->getServers(),
            extensionProperties: $operation->getExtensionProperties() ?? []
        );

        $pathItem = $pathItem->withPost($newOperation);
        $openApi->getPaths()->addPath('/api/login', $pathItem);
    }

    private function addTokenRefreshEndpoint(OpenApi $openApi): void
    {
        $requestBody = new RequestBody(
            description: 'Refresh Token Request',
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['refresh_token'],
                        'properties' => [
                            'refresh_token' => [
                                'type' => 'string',
                                'description' => 'The refresh token received during login',
                                'example' => 'abc123def456...'
                            ],
                            'rotate' => [
                                'type' => 'boolean',
                                'description' => 'Whether to rotate the refresh token (recommended)',
                                'default' => true,
                                'example' => true
                            ]
                        ]
                    ]
                ]
            ]),
            required: true
        );

        $responses = [
            '200' => [
                'description' => 'Token refreshed successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'token' => ['type' => 'string', 'description' => 'New JWT access token'],
                                'refresh_token' => ['type' => 'string', 'description' => 'New refresh token (if rotated)'],
                                'user' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => ['type' => 'string'],
                                        'roles' => ['type' => 'array', 'items' => ['type' => 'string']]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'headers' => [
                    'X-RateLimit-Limit' => [
                        'description' => 'Request limit per time window',
                        'schema' => ['type' => 'integer']
                    ],
                    'X-RateLimit-Remaining' => [
                        'description' => 'Remaining requests in current window',
                        'schema' => ['type' => 'integer']
                    ],
                    'X-RateLimit-Reset' => [
                        'description' => 'Unix timestamp when limit resets',
                        'schema' => ['type' => 'integer']
                    ]
                ]
            ],
            '401' => [
                'description' => 'Invalid or expired refresh token',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            '429' => [
                'description' => 'Too Many Requests - Rate limit exceeded',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'retry_after' => ['type' => 'integer']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $operation = new Operation(
            operationId: 'refreshToken',
            tags: ['Authentication'],
            summary: 'Refresh JWT Access Token',
            description: 'Exchange a refresh token for a new access token. Optionally rotates the refresh token for enhanced security.',
            responses: $responses,
            requestBody: $requestBody
        );

        $pathItem = new PathItem(post: $operation);
        $openApi->getPaths()->addPath('/api/token/refresh', $pathItem);
    }

    private function addTokenRevokeEndpoint(OpenApi $openApi): void
    {
        $requestBody = new RequestBody(
            description: 'Revoke Token Request',
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['refresh_token'],
                        'properties' => [
                            'refresh_token' => [
                                'type' => 'string',
                                'description' => 'The refresh token to revoke',
                                'example' => 'abc123def456...'
                            ]
                        ]
                    ]
                ]
            ]),
            required: true
        );

        $responses = [
            '200' => [
                'description' => 'Token revoked successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'Refresh token revoked successfully']
                            ]
                        ]
                    ]
                ],
                'headers' => [
                    'X-RateLimit-Limit' => [
                        'description' => 'Request limit per time window',
                        'schema' => ['type' => 'integer']
                    ],
                    'X-RateLimit-Remaining' => [
                        'description' => 'Remaining requests in current window',
                        'schema' => ['type' => 'integer']
                    ],
                    'X-RateLimit-Reset' => [
                        'description' => 'Unix timestamp when limit resets',
                        'schema' => ['type' => 'integer']
                    ]
                ]
            ],
            '404' => [
                'description' => 'Token not found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            '429' => [
                'description' => 'Too Many Requests - Rate limit exceeded',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'retry_after' => ['type' => 'integer']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $operation = new Operation(
            operationId: 'revokeToken',
            tags: ['Authentication'],
            summary: 'Revoke Refresh Token',
            description: 'Revoke a specific refresh token, preventing it from being used to obtain new access tokens.',
            responses: $responses,
            requestBody: $requestBody
        );

        $pathItem = new PathItem(post: $operation);
        $openApi->getPaths()->addPath('/api/token/revoke', $pathItem);
    }

    private function addTokenRevokeAllEndpoint(OpenApi $openApi): void
    {
        $responses = [
            '200' => [
                'description' => 'All tokens revoked successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'All refresh tokens revoked successfully'],
                                'revoked_count' => ['type' => 'integer', 'example' => 3]
                            ]
                        ]
                    ]
                ]
            ],
            '401' => [
                'description' => 'Unauthorized - JWT token required',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            '403' => [
                'description' => 'Forbidden - Admin role required',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $operation = new Operation(
            operationId: 'revokeAllTokens',
            tags: ['Authentication'],
            summary: 'Revoke All User Refresh Tokens (Admin Only)',
            description: 'Revoke all refresh tokens for the authenticated user. Requires ROLE_ADMIN. Useful for "logout from all devices" functionality.',
            responses: $responses
        );

        $pathItem = new PathItem(post: $operation);
        $openApi->getPaths()->addPath('/api/token/revoke-all', $pathItem);
    }

    private function applyJwtSecurity(OpenApi $openApi): void
    {
        $jwtSecurity = [['JWT' => []]];
        $noSecurity = [];
        
        // Public endpoints that don't require authentication
        $publicPaths = [
            '/api/login',          // Authentication endpoint
            '/api/token/refresh',  // Token refresh (uses refresh token)
            '/api/token/revoke',   // Token revocation (public)
            '/api/health',         // System health check
            '/api/models',         // Available models list
            '/api/docs'            // API documentation
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

