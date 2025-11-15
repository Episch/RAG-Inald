<?php

namespace App\Dto\Requirements;

/**
 * DTO für Infrastructure Entity (IRREB)
 * 
 * Repräsentiert Infrastruktur-Komponenten und -Anforderungen.
 */
class InfrastructureDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type, // server, network, storage, database, cloud
        public readonly ?string $description = null,
        public readonly ?array $capacity = [], // CPU, RAM, Storage, etc.
        public readonly ?array $dependencies = [],
        public readonly ?array $specifications = [],
        public readonly ?string $provider = null,
        public readonly ?string $location = null,
        public readonly ?array $scalability = [],
        public readonly ?array $redundancy = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'capacity' => $this->capacity ?? [],
            'dependencies' => $this->dependencies ?? [],
            'specifications' => $this->specifications ?? [],
            'provider' => $this->provider,
            'location' => $this->location,
            'scalability' => $this->scalability ?? [],
            'redundancy' => $this->redundancy ?? []
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('infra_'),
            name: $data['name'] ?? '',
            type: $data['type'] ?? 'server',
            description: $data['description'] ?? null,
            capacity: $data['capacity'] ?? [],
            dependencies: $data['dependencies'] ?? [],
            specifications: $data['specifications'] ?? [],
            provider: $data['provider'] ?? null,
            location: $data['location'] ?? null,
            scalability: $data['scalability'] ?? [],
            redundancy: $data['redundancy'] ?? []
        );
    }
}

