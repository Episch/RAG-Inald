<?php
namespace App\MessageHandler;

use App\Message\ExtractorMessage;
use App\Service\Connector\TikaConnector;
use App\Service\Connector\LlmConnector;
use App\Service\PromptRenderer;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class ExtractorMessageHandler
{
    private Finder $finder;
    private TikaConnector $extractorConnector;
    private LlmConnector $llmConnector;
    private string $promptsPath;

    public function __construct(
        Finder $finder, 
        TikaConnector $extractorConnector, 
        LlmConnector $llmConnector,
        string $promptsPath
    ) {
        $this->finder = $finder;
        $this->extractorConnector = $extractorConnector;
        $this->llmConnector = $llmConnector;
        $this->promptsPath = $promptsPath;
    }

    public function __invoke(ExtractorMessage $message)
    {
        $startTime = microtime(true);
        $secureBasePath = __DIR__ . '/../../public/storage/'; // TODO: remove it with config list of folders
        $path = $message->path;

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

        // 🚀 Send prompt to LLM for categorization
        try {
            $llmStart = microtime(true);
            $llmResponse = $this->llmConnector->promptForCategorization($fullPrompt);
            $llmContent = $llmResponse->getContent();
            $llmTime = round(microtime(true) - $llmStart, 3);
            
            $llmData = json_decode($llmContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from LLM: ' . json_last_error_msg());
            }
            
            // Save LLM response to file
            $outputPath = $secureBasePath . $path . '/../';
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "llm_categorization_{$timestamp}.json";
            $fullOutputPath = $outputPath . $filename;
            
            $outputData = [
                'input' => [
                    'path' => $path,
                    'extracted_content_length' => strlen($extractContent ?? ''),
                    'prompt_length' => strlen($fullPrompt),
                    'timestamp' => date('c')
                ],
                'llm_response' => $llmData,
                'extracted_entities' => $llmData['response'] ?? $llmContent,
            ];
            
            $jsonOutput = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (!is_dir($outputPath)) {
                mkdir($outputPath, 0755, true);
            }
            
            if (file_put_contents($fullOutputPath, $jsonOutput) === false) {
                throw new \RuntimeException("Failed to write LLM output to: {$fullOutputPath}");
            }
            
            $executionTime = round(microtime(true) - $startTime, 3);
            
            // Success logging with detailed performance breakdown
            error_log("✅ LLM categorization completed. Output: {$filename} - Total: {$executionTime}s (Tika: {$tikaTime}s, Optimization: {$optimizationTime}s, Prompt: {$promptTime}s, LLM: {$llmTime}s)");
            
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 3);
            $llmTime = isset($llmStart) ? round(microtime(true) - $llmStart, 3) : 0;
            
            // Log error with execution time and performance breakdown
            $performanceInfo = isset($tikaTime) 
                ? " - Total: {$executionTime}s (Tika: {$tikaTime}s, Optimization: " . ($optimizationTime ?? 0) . "s, Prompt: " . ($promptTime ?? 0) . "s, LLM: {$llmTime}s)"
                : " - Execution time: {$executionTime}s";
                
            error_log("❌ LLM categorization failed: " . $e->getMessage() . $performanceInfo);
            
            // Save error information for debugging
            $errorPath = $secureBasePath . $path . '/../llm_error_' . date('Y-m-d_H-i-s') . '.json';
            $errorData = [
                'error' => $e->getMessage(),
                'path' => $path,
                'prompt_preview' => substr($fullPrompt, 0, 500) . '...',
                'timestamp' => date('c')
            ];
            file_put_contents($errorPath, json_encode($errorData, JSON_PRETTY_PRINT));
        }

        return 0;
    }

}
