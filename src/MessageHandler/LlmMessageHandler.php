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
            // Load extraction file content if requested
            $finalPrompt = $this->prepareFinalPrompt($message);
            
            // Create a modified message with the final prompt
            $modifiedMessage = new LlmMessage(
                prompt: $finalPrompt,
                model: $message->model,
                temperature: $message->temperature,
                maxTokens: $message->maxTokens,
                requestId: $message->requestId,
                type: $message->type,
                useExtractionFile: $message->useExtractionFile,
                extractionFileId: $message->extractionFileId,
                saveAsFile: $message->saveAsFile,
                outputFilename: $message->outputFilename
            );
            
            // Check if prompt needs chunking (for very large prompts)
            $response = $this->processPrompt($modifiedMessage);
            
            // Save response to file system if requested
            $outputFile = null;
            if ($message->saveAsFile) {
                $outputFile = $this->saveResponse($modifiedMessage, $response);
            }
            
            $executionTime = round(microtime(true) - $startTime, 3);
            
            $this->logger->info('LLM processing completed', [
                'request_id' => $message->requestId,
                'output_file' => $outputFile ? basename($outputFile) : 'not_saved',
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

        // Determine the processing type - use categorize if extraction file is being used
        $processingType = $message->useExtractionFile ? 'categorize' : $message->type;
        
        switch ($processingType) {
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
        // Generate unique file ID for this LLM response
        $llmFileId = 'llm_' . uniqid() . '_' . date('Y-m-d_H-i-s');
        $timestamp = date('Y-m-d_H-i-s');
        $requestId = $message->requestId ?: uniqid();
        
        // Use categorization type if working with extraction file
        $responseType = $message->useExtractionFile ? 'categorization' : $message->type;
        $filename = $message->outputFilename ?: "llm_{$responseType}_{$llmFileId}.json";
        
        $outputFile = $this->outputPath . $filename;
        
        $outputData = [
            'file_id' => $llmFileId,
            'request' => [
                'prompt' => mb_substr($message->prompt, 0, 500) . '...', // Truncate for logging
                'model' => $message->model,
                'type' => $message->type,
                'temperature' => $message->temperature,
                'max_tokens' => $message->maxTokens,
                'request_id' => $requestId,
                'use_extraction_file' => $message->useExtractionFile,
                'extraction_file_id' => $message->extractionFileId,
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

    /**
     * Prepare the final prompt by combining user prompt with extraction file content if needed
     */
    private function prepareFinalPrompt(LlmMessage $message): string
    {
        if (!$message->useExtractionFile || empty($message->extractionFileId)) {
            return $message->prompt;
        }

        // Find extraction file by ID
        $extractionContent = $this->findExtractionFile($message->extractionFileId);
        
        if ($extractionContent === null) {
            $this->logger->warning('Extraction file not found, using original prompt only', [
                'extraction_file_id' => $message->extractionFileId,
                'request_id' => $message->requestId
            ]);
            return $message->prompt;
        }

        // Combine user prompt with extraction content
        if (strpos($extractionContent, 'Benutzer-Anfrage:') !== false || 
            strpos($extractionContent, 'Extraktions-Daten:') !== false) {
            // This looks like a prepared prompt, use it directly with user modification
            $combinedPrompt = str_replace(
                ['Benutzer-Anfrage:', 'Analysiere'],
                ["Benutzer-Anfrage: {$message->prompt}", $message->prompt ?: 'Analysiere'],
                $extractionContent
            );
        } else {
            // Raw extraction data, create structured prompt
            $combinedPrompt = "Benutzer-Anfrage: {$message->prompt}\n\n";
            $combinedPrompt .= "Extraktions-Daten:\n{$extractionContent}\n\n";
            $combinedPrompt .= "Bitte antworte basierend auf den bereitgestellten Extraktions-Daten und der Benutzer-Anfrage.";
        }

        return $combinedPrompt;
    }

    /**
     * Find and load extraction file content by file ID
     */
    private function findExtractionFile(string $fileId): ?string
    {
        // Search in common extraction paths
        $searchPaths = [
            $this->outputPath . '../public/storage/',
            __DIR__ . '/../../public/storage/',
            __DIR__ . '/../../var/llm_output/'
        ];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $files = glob($searchPath . "**/*{$fileId}*.json", GLOB_BRACE);
            if (empty($files)) {
                $files = glob($searchPath . "*{$fileId}*.json", GLOB_BRACE);
            }

            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Check for both old and new format
                        if (isset($data['tika_extraction'])) {
                            // Old format with LLM response
                            return is_string($data['tika_extraction']) 
                                ? $data['tika_extraction'] 
                                : json_encode($data['tika_extraction'], JSON_PRETTY_PRINT);
                        } elseif (isset($data['prepared_prompt'])) {
                            // New format with prepared prompt (from refactored ExtractorMessageHandler)
                            return $data['prepared_prompt'];
                        }
                    }
                }
            }
        }

        return null;
    }
}
