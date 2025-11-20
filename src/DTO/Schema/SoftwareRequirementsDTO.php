<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Schema.org SoftwareRequirements DTO
 * 
 * Represents functional and non-functional requirements extracted from documents
 * 
 * @see https://schema.org/SoftwareApplication (requirements property)
 */
class SoftwareRequirementsDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $identifier;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $description;

    #[Assert\Choice(choices: ['functional', 'non-functional', 'technical', 'business', 'security', 'performance', 'usability', 'other'])]
    public string $requirementType = 'functional';

    #[Assert\Choice(choices: ['must', 'should', 'could', 'wont'])]
    public string $priority = 'should';

    #[Assert\Type('string')]
    public ?string $category = null;

    /**
     * @var string[]
     */
    public array $tags = [];

    #[Assert\Type('string')]
    public ?string $source = null;

    #[Assert\Type('string')]
    public ?string $acceptanceCriteria = null;

    /**
     * Related requirement identifiers
     * @var string[]
     */
    public array $relatedRequirements = [];

    public function __construct(
        string $identifier,
        string $name,
        string $description,
        string $requirementType = 'functional',
        string $priority = 'should'
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->description = $description;
        $this->requirementType = $requirementType;
        $this->priority = $priority;
    }

    /**
     * Convert to array for TOON encoding
     */
    public function toArray(): array
    {
        return [
            '@type' => 'SoftwareRequirement',
            'identifier' => $this->identifier,
            'name' => $this->name,
            'description' => $this->description,
            'requirementType' => $this->requirementType,
            'priority' => $this->priority,
            'category' => $this->category,
            'tags' => $this->tags,
            'source' => $this->source,
            'acceptanceCriteria' => $this->acceptanceCriteria,
            'relatedRequirements' => $this->relatedRequirements,
        ];
    }

    /**
     * Create from LLM response array
     */
    public static function fromArray(array $data): self
    {
        $requirement = new self(
            $data['identifier'] ?? uniqid('req_'),
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['requirementType'] ?? 'functional',
            $data['priority'] ?? 'should'
        );

        $requirement->category = $data['category'] ?? null;
        $requirement->tags = $data['tags'] ?? [];
        $requirement->source = $data['source'] ?? null;
        $requirement->acceptanceCriteria = $data['acceptanceCriteria'] ?? null;
        $requirement->relatedRequirements = $data['relatedRequirements'] ?? [];

        return $requirement;
    }
}

