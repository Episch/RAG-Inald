<?php

namespace App\Dto\Requirements;

/**
 * DTO für IRREB Requirement Entity
 * 
 * Repräsentiert ein Requirement nach IREB-Standard mit allen relevanten Attributen.
 */
class RequirementDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $type, // functional, non-functional, constraint
        public readonly string $priority, // high, medium, low, critical
        public readonly string $status, // draft, approved, implemented, validated, deprecated
        public readonly string $source, // document, interview, workshop, etc.
        public readonly ?string $rationale = null,
        public readonly ?string $acceptanceCriteria = null,
        public readonly ?array $dependencies = [], // IDs of other requirements
        public readonly ?array $tags = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'source' => $this->source,
            'rationale' => $this->rationale,
            'acceptanceCriteria' => $this->acceptanceCriteria,
            'dependencies' => $this->dependencies ?? [],
            'tags' => $this->tags ?? [],
            'createdAt' => $this->createdAt ?? date('c'),
            'updatedAt' => $this->updatedAt ?? date('c')
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('req_'),
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            type: $data['type'] ?? 'functional',
            priority: $data['priority'] ?? 'medium',
            status: $data['status'] ?? 'draft',
            source: $data['source'] ?? 'document',
            rationale: $data['rationale'] ?? null,
            acceptanceCriteria: $data['acceptanceCriteria'] ?? null,
            dependencies: $data['dependencies'] ?? [],
            tags: $data['tags'] ?? [],
            createdAt: $data['createdAt'] ?? null,
            updatedAt: $data['updatedAt'] ?? null
        );
    }
}

