<?php

declare(strict_types=1);

namespace App\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Customizes Requirements endpoints (Search + Extract)
 */
final class RequirementsDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $this->addSearchEndpoint($openApi);
        $this->customizeExtractEndpoint($openApi);
        $this->setRequirementsTagsForAllPaths($openApi);

        return $openApi;
    }

    private function setRequirementsTagsForAllPaths(OpenApi $openApi): void
    {
        // Alle Pfade, die zu Requirements gehören
        $requirementsPaths = [
            '/api/requirements/extract',
            '/api/requirements/jobs',
            '/api/requirements/jobs/{id}',
            '/api/requirements/search'
        ];

        foreach ($requirementsPaths as $path) {
            $pathItem = $openApi->getPaths()->getPath($path);
            if (!$pathItem) {
                continue;
            }

            $operations = [
                'get' => $pathItem->getGet(),
                'post' => $pathItem->getPost(),
                'put' => $pathItem->getPut(),
                'patch' => $pathItem->getPatch(),
                'delete' => $pathItem->getDelete(),
            ];

            foreach ($operations as $method => $operation) {
                if ($operation) {
                    $newOperation = $operation->withTags(['Requirements']);

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
            '400' => [
                'description' => 'Invalid request - missing or invalid parameters',
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
            '401' => [
                'description' => 'Unauthorized - JWT token required or invalid',
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
            '500' => [
                'description' => 'Internal server error during search',
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
            '503' => [
                'description' => 'Embedding model not available in Ollama',
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

        $requestBody = new RequestBody(
            description: 'Semantic search query with optional filters',
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['query'],
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Natural language query to search for similar requirements',
                                'example' => 'Show me all authentication and security requirements',
                                'minLength' => 3,
                                'maxLength' => 500
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of results to return',
                                'example' => 10,
                                'default' => 10,
                                'minimum' => 1,
                                'maximum' => 100
                            ],
                            'minSimilarity' => [
                                'type' => 'number',
                                'description' => 'Minimum similarity threshold (0.0-1.0)',
                                'example' => 0.7,
                                'default' => 0.0,
                                'minimum' => 0.0,
                                'maximum' => 1.0
                            ],
                            'requirementType' => [
                                'type' => ['string', 'null'],
                                'description' => 'Filter by requirement type',
                                'example' => 'security',
                                'enum' => ['functional', 'non-functional', 'technical', 'business', 'security', 'performance', 'usability', 'other']
                            ],
                            'priority' => [
                                'type' => ['string', 'null'],
                                'description' => 'Filter by priority level (MoSCoW)',
                                'example' => 'must',
                                'enum' => ['must', 'should', 'could', 'wont']
                            ],
                            'status' => [
                                'type' => ['string', 'null'],
                                'description' => 'Filter by requirement status',
                                'example' => 'approved',
                                'enum' => ['draft', 'approved', 'implemented', 'verified', 'rejected', 'obsolete']
                            ]
                        ]
                    ],
                    'examples' => [
                        'general-search' => [
                            'summary' => 'General Natural Language Search',
                            'description' => 'Search using natural language to find semantically similar requirements',
                            'value' => [
                                'query' => 'Show me all authentication and security requirements',
                                'limit' => 10
                            ]
                        ],
                        'keyword-search' => [
                            'summary' => 'Keyword-Based Search',
                            'description' => 'Search for requirements containing specific keywords or concepts',
                            'value' => [
                                'query' => 'Requirements with keyword "encryption" or "data protection"',
                                'limit' => 15,
                                'minSimilarity' => 0.6
                            ]
                        ],
                        'filtered-search' => [
                            'summary' => 'Filtered Search by Type and Priority',
                            'description' => 'Search with filters for requirement type and priority level',
                            'value' => [
                                'query' => 'user interface requirements',
                                'limit' => 20,
                                'requirementType' => 'functional',
                                'priority' => 'must'
                            ]
                        ],
                        'high-precision-search' => [
                            'summary' => 'High-Precision Search',
                            'description' => 'Search with high similarity threshold for very precise matches',
                            'value' => [
                                'query' => 'performance requirements for API response time',
                                'limit' => 5,
                                'minSimilarity' => 0.85,
                                'requirementType' => 'performance',
                                'status' => 'approved'
                            ]
                        ],
                        'broad-exploration' => [
                            'summary' => 'Broad Exploration',
                            'description' => 'Explore related requirements with a lower similarity threshold',
                            'value' => [
                                'query' => 'What requirements are related to user management?',
                                'limit' => 30,
                                'minSimilarity' => 0.5
                            ]
                        ]
                    ]
                ]
            ]),
            required: true
        );

        $operation = new Operation(
            operationId: 'searchRequirements',
            tags: ['Requirements'],
            summary: 'Semantic Search for Requirements',
            description: 'Search for software requirements using natural language queries. Uses AI embeddings for semantic similarity matching.',
            responses: $responses,
            requestBody: $requestBody
        );

        $pathItem = new PathItem(post: $operation);
        $openApi->getPaths()->addPath('/api/requirements/search', $pathItem);
    }

    private function customizeExtractEndpoint(OpenApi $openApi): void
    {
        $pathItem = $openApi->getPaths()->getPath('/api/requirements/extract');
        if (!$pathItem) {
            return;
        }

        $postOperation = $pathItem->getPost();
        if (!$postOperation) {
            return;
        }

        // Add 422 response for validation errors
        $responses = $postOperation->getResponses() ?? [];
        $responses['422'] = [
            'description' => 'Validation error - Invalid input data',
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
        ];

        $requestBody = new RequestBody(
            description: 'Requirements extraction request - supports three document source methods',
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['projectName'],
                        'properties' => [
                            'projectName' => [
                                'type' => 'string',
                                'description' => 'Name of the project/software being analyzed',
                                'example' => 'E-Commerce Platform v2.0',
                                'minLength' => 3,
                                'maxLength' => 255
                            ],
                            'fileContent' => [
                                'type' => ['string', 'null'],
                                'description' => 'Base64-encoded document content (PDF, DOCX, TXT, MD)',
                                'example' => 'JVBERi0xLjQKJeLjz9MKMSAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgMiAwIFI+PgplbmRvYmoK...'
                            ],
                            'fileName' => [
                                'type' => ['string', 'null'],
                                'description' => 'Original filename (required if fileContent is provided)',
                                'example' => 'requirements-specification.pdf'
                            ],
                            'mimeType' => [
                                'type' => ['string', 'null'],
                                'description' => 'MIME type of the uploaded file',
                                'example' => 'application/pdf',
                                'enum' => [
                                    'application/pdf',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'text/plain',
                                    'text/markdown',
                                    'application/msword'
                                ]
                            ],
                            'documentUrl' => [
                                'type' => ['string', 'null'],
                                'description' => 'Public URL to download the document from',
                                'example' => 'https://example.com/docs/requirements.pdf',
                                'format' => 'uri'
                            ],
                            'serverPath' => [
                                'type' => ['string', 'null'],
                                'description' => 'Server file path (only for backend/admin use)',
                                'example' => '/var/www/uploads/requirements-doc.pdf'
                            ],
                            'llmModel' => [
                                'type' => 'string',
                                'description' => 'LLM model to use for extraction',
                                'example' => 'llama3.2',
                                'enum' => ['llama3.2', 'llama3.1', 'mistral', 'codellama'],
                                'default' => 'llama3.2'
                            ],
                            'temperature' => [
                                'type' => 'number',
                                'description' => 'Temperature for LLM (0.0 = deterministic, 1.0 = creative)',
                                'example' => 0.7,
                                'minimum' => 0.0,
                                'maximum' => 1.0,
                                'default' => 0.7
                            ],
                            'async' => [
                                'type' => 'boolean',
                                'description' => 'Process extraction asynchronously (recommended for large documents)',
                                'example' => true,
                                'default' => true
                            ]
                        ]
                    ],
                    'examples' => [
                        'file-upload' => [
                            'summary' => 'Option 1: File Upload (Base64)',
                            'description' => 'Upload a document as base64-encoded content - recommended for frontend applications',
                            'value' => [
                                'projectName' => 'E-Commerce Platform v2.0',
                                'fileContent' => 'JVBERi0xLjQKJeLjz9MKMSAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgMiAwIFI+PgplbmRvYmoK...',
                                'fileName' => 'requirements-specification.pdf',
                                'mimeType' => 'application/pdf',
                                'llmModel' => 'llama3.2',
                                'temperature' => 0.7,
                                'async' => true
                            ]
                        ],
                        'url-download' => [
                            'summary' => 'Option 2: URL Download',
                            'description' => 'Provide a public URL from which the document will be downloaded',
                            'value' => [
                                'projectName' => 'Mobile Banking App',
                                'documentUrl' => 'https://example.com/docs/requirements.pdf',
                                'llmModel' => 'llama3.2',
                                'temperature' => 0.7,
                                'async' => true
                            ]
                        ],
                        'server-path' => [
                            'summary' => 'Option 3: Server Path (Admin)',
                            'description' => 'Use a file already present on the server - only for backend/admin use',
                            'value' => [
                                'projectName' => 'Internal Tool Suite',
                                'serverPath' => '/home/david/projects/raginald/public/shared/example_Use_Cases_konsolidiert.xlsx',
                                'llmModel' => 'llama3.2',
                                'temperature' => 0.5,
                                'async' => true
                            ]
                        ]
                    ]
                ]
            ]),
            required: true
        );

        $newOperation = $postOperation
            ->withRequestBody($requestBody)
            ->withResponses($responses)
            ->withSummary('Extract Requirements from Document')
            ->withDescription('Extracts software requirements from a document using AI/LLM. Supports three upload methods: Base64 file upload, URL download, or server file path.');

        $pathItem = $pathItem->withPost($newOperation);
        $openApi->getPaths()->addPath('/api/requirements/extract', $pathItem);
    }
}

