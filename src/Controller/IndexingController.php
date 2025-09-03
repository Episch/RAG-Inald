<?php

namespace App\Controller;

use App\Constants\SystemConstants;
use App\Dto\IndexingRequest;
use App\Dto\QueueResponse;
use App\Message\IndexingMessage;
use App\Service\QueueStatsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class IndexingController
{
    public function __construct(
        private MessageBusInterface $bus,
        private QueueStatsService $queueStats,
        private LoggerInterface $logger,
        private SerializerInterface $serializer
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var IndexingRequest $data */
            $data = $this->serializer->deserialize($request->getContent(), IndexingRequest::class, 'json');
            
            // Debug: Log received data
            $this->logger->debug('Indexing request deserialized', [
                'entityType' => $data->entityType,
                'entityData_keys' => array_keys($data->entityData),
                'entityData_count' => count($data->entityData)
            ]);
            
            // Validate the request structure
            if (!$data->isValid()) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid indexing request structure',
                    'required_fields' => ['entityType', 'entityData with identifier'],
                    'received_data' => [
                        'entityType' => $data->entityType ?: '(empty)',
                        'entityData_keys' => array_keys($data->entityData),
                        'available_identifiers' => ['id', 'uuid', 'name', 'title']
                    ]
                ], 400);
            }

            // Generate unique request ID
            $requestId = uniqid('idx_', true);
            
            // Log the request
            $this->logger->info('Graph indexing request received', [
                'request_id' => $requestId,
                'entity_type' => $data->getEntityType(),
                'operation' => $data->getOperation(),
                'has_relationships' => !empty($data->getRelationships()),
                'data_keys' => array_keys($data->getEntityData())
            ]);
            
            // Dispatch message to queue
            $message = new IndexingMessage(
                entityType: $data->getEntityType(),
                entityData: $data->getEntityData(),
                relationships: $data->getRelationships(),
                metadata: $data->getMetadata(),
                operation: $data->getOperation(),
                indexes: $data->getIndexes(),
                useLlmFile: $data->isUseLlmFile(),
                llmFileId: $data->getLlmFileId()
            );
            
            $this->bus->dispatch($message);
            
            // Increment queue counter and get current count
            $this->queueStats->incrementQueueCounter();
            $queueCount = $this->queueStats->getQueueCounterValue();
            
            // Estimate processing time based on complexity
            $estimatedTime = $this->estimateProcessingTime($data);

            // Create standardized queue response using DTO
            $response = QueueResponse::createIndexingResponse(
                requestId: $requestId,
                entityType: $data->getEntityType(),
                operation: $data->getOperation(),
                entityCount: 1 + count($data->getRelationships()),
                queueCount: $queueCount,
                estimatedTime: $estimatedTime
            );

            return new JsonResponse($response, 202);
            
        } catch (\Exception $e) {
            $this->logger->error('Graph indexing request failed', [
                'error' => $e->getMessage(),
                'request_content' => $request->getContent()
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Failed to process indexing request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estimate processing time for graph indexing pipeline
     */
    private function estimateProcessingTime(IndexingRequest $data): string
    {
        // Base time for Neo4j operations
        $baseTime = 3;
        
        // Add time based on data complexity
        $dataComplexity = count($data->getEntityData()) * 0.5;
        
        // Add time for relationships
        $relationshipTime = count($data->getRelationships()) * 2;
        
        // Add time for indexes
        $indexTime = count($data->getIndexes()) * 1;
        
        // Operation complexity multiplier
        $operationMultiplier = match ($data->getOperation()) {
            'create' => 1.0,
            'update' => 1.2,
            'merge' => 1.5, // Most complex
            'delete' => 0.8,
            default => 1.0
        };
        
        $totalSeconds = intval(($baseTime + $dataComplexity + $relationshipTime + $indexTime) * $operationMultiplier);
        
        if ($totalSeconds < SystemConstants::SECONDS_PER_MINUTE) {
            return "{$totalSeconds} seconds";
        } elseif ($totalSeconds < 3600) {
            $minutes = intval($totalSeconds / SystemConstants::SECONDS_PER_MINUTE);
            return "{$minutes} minute(s)";
        } else {
            $hours = round($totalSeconds / 3600, 1);
            return "{$hours} hour(s)";
        }
    }
}
