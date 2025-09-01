<?php
namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\StatusController;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Server',
    operations: [
        new Get(
            uriTemplate: '/status',
            controller: StatusController::class,
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['write']],
            input: Status::class
        ),
    ]
)]
class Status
{
    #[Groups(['write', 'read'])]
    public array $server;

    public function getServer(): array
    {
        return $this->server;
    }

    public function setServer(array $server): void
    {
        $this->server = $server;
    }
}
