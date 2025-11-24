<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Output DTO for Requirements Extraction Job Status
 */
class RequirementExtractionJobOutput
{
    #[ApiProperty(
        description: 'Unique job identifier',
        example: '01936c3f-7b8e-7890-abcd-123456789abc',
        identifier: true
    )]
    #[Groups(['requirement:read'])]
    public string $id = '';

    #[ApiProperty(
        description: 'Current job status',
        example: 'processing',
        openapiContext: [
            'type' => 'string',
            'enum' => ['pending', 'processing', 'completed', 'failed']
        ]
    )]
    #[Groups(['requirement:read'])]
    public string $status = 'pending';

    #[ApiProperty(
        description: 'Name of the project being analyzed',
        example: 'E-Commerce Platform v2.0'
    )]
    #[Groups(['requirement:read'])]
    public string $projectName = '';

    #[ApiProperty(
        description: 'Path to the document being processed',
        example: '/var/uploads/extraction-jobs/requirements.pdf'
    )]
    #[Groups(['requirement:read'])]
    public string $documentPath = '';

    #[ApiProperty(
        description: 'Job creation timestamp',
        example: '2024-11-24T01:30:00+00:00'
    )]
    #[Groups(['requirement:read'])]
    public \DateTimeImmutable|null $createdAt = null;

    #[ApiProperty(
        description: 'Extracted text from the document (available when status is processing or completed)',
        example: 'The system shall...'
    )]
    #[Groups(['requirement:read'])]
    public ?string $extractedText = null;

    #[ApiProperty(
        description: 'Structured requirements output from LLM (available when status is completed)'
    )]
    #[Groups(['requirement:read'])]
    public ?SoftwareApplication $result = null;

    #[ApiProperty(
        description: 'Neo4j node ID where requirements are stored (available when status is completed)',
        example: '4:abc123:456'
    )]
    #[Groups(['requirement:read'])]
    public ?string $neo4jNodeId = null;

    #[ApiProperty(
        description: 'Job metadata (extraction options, statistics, etc.)',
        openapiContext: [
            'type' => 'object',
            'example' => [
                'llmModel' => 'llama3.2',
                'temperature' => 0.7,
                'async' => true,
                'extractionTimeSeconds' => 45.3
            ]
        ]
    )]
    #[Groups(['requirement:read'])]
    public array $metadata = [];

    #[ApiProperty(
        description: 'Job completion timestamp (null if not completed)',
        example: '2024-11-24T01:30:45+00:00'
    )]
    #[Groups(['requirement:read'])]
    public ?\DateTimeImmutable $completedAt = null;

    #[ApiProperty(
        description: 'Error message (only present when status is failed)',
        example: 'Failed to parse PDF: Unsupported format'
    )]
    #[Groups(['requirement:read'])]
    public ?string $errorMessage = null;

    /**
     * Create output DTO from Job entity
     */
    public static function fromJob($job): self
    {
        $output = new self();
        $output->id = $job->id;
        $output->status = $job->status;
        $output->projectName = $job->projectName;
        $output->documentPath = $job->documentPath;
        $output->createdAt = $job->createdAt;
        $output->extractedText = $job->extractedText;
        $output->result = $job->result;
        $output->neo4jNodeId = $job->neo4jNodeId;
        $output->metadata = array_merge($job->metadata, $job->extractionOptions ?? []);
        $output->completedAt = $job->completedAt;
        $output->errorMessage = $job->errorMessage;

        return $output;
    }
}

