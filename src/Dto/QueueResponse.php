<?php

namespace App\Dto;

use App\Dto\Base\AbstractDto;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Standardized DTO for all message queue responses
 * Implements JsonSerializable to avoid PropertyInfo deprecation warnings
 */
class QueueResponse extends AbstractDto implements \JsonSerializable
{
    #[Groups(['read'])]
    public string $status = 'queued';

    #[Groups(['read'])]
    public string $requestId;

    #[Groups(['read'])]
    public ?int $queueCount = null;

    #[Groups(['read'])]
    public string $estimatedProcessingTime;

    #[Groups(['read'])]
    public string $resultAvailableAt;

    #[Groups(['read'])]
    public string $operationType;

    #[Groups(['read'])]
    public array $requestData = [];

    #[Groups(['read'])]
    public array $metadata = [];

    public function __construct(
        string $requestId,
        string $operationType,
        array $requestData = [],
        ?int $queueCount = null,
        string $estimatedProcessingTime = '30 seconds',
        array $metadata = []
    ) {
        $this->requestId = $requestId;
        $this->operationType = $operationType;
        $this->requestData = $requestData;
        $this->queueCount = $queueCount;
        $this->estimatedProcessingTime = $estimatedProcessingTime;
        $this->resultAvailableAt = "/api/{$operationType}/result/{$requestId}";
        $this->metadata = $metadata;
    }

    /**
     * Factory method for LLM generation responses
     */
    public static function createLlmResponse(
        string $requestId,
        string $model,
        int $promptLength,
        ?int $queueCount = null,
        string $estimatedTime = '50 seconds'
    ): self {
        return new self(
            requestId: $requestId,
            operationType: 'llm',
            requestData: [
                'model' => $model,
                'prompt_tokens' => $promptLength
            ],
            queueCount: $queueCount,
            estimatedProcessingTime: $estimatedTime,
            metadata: [
                'pipeline' => 'LLM Generation',
                'async_processing' => true,
                'token_info' => 'Tokens calculated using tiktoken encoder',
                'tokenizer_note' => 'Non-OpenAI models use GPT-3.5-turbo tokenizer for approximation'
            ]
        );
    }

    /**
     * Factory method for document extraction responses
     */
    public static function createExtractionResponse(
        string $requestId,
        string $path,
        ?int $queueCount = null,
        string $estimatedTime = '15 seconds'
    ): self {
        return new self(
            requestId: $requestId,
            operationType: 'extraction',
            requestData: [
                'path' => $path,
                'document_type' => self::guessDocumentType($path)
            ],
            queueCount: $queueCount,
            estimatedProcessingTime: $estimatedTime,
            metadata: [
                'pipeline' => 'Document Extraction → Tika Processing → LLM Categorization',
                'stages' => ['tika_extraction', 'text_optimization', 'llm_categorization'],
                'async_processing' => true
            ]
        );
    }

    /**
     * Factory method for graph indexing responses
     */
    public static function createIndexingResponse(
        string $requestId,
        string $entityType,
        string $operation,
        int $entityCount,
        ?int $queueCount = null,
        string $estimatedTime = '10 seconds'
    ): self {
        return new self(
            requestId: $requestId,
            operationType: 'indexing',
            requestData: [
                'entity_type' => $entityType,
                'operation' => $operation,
                'entity_count' => $entityCount
            ],
            queueCount: $queueCount,
            estimatedProcessingTime: $estimatedTime,
            metadata: [
                'pipeline' => 'Graph Indexing → Neo4j Storage',
                'database' => 'Neo4j',
                'async_processing' => true,
                'supported_operations' => ['create', 'update', 'merge', 'delete']
            ]
        );
    }

    /**
     * Factory method for generic queue responses
     */
    public static function createGeneric(
        string $requestId,
        string $operationType,
        array $requestData = [],
        ?int $queueCount = null,
        string $estimatedTime = '30 seconds',
        array $metadata = []
    ): self {
        return new self(
            requestId: $requestId,
            operationType: $operationType,
            requestData: $requestData,
            queueCount: $queueCount,
            estimatedProcessingTime: $estimatedTime,
            metadata: array_merge([
                'async_processing' => true,
                'created_at' => date('c')
            ], $metadata)
        );
    }

    /**
     * Guess document type from path for metadata
     */
    private static function guessDocumentType(string $path): string
    {
        if (str_contains(strtolower($path), 'pdf')) return 'PDF';
        if (str_contains(strtolower($path), 'doc')) return 'Word Document';
        if (str_contains(strtolower($path), 'txt')) return 'Text File';
        if (str_contains(strtolower($path), 'md')) return 'Markdown';
        
        return 'Unknown';
    }

    /**
     * JsonSerializable implementation - avoids PropertyInfo deprecation warnings
     * by providing explicit serialization without reflection
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'requestId' => $this->requestId,
            'queueCount' => $this->queueCount,
            'estimatedProcessingTime' => $this->estimatedProcessingTime,
            'resultAvailableAt' => $this->resultAvailableAt,
            'operationType' => $this->operationType,
            'requestData' => $this->requestData,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Convert DTO to array for JSON serialization
     * This avoids PropertyInfo deprecation warnings by bypassing reflection
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    // Getters for API Platform
    public function getStatus(): string { return $this->status; }
    public function getRequestId(): string { return $this->requestId; }
    public function getQueueCount(): ?int { return $this->queueCount; }
    public function getEstimatedProcessingTime(): string { return $this->estimatedProcessingTime; }
    public function getResultAvailableAt(): string { return $this->resultAvailableAt; }
    public function getOperationType(): string { return $this->operationType; }
    public function getRequestData(): array { return $this->requestData; }
    public function getMetadata(): array { return $this->metadata; }

    // Setters for flexibility
    public function setQueueCount(?int $queueCount): self { $this->queueCount = $queueCount; return $this; }
    public function setEstimatedProcessingTime(string $time): self { $this->estimatedProcessingTime = $time; return $this; }
    public function addMetadata(string $key, mixed $value): self { $this->metadata[$key] = $value; return $this; }
}
