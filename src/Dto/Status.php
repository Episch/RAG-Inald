<?php
namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\StatusController;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Check the health and connectivity status of all backend services including Apache Tika (document extraction), Neo4j (graph database), and Ollama (LLM service).
 */
#[ApiResource(
    shortName: 'Monitoring',
    operations: [
        new Get(
            uriTemplate: '/status',
            controller: StatusController::class,
            description: 'Check the health and connectivity status of all backend services including Apache Tika (document extraction), Neo4j (graph database), and Ollama (LLM service).',
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['write']],
            input: Status::class
        ),
    ]
)]
class Status
{
    #[Groups(['write', 'read'])]
    public array $server = []; // Initialize property to avoid undefined behavior

    public function getServer(): array
    {
        return $this->server;
    }

    public function setServer(array $server): void
    {
        $this->server = $server;
    }
}
