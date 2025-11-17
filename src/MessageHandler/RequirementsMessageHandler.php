<?php

namespace App\MessageHandler;

use App\Message\RequirementsMessage;
use App\Service\RequirementsExtractionService;
use App\Service\QueueStatsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler für Requirements-Extraktion Messages
 * 
 * Verarbeitet Requirements-Extraktion asynchron über die Queue.
 */
#[AsMessageHandler]
class RequirementsMessageHandler
{
    private readonly string $outputPath;

    public function __construct(
        private readonly RequirementsExtractionService $extractionService,
        private readonly QueueStatsService $queueStats,
        private readonly LoggerInterface $logger,
        ?string $outputPath = null
    ) {
        $this->outputPath = $outputPath ?: __DIR__ . '/../../var/requirements_output/';
        
        // Stelle sicher, dass Output-Verzeichnis existiert
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    public function __invoke(RequirementsMessage $message): int
    {
        $startTime = microtime(true);
        $requestId = $message->getRequestId();

        $this->logger->info('Processing requirements extraction message', [
            'request_id' => $requestId,
            'file_count' => count($message->filePaths),
            'model' => $message->model,
            'import_to_neo4j' => $message->importToNeo4j
        ]);

        try {
            // Validiere Dateipfade
            $this->validateFilePaths($message->filePaths);

            // Extrahiere Requirements
            $requirementsGraph = $this->extractionService->extractFromDocuments(
                filePaths: $message->filePaths,
                model: $message->model,
                importToNeo4j: $message->importToNeo4j
            );

            // Speichere Ergebnis als Datei
            $outputFile = null;
            if ($message->saveAsFile) {
                $outputFile = $this->saveRequirementsGraph($requirementsGraph, $message, $requestId);
            }

            $executionTime = round(microtime(true) - $startTime, 3);

            $this->logger->info('Requirements extraction completed successfully', [
                'request_id' => $requestId,
                'execution_time' => $executionTime . 's',
                'requirements_count' => count($requirementsGraph->requirements),
                'roles_count' => count($requirementsGraph->roles),
                'relationships_count' => count($requirementsGraph->relationships),
                'output_file' => $outputFile ? basename($outputFile) : 'not_saved',
                'neo4j_imported' => $message->importToNeo4j
            ]);

            // Dekrementiere Queue-Counter
            $this->queueStats->decrementQueueCounter();

            return 0;

        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 3);

            $this->logger->error('Requirements extraction failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time' => $executionTime . 's'
            ]);

            // Dekrementiere Counter auch bei Fehler
            $this->queueStats->decrementQueueCounter();

            throw $e;
        }
    }

    /**
     * Validiert die Dateipfade
     */
    private function validateFilePaths(array $filePaths): void
    {
        if (empty($filePaths)) {
            throw new \InvalidArgumentException('Keine Dateipfade angegeben');
        }

        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                throw new \InvalidArgumentException("Datei existiert nicht: {$filePath}");
            }

            if (!is_readable($filePath)) {
                throw new \InvalidArgumentException("Datei ist nicht lesbar: {$filePath}");
            }

            // Prüfe Dateityp (nur PDF und Excel erlaubt)
            $mimeType = mime_content_type($filePath);
            $allowedTypes = [
                'application/pdf',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet'
            ];

            if (!in_array($mimeType, $allowedTypes)) {
                $this->logger->warning('Unerwarteter Dateityp', [
                    'file' => basename($filePath),
                    'mime_type' => $mimeType
                ]);
            }
        }
    }

    /**
     * Speichert den Requirements-Graph als JSON-Datei
     */
    private function saveRequirementsGraph(
        $requirementsGraph,
        RequirementsMessage $message,
        string $requestId
    ): string {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $message->outputFilename ?: "requirements_{$requestId}_{$timestamp}.json";
        $fullPath = $this->outputPath . $filename;

        $outputData = [
            'request_id' => $requestId,
            'timestamp' => date('c'),
            'input' => [
                'files' => array_map('basename', $message->filePaths),
                'model' => $message->model,
                'import_to_neo4j' => $message->importToNeo4j
            ],
            'graph' => $requirementsGraph->toArray(),
            'statistics' => [
                'total_requirements' => count($requirementsGraph->requirements),
                'total_roles' => count($requirementsGraph->roles),
                'total_environments' => count($requirementsGraph->environments),
                'total_businesses' => count($requirementsGraph->businesses),
                'total_infrastructures' => count($requirementsGraph->infrastructures),
                'total_software_applications' => count($requirementsGraph->softwareApplications),
                'total_relationships' => count($requirementsGraph->relationships)
            ]
        ];

        $jsonOutput = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($fullPath, $jsonOutput) === false) {
            throw new \RuntimeException("Konnte Requirements-Graph nicht speichern: {$fullPath}");
        }

        $this->logger->info('Requirements graph saved to file', [
            'file' => $filename,
            'path' => $fullPath,
            'size' => filesize($fullPath)
        ]);

        return $fullPath;
    }
}

