<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for asynchronous requirements extraction
 */
class ExtractRequirementsMessage
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $documentPath,
        public readonly string $projectName,
        public readonly array $extractionOptions = []
    ) {
    }
}

