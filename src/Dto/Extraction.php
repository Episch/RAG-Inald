<?php
namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\ExtractionController;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Submit documents for asynchronous extraction and processing using Apache Tika, followed by LLM categorization and graph storage.
 */
#[ApiResource(
    shortName: 'ETL DocumentExtraction',
    operations: [
        new Post(
            uriTemplate: '/extraction',
            controller: ExtractionController::class,
            description: 'Submit documents for asynchronous extraction and processing using Apache Tika, followed by LLM categorization and graph storage.',
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['write']],
            input: Extraction::class
        ),
    ]
)]
class Extraction
{
    #[Groups(['write', 'read'])]
    #[Assert\NotBlank(message: 'Path is required')]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\/_-]+$/',
        message: 'Path contains invalid characters. Only letters, numbers, slashes, underscores and dashes are allowed.'
    )]
    public string $path = ''; // PUBLIC PROPERTY für ApiPlatform - no default for security

    #[Groups(['write', 'read'])]
    #[Assert\Type('bool')]
    public bool $saveAsFile = true; // Standardmäßig als Datei speichern

    #[Groups(['write', 'read'])]
    #[Assert\Length(max: 100)]
    public string $outputFilename = ''; // Optional: benutzerdefinierter Dateiname

    // Getter/Setter optional
    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function isSaveAsFile(): bool
    {
        return $this->saveAsFile;
    }

    public function setSaveAsFile(bool $saveAsFile): void
    {
        $this->saveAsFile = $saveAsFile;
    }

    public function getOutputFilename(): string
    {
        return $this->outputFilename;
    }

    public function setOutputFilename(string $outputFilename): void
    {
        $this->outputFilename = $outputFilename;
    }
}
