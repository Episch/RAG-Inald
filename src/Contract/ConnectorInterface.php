<?php

namespace App\Contract;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Interface for external service connectors
 */
interface ConnectorInterface
{
    /**
     * Get service status/health check
     */
    public function getStatus(): ResponseInterface;

    /**
     * Get service information (version, capabilities, etc.)
     */
    public function getServiceInfo(): array;
}
