<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\DTO\Schema\SoftwareApplicationDTO;
use App\State\RequirementExtractionProcessor;
use App\State\RequirementExtractionProvider;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API Resource for Requirements Extraction Jobs
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/requirements/jobs/{id}',
            description: 'Get Requirements Extraction Job Status',
            provider: RequirementExtractionProvider::class,
            security: 'is_granted("ROLE_USER")'
        ),
        new GetCollection(
            uriTemplate: '/requirements/jobs',
            description: 'List Requirements Extraction Jobs',
            provider: RequirementExtractionProvider::class,
            security: 'is_granted("ROLE_USER")'
        ),
        new Post(
            uriTemplate: '/requirements/extract',
            description: 'Extract software requirements from document and start processing job',
            input: \App\DTO\Input\RequirementExtractionInput::class,
            processor: RequirementExtractionProcessor::class,
            security: 'is_granted("ROLE_USER")'
        ),
    ],
    normalizationContext: ['groups' => ['requirement:read']],
    denormalizationContext: ['groups' => ['requirement:write']]
)]
class RequirementExtractionJob
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(writable: false)]
    public string $status = 'pending'; // pending, processing, completed, failed

    #[Assert\NotBlank(groups: ['requirement:write'])]
    #[ApiProperty(required: true)]
    public string $documentPath;

    #[Assert\NotBlank(groups: ['requirement:write'])]
    #[ApiProperty(required: true)]
    public string $projectName;

    #[ApiProperty(writable: false)]
    public ?string $extractedText = null;

    #[ApiProperty(writable: false)]
    public ?string $toonOutput = null;

    #[ApiProperty(writable: false)]
    public ?SoftwareApplicationDTO $result = null;

    #[ApiProperty(writable: false)]
    public ?string $neo4jNodeId = null;

    #[ApiProperty(writable: false)]
    public array $metadata = [];

    #[ApiProperty(writable: false)]
    public ?\DateTimeImmutable $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?\DateTimeImmutable $completedAt = null;

    #[ApiProperty(writable: false)]
    public ?string $errorMessage = null;

    public array $extractionOptions = [
        'llmModel' => 'llama3.2',
        'temperature' => 0.7,
        'async' => true,
    ];

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function markAsProcessing(): void
    {
        $this->status = 'processing';
    }

    public function markAsCompleted(SoftwareApplicationDTO $result): void
    {
        $this->status = 'completed';
        $this->result = $result;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function markAsFailed(string $error): void
    {
        $this->status = 'failed';
        $this->errorMessage = $error;
        $this->completedAt = new \DateTimeImmutable();
    }
}

