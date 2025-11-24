<?php

declare(strict_types=1);

namespace App\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Adds global error responses (415, 422) to write operations
 */
final class ErrorResponsesDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $this->add415ErrorToWriteOperations($openApi);

        return $openApi;
    }

    private function add415ErrorToWriteOperations(OpenApi $openApi): void
    {
        $error415Response = [
            'description' => 'Unsupported Media Type - Invalid Content-Type header',
            'content' => [
                'application/ld+json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            '@context' => [
                                'type' => 'string',
                                'example' => '/api/contexts/Error'
                            ],
                            '@id' => [
                                'type' => 'string',
                                'example' => '/api/errors/415'
                            ],
                            '@type' => [
                                'type' => 'string',
                                'example' => 'Error'
                            ],
                            'type' => [
                                'type' => 'string',
                                'example' => '/errors/415',
                                'description' => 'A URI reference that identifies the error type'
                            ],
                            'title' => [
                                'type' => 'string',
                                'example' => 'An error occurred',
                                'description' => 'A short, human-readable summary of the error'
                            ],
                            'detail' => [
                                'type' => 'string',
                                'example' => 'The content-type "text/plain" is not supported. Supported MIME types are "application/json", "application/ld+json".',
                                'description' => 'A human-readable explanation of the error'
                            ],
                            'status' => [
                                'type' => 'integer',
                                'example' => 415,
                                'description' => 'The HTTP status code'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Add 415 error to all POST, PUT, PATCH operations
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            $writeOperations = [
                'post' => $pathItem->getPost(),
                'put' => $pathItem->getPut(),
                'patch' => $pathItem->getPatch(),
            ];

            foreach ($writeOperations as $method => $operation) {
                if ($operation) {
                    $responses = $operation->getResponses() ?? [];
                    
                    // Only add 415 if not already present
                    if (!isset($responses['415'])) {
                        $responses['415'] = $error415Response;
                        $newOperation = $operation->withResponses($responses);

                        $pathItem = match($method) {
                            'post' => $pathItem->withPost($newOperation),
                            'put' => $pathItem->withPut($newOperation),
                            'patch' => $pathItem->withPatch($newOperation),
                            default => $pathItem,
                        };
                    }
                }
            }

            $openApi->getPaths()->addPath($path, $pathItem);
        }
    }
}

