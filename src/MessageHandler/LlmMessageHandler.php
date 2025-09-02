<?php

namespace App\MessageHandler;

use App\Message\LlmMessage;
use App\Service\Connector\LlmConnector;
use App\Service\TokenChunker;
use App\Service\QueueStatsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LlmMessageHandler
{
    private LlmConnector $llmConnector;
    private TokenChunker $tokenChunker;
    private LoggerInterface $logger;
    private QueueStatsService $queueStats;
    private string $outputPath;

    public function __construct(
        LlmConnector $llmConnector,
        TokenChunker $tokenChunker,
        LoggerInterface $logger,
        QueueStatsService $queueStats,
        string $outputPath = null
    ) {
        $this->llmConnector = $llmConnector;
        $this->tokenChunker = $tokenChunker;
        $this->logger = $logger;
        $this->queueStats = $queueStats;
        $this->outputPath = $outputPath ?: __DIR__ . '/../../var/llm_output/';
        
        // Ensure output directory exists
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    public function __invoke(LlmMessage $message): int
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing LLM message', [
            'model' => $message->model,
            'type' => $message->type,
            'prompt_length' => strlen($message->prompt),
            'request_id' => $message->requestId
        ]);

        try {
            // Check if prompt needs chunking (for very large prompts)
            $response = $this->processPrompt($message);
            
            // Save response to file system
            $outputFile = $this->saveResponse($message, $response);
            
            $executionTime = round(microtime(true) - $startTime, 3);
            
            $this->logger->info('LLM processing completed', [
                'request_id' => $message->requestId,
                'output_file' => basename($outputFile),
                'model' => $message->model,
                'execution_time' => $executionTime . 's'
            ]);
            
            // Decrement queue counter when message is processed successfully
            $this->queueStats->decrementQueueCounter();
            
            return 0;
            
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 3);
            
            $this->logger->error('LLM processing failed', [
                'request_id' => $message->requestId,
                'error' => $e->getMessage(),
                'model' => $message->model,
                'execution_time' => $executionTime . 's'
            ]);
            
            // Decrement counter even on failure to keep count accurate
            $this->queueStats->decrementQueueCounter();
            
            throw $e;
        }
    }

    private function processPrompt(LlmMessage $message): array
    {
        $options = [
            'temperature' => $message->temperature,
            'num_predict' => $message->maxTokens,
        ];

        switch ($message->type) {
            case 'categorize':
                $response = $this->llmConnector->promptForCategorization($message->prompt, $message->model);
                break;
                
            case 'chat':
                // For chat, we expect the prompt to be a JSON array of messages
                $messages = json_decode($message->prompt, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON in chat prompt');
                }
                $response = $this->llmConnector->chatCompletion($messages, $message->model, $options);
                break;
                
            case 'generate':
            default:
                $response = $this->llmConnector->generateText($message->prompt, $message->model, $options);
                break;
        }

        $content = $response->getContent();
        $decodedResponse = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from LLM: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    private function saveResponse(LlmMessage $message, array $response): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $requestId = $message->requestId ?: uniqid();
        $filename = "llm_response_{$message->model}_{$message->type}_{$timestamp}_{$requestId}.json";
        
        $outputFile = $this->outputPath . $filename;
        
        $outputData = [
            'request' => [
                'prompt' => mb_substr($message->prompt, 0, 500) . '...', // Truncate for logging
                'model' => $message->model,
                'type' => $message->type,
                'temperature' => $message->temperature,
                'max_tokens' => $message->maxTokens,
                'request_id' => $requestId,
                'timestamp' => date('c'),
            ],
            'response' => $response,
        ];
        
        $jsonOutput = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($outputFile, $jsonOutput) === false) {
            throw new \RuntimeException("Failed to write LLM response to file: {$outputFile}");
        }
        
        return $outputFile;
    }
}
