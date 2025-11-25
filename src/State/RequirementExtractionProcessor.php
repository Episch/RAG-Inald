<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\RequirementExtractionJob;
use App\DTO\Schema\RequirementExtractionInput;
use App\DTO\Schema\RequirementExtractionJobOutput;
use App\Message\ExtractRequirementsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * API Platform State Processor for Requirements Extraction
 */
class RequirementExtractionProcessor implements ProcessorInterface
{
    // In-memory storage for demo (replace with Doctrine/Redis in production)
    private static array $jobs = [];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir
    ) {
    }

    /**
     * @param RequirementExtractionInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequirementExtractionJobOutput
    {
        // Transform Input DTO to Job
        $job = $this->createJobFromInput($data);

        // Process document based on source type
        $documentPath = $this->processDocumentSource($data, $job);

        // Validate document path
        if (!file_exists($documentPath)) {
            throw new \RuntimeException("Document could not be processed: {$documentPath}");
        }

        $job->documentPath = $documentPath;

        // Store job
        self::$jobs[$job->id] = $job;

        // Dispatch async message if enabled
        if ($data->async) {
            $message = new ExtractRequirementsMessage(
                jobId: $job->id,
                documentPath: $job->documentPath,
                projectName: $job->projectName,
                extractionOptions: $job->extractionOptions
            );

            $this->messageBus->dispatch($message);

            $this->logger->info('Requirements extraction job dispatched', [
                'job_id' => $job->id,
                'document' => basename($job->documentPath),
                'source_type' => $data->getSourceType(),
            ]);

            $job->markAsProcessing();
        } else {
            // Synchronous processing (for testing)
            $this->logger->info('Synchronous extraction not yet implemented', [
                'job_id' => $job->id,
            ]);
        }

        return RequirementExtractionJobOutput::fromJob($job);
    }

    /**
     * Create a Job from Input DTO
     */
    private function createJobFromInput(RequirementExtractionInput $input): RequirementExtractionJob
    {
        $job = new RequirementExtractionJob();
        $job->projectName = $input->projectName;
        $job->extractionOptions = [
            'llmModel' => $input->llmModel,
            'temperature' => $input->temperature,
            'async' => $input->async,
        ];

        return $job;
    }

    /**
     * Process document source based on input type
     */
    private function processDocumentSource(RequirementExtractionInput $input, RequirementExtractionJob $job): string
    {
        $uploadDir = $this->projectDir . '/var/uploads/extraction-jobs';
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return match ($input->getSourceType()) {
            'upload' => $this->handleFileUpload($input, $job, $uploadDir),
            'url' => $this->handleUrlDownload($input, $job, $uploadDir),
            'server' => $this->handleServerPath($input),
            default => throw new \RuntimeException('No valid document source provided'),
        };
    }

    /**
     * Handle base64 file upload
     */
    private function handleFileUpload(RequirementExtractionInput $input, RequirementExtractionJob $job, string $uploadDir): string
    {
        // Decode base64 content
        $fileContent = base64_decode($input->fileContent, true);
        
        if ($fileContent === false) {
            throw new \RuntimeException('Invalid base64 file content');
        }

        // Generate safe filename
        $extension = pathinfo($input->fileName, PATHINFO_EXTENSION);
        $safeFileName = $job->id . '.' . $extension;
        $targetPath = $uploadDir . '/' . $safeFileName;

        // Save file
        if (file_put_contents($targetPath, $fileContent) === false) {
            throw new \RuntimeException('Failed to save uploaded file');
        }

        $this->logger->info('File uploaded successfully', [
            'job_id' => $job->id,
            'original_name' => $input->fileName,
            'saved_path' => $targetPath,
            'size_bytes' => strlen($fileContent),
        ]);

        return $targetPath;
    }

    /**
     * Handle URL download
     */
    private function handleUrlDownload(RequirementExtractionInput $input, RequirementExtractionJob $job, string $uploadDir): string
    {
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', $input->documentUrl);
            
            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Failed to download document: HTTP ' . $response->getStatusCode());
            }

            $fileContent = $response->getContent();
            
            // Determine extension from Content-Type or URL
            $extension = $this->guessExtensionFromUrl($input->documentUrl, $response->getHeaders()['content-type'][0] ?? null);
            $safeFileName = $job->id . '.' . $extension;
            $targetPath = $uploadDir . '/' . $safeFileName;

            // Save file
            if (file_put_contents($targetPath, $fileContent) === false) {
                throw new \RuntimeException('Failed to save downloaded file');
            }

            $this->logger->info('File downloaded successfully', [
                'job_id' => $job->id,
                'url' => $input->documentUrl,
                'saved_path' => $targetPath,
                'size_bytes' => strlen($fileContent),
            ]);

            return $targetPath;
        } catch (\Exception $e) {
            $this->logger->error('Failed to download document', [
                'url' => $input->documentUrl,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to download document: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Handle server path (admin only)
     */
    private function handleServerPath(RequirementExtractionInput $input): string
    {
        if (!file_exists($input->serverPath)) {
            throw new \RuntimeException("Server file not found: {$input->serverPath}");
        }

        return $input->serverPath;
    }

    /**
     * Guess file extension from URL or Content-Type
     */
    private function guessExtensionFromUrl(string $url, ?string $contentType): string
    {
        // Try from Content-Type first
        $mimeToExt = [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
        ];

        if ($contentType && isset($mimeToExt[$contentType])) {
            return $mimeToExt[$contentType];
        }

        // Fallback to URL path extension
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        return $extension ?: 'bin';
    }

    /**
     * Get job by ID (for GET requests)
     */
    public static function getJob(string $id): ?RequirementExtractionJob
    {
        return self::$jobs[$id] ?? null;
    }

    /**
     * Get all jobs (for GET collection), sorted by creation date (newest first)
     */
    public static function getAllJobs(): array
    {
        $jobs = array_values(self::$jobs);
        
        // Sort by creation date (newest first)
        usort($jobs, function($a, $b) {
            $timeA = $a->createdAt?->getTimestamp() ?? 0;
            $timeB = $b->createdAt?->getTimestamp() ?? 0;
            return $timeB <=> $timeA; // Descending order
        });
        
        return $jobs;
    }

    /**
     * Get the latest (most recent) job
     */
    public static function getLatestJob(): ?RequirementExtractionJob
    {
        $jobs = self::getAllJobs();
        return $jobs[0] ?? null;
    }

    /**
     * Update job status (called from message handler)
     */
    public static function updateJobStatus(string $jobId, string $status, array $data = []): void
    {
        if (!isset(self::$jobs[$jobId])) {
            return;
        }

        $job = self::$jobs[$jobId];
        $job->status = $status;

        if (isset($data['result'])) {
            $job->result = $data['result'];
        }

        if (isset($data['neo4jNodeId'])) {
            $job->neo4jNodeId = $data['neo4jNodeId'];
        }

        if (isset($data['metadata'])) {
            $job->metadata = array_merge($job->metadata, $data['metadata']);
        }

        if (isset($data['errorMessage'])) {
            $job->errorMessage = $data['errorMessage'];
        }

        if (in_array($status, ['completed', 'failed'])) {
            $job->completedAt = new \DateTimeImmutable();
        }
    }
}

