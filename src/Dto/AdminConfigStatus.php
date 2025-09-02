<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO for admin configuration status response
 */
class AdminConfigStatus
{
    #[Groups(['admin:read'])]
    public array $configuration = [];

    #[Groups(['admin:read'])]
    public string $timestamp = '';

    #[Groups(['admin:read'])]
    public string $environment = '';
}
