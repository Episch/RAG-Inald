<?php

namespace App\Dto\Requirements;

/**
 * DTO für Role Entity (IRREB)
 * 
 * Repräsentiert eine Rolle oder einen Stakeholder im Requirements-Kontext.
 */
class RoleDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?array $responsibilities = [],
        public readonly ?string $department = null,
        public readonly ?string $level = null, // executive, manager, operator, end-user
        public readonly ?array $permissions = [],
        public readonly ?array $interests = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'responsibilities' => $this->responsibilities ?? [],
            'department' => $this->department,
            'level' => $this->level,
            'permissions' => $this->permissions ?? [],
            'interests' => $this->interests ?? []
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('role_'),
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            responsibilities: $data['responsibilities'] ?? [],
            department: $data['department'] ?? null,
            level: $data['level'] ?? null,
            permissions: $data['permissions'] ?? [],
            interests: $data['interests'] ?? []
        );
    }
}

