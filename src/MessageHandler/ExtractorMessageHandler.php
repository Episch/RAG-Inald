<?php
namespace App\MessageHandler;

use App\Message\ExtractorMessage;
use App\Service\Connector\TikaConnector;
use App\Service\PromptRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Message handler for document extraction using Apache Tika.
 * 
 * Processes extraction messages, extracts document content using Tika,
 * and saves results to the file system for further processing.
 */
class ExtractorMessageHandler
{
    /**
     * Initialize extractor handler with required dependencies.
     * 
     * @param Finder $finder File system finder for locating documents
     * @param TikaConnector $extractorConnector Apache Tika service connector
     * @param LoggerInterface $logger Logger for operation tracking
     * @param string $promptsPath Path to prompt template files
     * @param string $documentStoragePath Base path for document storage
     */
    public function __construct(
        private readonly Finder $finder,
        private readonly TikaConnector $extractorConnector,
        private readonly LoggerInterface $logger,
        private readonly string $promptsPath,
        private readonly string $documentStoragePath
    ) {}

    /**
     * Process document extraction message.
     * 
     * @param ExtractorMessage $message Message containing extraction parameters
     * 
     * @return void
     * @throws \RuntimeException If file operations fail
     */
    public function __invoke(ExtractorMessage $message): void
    {
        $startTime = microtime(true);
        $path = $message->path;
        
        // Use configured storage path instead of hardcoded path
        $secureBasePath = rtrim($this->documentStoragePath, '/') . '/';

        if (!is_dir($secureBasePath . $path)) {
            throw new \RuntimeException('Path does not exist: ' . $secureBasePath . $path);
        }
        $filename = 'Prozessorientierter_Bericht_-_David_Nagelschmidt.pdf';
        $files = $this->finder->files()->in($secureBasePath . $path)->name($filename);

        // Iterator in Array umwandeln und erste Datei nehmen
        $file = iterator_to_array($files, false)[0] ?? null;
        if (!$file) {
            throw new \RuntimeException('File does not exist: ' . $filename);
        }

        // $file ist jetzt ein SplFileInfo-Objekt
        $realPath = $file->getRealPath();

        // extract
        $tikaStart = microtime(true);
        $content = $this->extractorConnector->analyzeDocument($realPath);
        $tikaTime = round(microtime(true) - $tikaStart, 3);
        
        // reduce text noize
        $optimizationStart = microtime(true);
        $extractContent = $this->extractorConnector->parseOptimization($content?->getContent() ?: null);
        $optimizationTime = round(microtime(true) - $optimizationStart, 3);

        // prepare prompt
        $promptStart = microtime(true);
        $config = Yaml::parseFile($this->promptsPath);
        $template = $config['graph_mapping']['template'];
        $prompt = new PromptRenderer($template);
        $fullPrompt = $prompt->render(['tika_json' => $extractContent]);
        $promptTime = round(microtime(true) - $promptStart, 3);

        // Save extraction data and prepared prompt to file (if requested)
        if ($message->saveAsFile) {
            try {
                $this->saveExtractionData($message, $path, $extractContent, $fullPrompt, $startTime, $tikaTime, $optimizationTime, $promptTime);
            } catch (\Exception $e) {
                $executionTime = round(microtime(true) - $startTime, 3);
                $this->logger->error('Extraction file save failed', [
                    'error' => $e->getMessage(),
                    'execution_time' => $executionTime,
                    'path' => $path
                ]);
                throw $e;
            }
        }

        $executionTime = round(microtime(true) - $startTime, 3);
        $this->logger->info('Extraction completed successfully', [
            'total_time' => $executionTime,
            'tika_time' => $tikaTime,
            'optimization_time' => $optimizationTime,
            'prompt_time' => $promptTime,
            'path' => $path
        ]);
    }

    /**
     * Save extraction data and prepared prompt to file system.
     * 
     * @param ExtractorMessage $message Original extraction message
     * @param string $path Document path 
     * @param string|null $extractContent Extracted document content
     * @param string $fullPrompt Prepared prompt for LLM processing
     * @param float $startTime Processing start time
     * @param float $tikaTime Tika processing duration
     * @param float $optimizationTime Content optimization duration
     * @param float $promptTime Prompt preparation duration
     * 
     * @return void
     */
    private function saveExtractionData(
        ExtractorMessage $message,
        string $path,
        ?string $extractContent,
        string $fullPrompt,
        float $startTime,
        float $tikaTime,
        float $optimizationTime,
        float $promptTime
    ): void {
        // Generate unique file ID for this extraction
        $extractionFileId = 'ext_' . uniqid() . '_' . date('Y-m-d_H-i-s');
        
        // Determine output path and filename
        $outputPath = $this->documentStoragePath . $path . '/../';
        $filename = $message->outputFilename ?: "extraction_{$extractionFileId}.json";
        $fullOutputPath = $outputPath . $filename;
        
        // Prepare extraction data (without LLM response)
        $outputData = [
            'file_id' => $extractionFileId,
            'type' => 'extraction',
            'input' => [                
                'original_filename' => $filename,
                'path' => $path,
                'extracted_content_length' => strlen($extractContent ?? ''),
                'prompt_length' => strlen($fullPrompt),
                'timestamp' => date('c')
            ],
            'tika_extraction' => $extractContent,
            'prepared_prompt' => $fullPrompt,
            'performance' => [
                'tika_time_seconds' => $tikaTime,
                'optimization_time_seconds' => $optimizationTime,
                'prompt_time_seconds' => $promptTime,
                'total_time_seconds' => round(microtime(true) - $startTime, 3)
            ],
            'created_at' => date('c'),
        ];
        
        $jsonOutput = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Ensure output directory exists
        if (!is_dir($outputPath)) {
            if (!mkdir($outputPath, 0755, true)) {
                throw new \RuntimeException("Failed to create output directory: {$outputPath}");
            }
        }
        
        // Write file
        if (file_put_contents($fullOutputPath, $jsonOutput) === false) {
            throw new \RuntimeException("Failed to write extraction output to: {$fullOutputPath}");
        }
        
        $this->logger->info('Extraction data saved to file', [
            'file_id' => $extractionFileId,
            'filename' => $filename,
            'file_path' => $fullOutputPath,
            'path' => $path
        ]);
    }
}
