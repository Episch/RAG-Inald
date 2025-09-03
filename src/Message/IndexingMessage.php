<?php

namespace App\Message;

/**
 * Message for asynchronous Neo4j entity indexing
 */
class IndexingMessage
{
    public function __construct(
        public readonly string $entityType,
        public readonly array $entityData,
        public readonly array $relationships = [],
        public readonly array $metadata = [],
        public readonly string $operation = 'merge',
        public readonly array $indexes = [],
        public readonly bool $useLlmFile = false,
        public readonly string $llmFileId = ''
    ) {}
}
