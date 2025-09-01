<?php
namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\ExtractionController;
use Symfony\Component\Serializer\Annotation\Groups;

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
    public string $path = 'test'; // PUBLIC PROPERTY für ApiPlatform

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
