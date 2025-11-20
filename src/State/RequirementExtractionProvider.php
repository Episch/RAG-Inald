<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RequirementExtractionJob;

/**
 * API Platform State Provider for Requirements Extraction
 */
class RequirementExtractionProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get single job
        if (isset($uriVariables['id'])) {
            return RequirementExtractionProcessor::getJob($uriVariables['id']);
        }

        // Get all jobs
        return RequirementExtractionProcessor::getAllJobs();
    }
}

