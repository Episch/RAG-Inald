<?php

namespace App\Dto\Requirements;

/**
 * DTO für den kompletten Requirements-Graph
 * 
 * Container-DTO für alle extrahierten Requirements und deren Beziehungen.
 */
class RequirementsGraphDto
{
    public function __construct(
        public readonly array $requirements = [],
        public readonly array $roles = [],
        public readonly array $environments = [],
        public readonly array $businesses = [],
        public readonly array $infrastructures = [],
        public readonly array $softwareApplications = [],
        public readonly array $relationships = [],
        public readonly ?array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'requirements' => array_map(fn($r) => $r instanceof RequirementDto ? $r->toArray() : $r, $this->requirements),
            'roles' => array_map(fn($r) => $r instanceof RoleDto ? $r->toArray() : $r, $this->roles),
            'environments' => array_map(fn($e) => $e instanceof EnvironmentDto ? $e->toArray() : $e, $this->environments),
            'businesses' => array_map(fn($b) => $b instanceof BusinessDto ? $b->toArray() : $b, $this->businesses),
            'infrastructures' => array_map(fn($i) => $i instanceof InfrastructureDto ? $i->toArray() : $i, $this->infrastructures),
            'softwareApplications' => array_map(fn($s) => $s instanceof SoftwareApplicationDto ? $s->toArray() : $s, $this->softwareApplications),
            'relationships' => $this->relationships,
            'metadata' => $this->metadata ?? [
                'extractedAt' => date('c'),
                'totalRequirements' => count($this->requirements),
                'totalRoles' => count($this->roles),
                'totalEnvironments' => count($this->environments),
                'totalBusinesses' => count($this->businesses),
                'totalInfrastructures' => count($this->infrastructures),
                'totalSoftwareApplications' => count($this->softwareApplications),
                'totalRelationships' => count($this->relationships)
            ]
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            requirements: array_map(
                fn($r) => is_array($r) ? RequirementDto::fromArray($r) : $r,
                $data['requirements'] ?? []
            ),
            roles: array_map(
                fn($r) => is_array($r) ? RoleDto::fromArray($r) : $r,
                $data['roles'] ?? []
            ),
            environments: array_map(
                fn($e) => is_array($e) ? EnvironmentDto::fromArray($e) : $e,
                $data['environments'] ?? []
            ),
            businesses: array_map(
                fn($b) => is_array($b) ? BusinessDto::fromArray($b) : $b,
                $data['businesses'] ?? []
            ),
            infrastructures: array_map(
                fn($i) => is_array($i) ? InfrastructureDto::fromArray($i) : $i,
                $data['infrastructures'] ?? []
            ),
            softwareApplications: array_map(
                fn($s) => is_array($s) ? SoftwareApplicationDto::fromArray($s) : $s,
                $data['softwareApplications'] ?? []
            ),
            relationships: $data['relationships'] ?? [],
            metadata: $data['metadata'] ?? []
        );
    }
}

