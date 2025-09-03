<?php
namespace App\MessageHandler;

use App\Message\ExtractorMessage;
use App\Service\Connector\TikaConnector;

use App\Service\PromptRenderer;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class ExtractorMessageHandler
{
    private Finder $finder;
    private TikaConnector $extractorConnector;
    private string $promptsPath;
    private string $documentStoragePath;

    public function __construct(
        Finder $finder, 
        TikaConnector $extractorConnector, 
        string $promptsPath,
        string $documentStoragePath
    ) {
        $this->finder = $finder;
        $this->extractorConnector = $extractorConnector;
        $this->promptsPath = $promptsPath;
        $this->documentStoragePath = rtrim($documentStoragePath, '/') . '/';
    }

    public function __invoke(ExtractorMessage $message)
    {
        $startTime = microtime(true);
        $path = $message->path;
        
        // Use configured storage path instead of hardcoded path
        $secureBasePath = $this->documentStoragePath;

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
                error_log("❌ Extraction file save failed: " . $e->getMessage() . " - Execution time: {$executionTime}s");
                throw $e;
            }
        }

        $executionTime = round(microtime(true) - $startTime, 3);
        error_log("✅ Extraction completed - Total: {$executionTime}s (Tika: {$tikaTime}s, Optimization: {$optimizationTime}s, Prompt: {$promptTime}s)");

        return 0;
    }

    /**
     * Save extraction data and prepared prompt to file
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
            'created_at' => date('c')
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
        
        error_log("📁 Extraction data saved: FileId: {$extractionFileId}, Output: {$filename}");
    }
}
