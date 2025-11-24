<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use ApiPlatform\Metadata\ApiProperty;
use App\Validator\Constraints as AppAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input DTO for Requirements Extraction
 * 
 * Supports three ways to provide a document:
 * 1. File upload (base64-encoded content)
 * 2. Public URL (will be downloaded)
 * 3. Server file path (only for backend/admin use)
 */
#[AppAssert\DocumentSource]
class RequirementExtractionInput
{
    #[ApiProperty(
        description: 'Name of the project/software being analyzed',
        example: 'E-Commerce Platform v2.0'
    )]
    #[Assert\NotBlank(message: 'Project name is required')]
    #[Assert\Length(min: 3, max: 255)]
    public ?string $projectName = null;

    // ============================================
    // Option 1: File Upload (Base64)
    // ============================================
    
    #[ApiProperty(
        description: 'Base64-encoded document content (PDF, DOCX, TXT, MD)',
        example: 'JVBERi0xLjQKJeLjz9MKMSAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgMiAwIFI+PgplbmRvYmoK...'
    )]
    #[Assert\Type('string')]
    public ?string $fileContent = null;

    #[ApiProperty(
        description: 'Original filename (required if fileContent is provided)',
        example: 'requirements-specification.pdf'
    )]
    #[Assert\Type('string')]
    public ?string $fileName = null;

    #[ApiProperty(
        description: 'MIME type of the uploaded file',
        example: 'application/pdf',
        openapiContext: [
            'type' => 'string',
            'enum' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/markdown', 'application/msword']
        ]
    )]
    #[Assert\Choice(choices: ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/markdown', 'application/msword'])]
    public ?string $mimeType = null;

    // ============================================
    // Option 2: URL Download
    // ============================================
    
    #[ApiProperty(
        description: 'Public URL to download the document from',
        example: 'https://example.com/docs/requirements.pdf'
    )]
    #[Assert\Url]
    public ?string $documentUrl = null;

    // ============================================
    // Option 3: Server Path (Admin only)
    // ============================================
    
    #[ApiProperty(
        description: 'Server file path (only for backend/admin use)',
        example: '/var/www/uploads/requirements-doc.pdf'
    )]
    #[Assert\Type('string')]
    public ?string $serverPath = null;

    // ============================================
    // Extraction Options
    // ============================================
    
    #[ApiProperty(
        description: 'LLM model to use for extraction',
        example: 'llama3.2',
        openapiContext: [
            'type' => 'string',
            'enum' => ['llama3.2', 'llama3.1', 'mistral', 'codellama']
        ]
    )]
    #[Assert\Choice(choices: ['llama3.2', 'llama3.1', 'mistral', 'codellama'])]
    public ?string $llmModel = 'llama3.2';

    #[ApiProperty(
        description: 'Temperature for LLM (0.0 = deterministic, 1.0 = creative)',
        example: 0.7
    )]
    #[Assert\Range(min: 0.0, max: 1.0)]
    public ?float $temperature = 0.7;

    #[ApiProperty(
        description: 'Process extraction asynchronously (recommended for large documents)',
        example: true
    )]
    public ?bool $async = true;

    /**
     * Validate that fileContent has corresponding fileName
     */
    #[Assert\IsTrue(message: 'fileName is required when fileContent is provided')]
    public function hasFileNameWhenContentProvided(): bool
    {
        if ($this->fileContent !== null) {
            return $this->fileName !== null;
        }
        return true;
    }

    /**
     * Get the document source type
     */
    public function getSourceType(): string
    {
        if ($this->fileContent !== null) {
            return 'upload';
        }
        if ($this->documentUrl !== null) {
            return 'url';
        }
        if ($this->serverPath !== null) {
            return 'server';
        }
        return 'unknown';
    }
}

