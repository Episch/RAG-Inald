<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Schema.org SoftwareApplication DTO
 * 
 * @see https://schema.org/SoftwareApplication
 */
class SoftwareApplicationDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $name;

    #[Assert\Type('string')]
    public ?string $description = null;

    #[Assert\Type('string')]
    public ?string $version = null;

    #[Assert\Type('string')]
    public ?string $applicationCategory = null;

    #[Assert\Type('string')]
    public ?string $operatingSystem = null;

    /**
     * @var SoftwareRequirementsDTO[]
     */
    #[Assert\Valid]
    public array $requirements = [];

    #[Assert\Type('string')]
    public ?string $softwareVersion = null;

    #[Assert\Type('string')]
    public ?string $releaseNotes = null;

    /**
     * @var string[]
     */
    public array $keywords = [];

    #[Assert\Type('string')]
    public ?string $license = null;

    #[Assert\Type('string')]
    public ?string $provider = null;

    public function __construct(
        string $name,
        ?string $description = null,
        array $requirements = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->requirements = $requirements;
    }

    public function addRequirement(SoftwareRequirementsDTO $requirement): self
    {
        $this->requirements[] = $requirement;
        return $this;
    }

    /**
     * Convert to array for TOON encoding
     */
    public function toArray(): array
    {
        return [
            '@type' => 'SoftwareApplication',
            '@context' => 'https://schema.org',
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'applicationCategory' => $this->applicationCategory,
            'operatingSystem' => $this->operatingSystem,
            'softwareVersion' => $this->softwareVersion,
            'releaseNotes' => $this->releaseNotes,
            'keywords' => $this->keywords,
            'license' => $this->license,
            'provider' => $this->provider,
            'requirements' => array_map(
                fn(SoftwareRequirementsDTO $req) => $req->toArray(),
                $this->requirements
            ),
        ];
    }
}

