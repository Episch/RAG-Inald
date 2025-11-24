<?php

declare(strict_types=1);

namespace App\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Adds System endpoints (Health + Models)
 */
final class SystemEndpointsDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $this->addHealthEndpoint($openApi);
        $this->addModelsEndpoint($openApi);

        return $openApi;
    }

    private function addHealthEndpoint(OpenApi $openApi): void
    {
        $responses = [
            '200' => [
                'description' => 'Service is healthy',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'example' => 'healthy'],
                                'services' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'tika' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'up'],
                                                'description' => ['type' => 'string', 'example' => 'Apache Tika Document Extractor']
                                            ]
                                        ],
                                        'ollama' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'up'],
                                                'description' => ['type' => 'string', 'example' => 'Ollama LLM Service'],
                                                'models' => ['type' => 'array', 'items' => ['type' => 'string']]
                                            ]
                                        ],
                                        'neo4j' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'up'],
                                                'description' => ['type' => 'string', 'example' => 'Neo4j Graph Database']
                                            ]
                                        ],
                                        'messenger' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'up'],
                                                'description' => ['type' => 'string', 'example' => 'Symfony Messenger Queue']
                                            ]
                                        ]
                                    ]
                                ],
                                'timestamp' => ['type' => 'string', 'example' => '2024-01-01T12:00:00+00:00']
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
            ],
            '503' => [
                'description' => 'One or more services are down',
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
            operationId: 'getHealth',
            tags: ['System'],
            summary: 'Health Check',
            description: 'Check the status of all backend services (Tika, Ollama, Neo4j, Messenger Queue)',
            responses: $responses,
            security: [] // Public endpoint - no authentication required
        );

        $pathItem = new PathItem(get: $operation);
        $openApi->getPaths()->addPath('/api/health', $pathItem);
    }

    private function addModelsEndpoint(OpenApi $openApi): void
    {
        $responses = [
            '200' => [
                'description' => 'List of available LLM models',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'models' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'examples' => [['llama3.2', 'nomic-embed-text']]
                                ],
                                'default_model' => ['type' => 'string', 'examples' => ['llama3.2']],
                                'embedding_model' => ['type' => 'string', 'examples' => ['nomic-embed-text']]
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
            ],
            '500' => [
                'description' => 'Failed to list models',
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
            operationId: 'getModels',
            tags: ['System'],
            summary: 'List Available Models',
            description: 'Get a list of all available LLM models in Ollama',
            responses: $responses,
            security: [] // Public endpoint - no authentication required
        );

        $pathItem = new PathItem(get: $operation);
        $openApi->getPaths()->addPath('/api/models', $pathItem);
    }
}

