<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RequirementExtractionJob;
use App\DTO\Schema\RequirementExtractionJobOutput;

/**
 * API Platform State Provider for Requirements Extraction
 */
class RequirementExtractionProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get single job
        if (isset($uriVariables['id'])) {
            $job = RequirementExtractionProcessor::getJob($uriVariables['id']);
            return $job ? RequirementExtractionJobOutput::fromJob($job) : null;
        }

        // Get all jobs
        $jobs = RequirementExtractionProcessor::getAllJobs();
        return array_map(
            fn($job) => RequirementExtractionJobOutput::fromJob($job),
            $jobs
        );
    }
}

