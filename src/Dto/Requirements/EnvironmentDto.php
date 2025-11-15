<?php

namespace App\Dto\Requirements;

/**
 * DTO für Environment Entity (IRREB)
 * 
 * Repräsentiert eine Umgebung, in der das System betrieben wird.
 */
class EnvironmentDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type, // production, staging, development, test
        public readonly ?string $description = null,
        public readonly ?array $constraints = [],
        public readonly ?array $specifications = [],
        public readonly ?string $location = null,
        public readonly ?array $availability = [], // uptime requirements, etc.
        public readonly ?array $securityRequirements = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'constraints' => $this->constraints ?? [],
            'specifications' => $this->specifications ?? [],
            'location' => $this->location,
            'availability' => $this->availability ?? [],
            'securityRequirements' => $this->securityRequirements ?? []
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('env_'),
            name: $data['name'] ?? '',
            type: $data['type'] ?? 'production',
            description: $data['description'] ?? null,
            constraints: $data['constraints'] ?? [],
            specifications: $data['specifications'] ?? [],
            location: $data['location'] ?? null,
            availability: $data['availability'] ?? [],
            securityRequirements: $data['securityRequirements'] ?? []
        );
    }
}

