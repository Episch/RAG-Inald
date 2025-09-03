<?php
namespace App\Controller;

use App\Dto\Extraction;
use App\Dto\QueueResponse;
use App\Message\ExtractorMessage;
use App\Service\QueueStatsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class ExtractionController
{
    public function __construct(
        private MessageBusInterface $bus,
        private QueueStatsService $queueStats,
        private LoggerInterface $logger,
        private SerializerInterface $serializer
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Extraction $data */
        $data = $this->serializer->deserialize($request->getContent(), Extraction::class, 'json');
        
        // Generate unique request ID
        $requestId = uniqid('ext_', true);
        
        // Log the request
        $this->logger->info('Document extraction request received', [
            'request_id' => $requestId,
            'path' => $data->getPath()
        ]);
        
        // Dispatch message to queue
        $message = new ExtractorMessage(
            path: $data->getPath(),
            saveAsFile: $data->isSaveAsFile(),
            outputFilename: $data->getOutputFilename()
        );
        $this->bus->dispatch($message);
        
        // Increment queue counter and get current count
        $this->queueStats->incrementQueueCounter();
        $queueCount = $this->queueStats->getQueueCounterValue();
        
        // Estimate processing time based on typical extraction pipeline
        $estimatedTime = $this->estimateProcessingTime($data->getPath());

        // Create standardized queue response using DTO
        $response = QueueResponse::createExtractionResponse(
            requestId: $requestId,
            path: $data->getPath(),
            queueCount: $queueCount,
            estimatedTime: $estimatedTime
        );

        return new JsonResponse($response, 202);
    }

    /**
     * Estimate processing time for document extraction pipeline
     */
    private function estimateProcessingTime(string $path): string
    {
        // Base time for extraction pipeline
        $baseTime = 15; // Tika + Optimization + LLM takes longer than simple LLM
        
        // Add time based on path complexity (rough estimation)
        $pathComplexity = strlen($path) > 20 ? 5 : 0;
        
        $totalSeconds = $baseTime + $pathComplexity;
        
        if ($totalSeconds < 60) {
            return "{$totalSeconds} seconds";
        } elseif ($totalSeconds < 3600) {
            $minutes = intval($totalSeconds / 60);
            return "{$minutes} minute(s)";
        } else {
            $hours = round($totalSeconds / 3600, 1);
            return "{$hours} hour(s)";
        }
    }
}
