<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\RequirementExtractionJob;
use App\Message\ExtractRequirementsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform State Processor for Requirements Extraction
 */
class RequirementExtractionProcessor implements ProcessorInterface
{
    // In-memory storage for demo (replace with Doctrine/Redis in production)
    private static array $jobs = [];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param RequirementExtractionJob $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequirementExtractionJob
    {
        // Validate document path
        if (!file_exists($data->documentPath)) {
            throw new \RuntimeException("Document not found: {$data->documentPath}");
        }

        // Store job
        self::$jobs[$data->id] = $data;

        // Dispatch async message if enabled
        if ($data->extractionOptions['async'] ?? true) {
            $message = new ExtractRequirementsMessage(
                jobId: $data->id,
                documentPath: $data->documentPath,
                projectName: $data->projectName,
                extractionOptions: $data->extractionOptions
            );

            $this->messageBus->dispatch($message);

            $this->logger->info('Requirements extraction job dispatched', [
                'job_id' => $data->id,
                'document' => basename($data->documentPath),
            ]);

            $data->markAsProcessing();
        } else {
            // Synchronous processing (for testing)
            $this->logger->info('Synchronous extraction not yet implemented', [
                'job_id' => $data->id,
            ]);
        }

        return $data;
    }

    /**
     * Get job by ID (for GET requests)
     */
    public static function getJob(string $id): ?RequirementExtractionJob
    {
        return self::$jobs[$id] ?? null;
    }

    /**
     * Get all jobs (for GET collection)
     */
    public static function getAllJobs(): array
    {
        return array_values(self::$jobs);
    }
}

