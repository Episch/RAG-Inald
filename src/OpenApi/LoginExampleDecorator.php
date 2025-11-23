<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Decorator to customize OpenAPI documentation
 * - Login endpoint example payload
 * - Add custom controller endpoints (health, models)
 */
final class LoginExampleDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // JWT security scheme is now automatically handled by api_platform.yaml configuration
        // No need to manually define it here

        // 1. Customize the /api/login endpoint
        $this->customizeLoginEndpoint($openApi);

        // 2. Add /api/health endpoint
        $this->addHealthEndpoint($openApi);

        // 3. Add /api/models endpoint
        $this->addModelsEndpoint($openApi);

        // 4. Add /api/requirements/search endpoint
        $this->addSearchEndpoint($openApi);

        // 5. Ensure JWT security is applied to all protected endpoints
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

        // Get existing request body or create new one
        $existingRequestBody = $operation->getRequestBody();
        
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
                            'example' => 'admin123',
                            'description' => 'Password for authentication'
                        ]
                    ],
                    'required' => ['username', 'password']
                ],
                'example' => [
                    'username' => 'admin',
                    'password' => 'admin123'
                ]
            ]
        ];

        $requestBody = new RequestBody(
            description: 'User credentials for JWT authentication',
            content: new \ArrayObject($content),
            required: true
        );

        // Create new operation with updated request body
        $newOperation = new Operation(
            operationId: $operation->getOperationId() ?? 'postCredentialsItem',
            tags: $operation->getTags() ?? ['Login Check'],
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
                                                'description' => ['type' => 'string', 'example' => 'Ollama LLM Service']
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
                                                'description' => ['type' => 'string', 'example' => 'Message Queue for Async Processing'],
                                                'transport' => ['type' => 'string', 'example' => 'in-memory'],
                                                'info' => ['type' => 'string', 'example' => 'In-Memory Queue (Development)']
                                            ]
                                        ]
                                    ]
                                ],
                                'timestamp' => ['type' => 'string', 'format' => 'date-time']
                            ]
                        ]
                    ]
                ]
            ],
            '503' => [
                'description' => 'One or more services are down'
            ]
        ];

        $operation = new Operation(
            operationId: 'getHealth',
            tags: ['System'],
            summary: 'Health Check',
            description: 'Check the status of all backend services (Tika, Ollama, Neo4j, Messenger Queue)',
            responses: $responses
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
                                    'example' => ['llama3.2', 'nomic-embed-text']
                                ],
                                'default_model' => ['type' => 'string', 'example' => 'llama3.2'],
                                'embedding_model' => ['type' => 'string', 'example' => 'nomic-embed-text']
                            ]
                        ]
                    ]
                ]
            ],
            '500' => [
                'description' => 'Failed to list models'
            ]
        ];

        $operation = new Operation(
            operationId: 'getModels',
            tags: ['System'],
            summary: 'List Available Models',
            description: 'Get a list of all available LLM models in Ollama',
            responses: $responses
        );

        $pathItem = new PathItem(get: $operation);
        $openApi->getPaths()->addPath('/api/models', $pathItem);
    }

    private function addSearchEndpoint(OpenApi $openApi): void
    {
        $responses = [
            '200' => [
                'description' => 'Search results with similar requirements',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string', 'example' => 'authentication requirements'],
                                'results' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'requirement' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'identifier' => ['type' => 'string'],
                                                    'name' => ['type' => 'string'],
                                                    'description' => ['type' => 'string'],
                                                    'requirementType' => ['type' => 'string'],
                                                    'priority' => ['type' => 'string']
                                                ]
                                            ],
                                            'similarity' => ['type' => 'number', 'example' => 0.87]
                                        ]
                                    ]
                                ],
                                'count' => ['type' => 'integer', 'example' => 5],
                                'limit' => ['type' => 'integer', 'example' => 10],
                                'duration_seconds' => ['type' => 'number', 'example' => 0.234]
                            ]
                        ]
                    ]
                ]
            ],
            '400' => ['description' => 'Invalid request'],
            '401' => ['description' => 'Unauthorized - JWT token required'],
            '500' => ['description' => 'Search failed']
        ];

        $requestBody = new RequestBody(
            description: 'Semantic search query',
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'example' => 'Show me all authentication and security requirements',
                                'description' => 'Natural language query to search for similar requirements'
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'example' => 10,
                                'default' => 10,
                                'minimum' => 1,
                                'maximum' => 100,
                                'description' => 'Maximum number of results to return'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ]),
            required: true
        );

        $operation = new Operation(
            operationId: 'searchRequirements',
            tags: ['Requirements'],
            summary: 'Semantic Search for Requirements',
            description: 'Search for similar requirements using natural language queries. The query is converted to an embedding and matched against stored requirements using cosine similarity.',
            responses: $responses,
            requestBody: $requestBody
        );

        $pathItem = new PathItem(post: $operation);
        $openApi->getPaths()->addPath('/api/requirements/search', $pathItem);
    }

    private function applyJwtSecurity(OpenApi $openApi): void
    {
        // IMPORTANT: security must be an ARRAY of security requirements
        $jwtSecurity = [['JWT' => []]];  // Protected: Array containing JWT requirement
        $noSecurity = [];  // Public: Empty array means NO authentication required
        
        // Paths that should NOT require authentication (public routes)
        $publicPaths = ['/api/login', '/api/health', '/api/models', '/api/docs'];
        
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            // Determine if this path is public or protected
            $isPublic = in_array($path, $publicPaths);
            $security = $isPublic ? $noSecurity : $jwtSecurity;
            
            // Apply security to all operations in this path
            $operations = [
                'get' => $pathItem->getGet(),
                'post' => $pathItem->getPost(),
                'put' => $pathItem->getPut(),
                'patch' => $pathItem->getPatch(),
                'delete' => $pathItem->getDelete(),
            ];
            
            foreach ($operations as $method => $operation) {
                if ($operation) {
                    // Explicitly set security (either empty for public or JWT for protected)
                    $newOperation = $operation->withSecurity($security);
                    $pathItem = match($method) {
                        'get' => $pathItem->withGet($newOperation),
                        'post' => $pathItem->withPost($newOperation),
                        'put' => $pathItem->withPut($newOperation),
                        'patch' => $pathItem->withPatch($newOperation),
                        'delete' => $pathItem->withDelete($newOperation),
                    };
                }
            }
            
            $openApi->getPaths()->addPath($path, $pathItem);
        }
    }
}

