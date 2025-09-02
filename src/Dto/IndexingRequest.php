<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\IndexingController;
use App\Dto\Base\AbstractDto;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** 
 * Iindexing various entities/nodes into Neo4j
 */
#[ApiResource(
    shortName: 'GraphIndexing',
    operations: [
        new Post(
            uriTemplate: '/indexing',
            controller: IndexingController::class,
            description: 'Submit flexible entities for asynchronous indexing into Neo4j graph database. Supports various entity types (Person, Document, Organization), relationships, CRUD operations (create/update/merge/delete), and automatic index creation.',
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['write']],
            input: IndexingRequest::class
        )
    ]
)]
class IndexingRequest extends AbstractDto
{
    #[Groups(['write', 'read'])]
    #[Assert\NotBlank(message: "Entity type is required")]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z][a-zA-Z0-9_]*$/',
        message: 'Entity type must be alphanumeric and start with a letter'
    )]
    public string $entityType = '';

    #[Groups(['write', 'read'])]
    #[Assert\NotBlank(message: "Entity data is required")]
    #[Assert\Type('array', message: 'Entity data must be an array')]
    public array $entityData = [];

    #[Groups(['write', 'read'])]
    public array $relationships = [];

    #[Groups(['write', 'read'])]
    public array $metadata = [];

    #[Groups(['write', 'read'])]
    #[Assert\Choice(
        choices: ['create', 'update', 'merge', 'delete'],
        message: 'Operation must be one of: create, update, merge, delete'
    )]
    public string $operation = 'merge';

    #[Groups(['write', 'read'])]
    public array $indexes = [];

    public function __construct(
        string $entityType = '',
        array $entityData = [],
        array $relationships = [],
        array $metadata = [],
        string $operation = 'merge',
        array $indexes = []
    ) {
        $this->entityType = $entityType;
        $this->entityData = $entityData;
        $this->relationships = $relationships;
        $this->metadata = $metadata;
        $this->operation = $operation;
        $this->indexes = $indexes;
    }

    // Getters
    public function getEntityType(): string { return $this->entityType; }
    public function getEntityData(): array { return $this->entityData; }
    public function getRelationships(): array { return $this->relationships; }
    public function getMetadata(): array { return $this->metadata; }
    public function getOperation(): string { return $this->operation; }
    public function getIndexes(): array { return $this->indexes; }

    // Setters for flexibility
    public function setEntityType(string $entityType): self 
    { 
        $this->entityType = $entityType; 
        return $this; 
    }

    public function setEntityData(array $entityData): self 
    { 
        $this->entityData = $entityData; 
        return $this; 
    }

    public function setRelationships(array $relationships): self 
    { 
        $this->relationships = $relationships; 
        return $this; 
    }

    public function setMetadata(array $metadata): self 
    { 
        $this->metadata = $metadata; 
        return $this; 
    }

    public function setOperation(string $operation): self 
    { 
        $this->operation = $operation; 
        return $this; 
    }

    public function setIndexes(array $indexes): self 
    { 
        $this->indexes = $indexes; 
        return $this; 
    }

    /**
     * Add a single relationship
     */
    public function addRelationship(string $type, array $targetNode, array $properties = []): self
    {
        $this->relationships[] = [
            'type' => $type,
            'target' => $targetNode,
            'properties' => $properties
        ];
        return $this;
    }

    /**
     * Add index definition
     */
    public function addIndex(string $property, string $type = 'btree'): self
    {
        $this->indexes[] = [
            'property' => $property,
            'type' => $type
        ];
        return $this;
    }

    /**
     * Validate the structure for common entity types
     */
    public function isValid(): bool
    {
        // Basic validation
        if (empty($this->entityType)) {
            return false;
        }
        
        if (empty($this->entityData) || !is_array($this->entityData)) {
            return false;
        }

        // Ensure entity data has at least an identifier
        $hasIdentifier = isset($this->entityData['id']) || 
                        isset($this->entityData['uuid']) || 
                        isset($this->entityData['name']) ||
                        isset($this->entityData['title']);

        return $hasIdentifier;
    }

    /**
     * Get suggested node label based on entity type
     */
    public function getNodeLabel(): string
    {
        return ucfirst($this->entityType);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'entityType' => $this->entityType,
            'entityData' => $this->entityData,
            'relationships' => $this->relationships,
            'metadata' => $this->metadata,
            'operation' => $this->operation,
            'indexes' => $this->indexes
        ];
    }
}
