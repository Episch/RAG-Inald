<?php

namespace App\Dto\Requirements;

/**
 * DTO für SoftwareApplication Entity (schema.org)
 * 
 * Repräsentiert Software-Anwendungen nach schema.org Standard.
 */
class SoftwareApplicationDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $version = null,
        public readonly ?string $description = null,
        public readonly ?string $operatingSystem = null,
        public readonly ?string $category = null, // BusinessApplication, DeveloperApplication, etc.
        public readonly ?string $downloadUrl = null,
        public readonly ?string $fileSize = null,
        public readonly ?array $softwareRequirements = [], // dependencies
        public readonly ?array $memoryRequirements = [],
        public readonly ?array $storageRequirements = [],
        public readonly ?array $permissions = [],
        public readonly ?string $applicationCategory = null,
        public readonly ?string $applicationSubCategory = null,
        public readonly ?array $features = [],
        public readonly ?string $license = null,
        public readonly ?string $releaseDate = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'operatingSystem' => $this->operatingSystem,
            'category' => $this->category,
            'downloadUrl' => $this->downloadUrl,
            'fileSize' => $this->fileSize,
            'softwareRequirements' => $this->softwareRequirements ?? [],
            'memoryRequirements' => $this->memoryRequirements ?? [],
            'storageRequirements' => $this->storageRequirements ?? [],
            'permissions' => $this->permissions ?? [],
            'applicationCategory' => $this->applicationCategory,
            'applicationSubCategory' => $this->applicationSubCategory,
            'features' => $this->features ?? [],
            'license' => $this->license,
            'releaseDate' => $this->releaseDate
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('sw_'),
            name: $data['name'] ?? '',
            version: $data['version'] ?? null,
            description: $data['description'] ?? null,
            operatingSystem: $data['operatingSystem'] ?? null,
            category: $data['category'] ?? null,
            downloadUrl: $data['downloadUrl'] ?? null,
            fileSize: $data['fileSize'] ?? null,
            softwareRequirements: $data['softwareRequirements'] ?? [],
            memoryRequirements: $data['memoryRequirements'] ?? [],
            storageRequirements: $data['storageRequirements'] ?? [],
            permissions: $data['permissions'] ?? [],
            applicationCategory: $data['applicationCategory'] ?? null,
            applicationSubCategory: $data['applicationSubCategory'] ?? null,
            features: $data['features'] ?? [],
            license: $data['license'] ?? null,
            releaseDate: $data['releaseDate'] ?? null
        );
    }
}

