<?php
namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\ExtractionController;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'RAG',
    operations: [
        new Post(
            uriTemplate: '/extraction',
            controller: ExtractionController::class,
            description: 'Extraction of file with help from apache TIKA.',
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

    // Getter/Setter optional
    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
