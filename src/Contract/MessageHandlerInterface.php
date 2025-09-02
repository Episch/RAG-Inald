<?php

namespace App\Contract;

/**
 * Interface for message handlers with common structure
 */
interface MessageHandlerInterface
{
    /**
     * Process the message and return status code
     * 
     * @return int Status code (0 = success, > 0 = error)
     */
    public function __invoke(object $message): int;

    /**
     * Get handler name for logging/debugging
     */
    public function getHandlerName(): string;
}
