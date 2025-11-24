<?php

declare(strict_types=1);

namespace App\OpenApi\Decorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Manages OpenAPI tags (ordering, descriptions, renaming)
 */
final class TagsDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi = $this->defineTagsWithDescriptions($openApi);
        $openApi = $this->sortTags($openApi);

        return $openApi;
    }

    private function defineTagsWithDescriptions(OpenApi $openApi): OpenApi
    {
        $tags = [
            new Tag(
                name: 'Authentication',
                description: 'User authentication and JWT token management'
            ),
            new Tag(
                name: 'Requirements',
                description: 'Software requirements management, extraction, and semantic search'
            ),
            new Tag(
                name: 'System',
                description: 'System health checks and service status'
            ),
        ];

        return $openApi->withTags($tags);
    }

    private function sortTags(OpenApi $openApi): OpenApi
    {
        $tags = $openApi->getTags();
        usort($tags, fn ($a, $b) => $a->getName() <=> $b->getName());
        return $openApi->withTags($tags);
    }
}

