<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Zentraler Service für die Verwaltung von Dateien im Storage-System
 */
class FileStorageService
{
    private string $storageBasePath;
    private string $publicStoragePath;
    private string $llmOutputPath;
    private LoggerInterface $logger;

    public function __construct(
        string $documentStoragePath,
        string $llmOutputPath,
        LoggerInterface $logger
    ) {
        $this->storageBasePath = rtrim($documentStoragePath, '/') . '/';
        $this->publicStoragePath = $this->storageBasePath;
        $this->llmOutputPath = rtrim($llmOutputPath, '/') . '/';
        $this->logger = $logger;
        
        // Ensure directories exist
        $this->ensureDirectoriesExist();
    }

    /**
     * Speichere Extraction-Daten als Datei
     */
    public function saveExtractionData(
        string $extractionFileId, 
        array $data, 
        string $outputFilename = ''
    ): string {
        $filename = $outputFilename ?: "extraction_{$extractionFileId}.json";
        $filePath = $this->publicStoragePath . $filename;
        
        $outputData = array_merge($data, [
            'file_id' => $extractionFileId,
            'type' => 'extraction',
            'created_at' => date('c')
        ]);
        
        $jsonOutput = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filePath, $jsonOutput) === false) {
            throw new \RuntimeException("Failed to write extraction data to: {$filePath}");
        }

        $this->logger->info('Extraction data saved', [
            'file_id' => $extractionFileId,
            'filename' => $filename,
            'path' => $filePath
        ]);

        return $filePath;
    }

    /**
     * Speichere LLM-Response als Datei
     */
    public function saveLlmResponse(
        string $llmFileId, 
        array $data, 
        string $outputFilename = ''
    ): string {
        $filename = $outputFilename ?: "llm_response_{$llmFileId}.json";
        $filePath = $this->llmOutputPath . $filename;
        
        $outputData = array_merge($data, [
            'file_id' => $llmFileId,
            'type' => 'llm_response',
            'created_at' => date('c')
        ]);
        
        $jsonOutput = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filePath, $jsonOutput) === false) {
            throw new \RuntimeException("Failed to write LLM response to: {$filePath}");
        }

        $this->logger->info('LLM response saved', [
            'file_id' => $llmFileId,
            'filename' => $filename,
            'path' => $filePath
        ]);

        return $filePath;
    }

    /**
     * Finde und lade Extraction-Datei nach FileId
     */
    public function findExtractionFile(string $fileId): ?array
    {
        $searchPaths = [
            $this->publicStoragePath,
            $this->llmOutputPath,
        ];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            // Search for files containing the fileId
            $files = glob($searchPath . "*{$fileId}*.json", GLOB_BRACE);

            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && 
                        isset($data['file_id']) && $data['file_id'] === $fileId) {
                        return $data;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Finde und lade LLM-Datei nach FileId
     */
    public function findLlmFile(string $fileId): ?array
    {
        $searchPaths = [
            $this->llmOutputPath,
            $this->publicStoragePath,
        ];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            // Search for files containing the fileId
            $files = glob($searchPath . "*{$fileId}*.json", GLOB_BRACE);

            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && 
                        isset($data['file_id']) && $data['file_id'] === $fileId) {
                        return $data;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extrahiere Tika-Inhalt aus Extraction-Datei
     */
    public function getExtractionContent(string $fileId): ?string
    {
        $data = $this->findExtractionFile($fileId);
        
        if ($data === null) {
            return null;
        }

        if (isset($data['tika_extraction'])) {
            return is_string($data['tika_extraction']) 
                ? $data['tika_extraction'] 
                : json_encode($data['tika_extraction'], JSON_PRETTY_PRINT);
        }

        // Fallback: look for extracted content in other fields
        $contentFields = ['extracted_entities', 'llm_response', 'input'];
        foreach ($contentFields as $field) {
            if (isset($data[$field])) {
                return is_string($data[$field]) 
                    ? $data[$field] 
                    : json_encode($data[$field], JSON_PRETTY_PRINT);
            }
        }

        return null;
    }

    /**
     * Extrahiere LLM-Response-Daten
     */
    public function getLlmResponseData(string $fileId): ?array
    {
        $data = $this->findLlmFile($fileId);
        
        if ($data === null) {
            return null;
        }

        // Return the response data
        return $data['response'] ?? $data;
    }

    /**
     * Generiere eine eindeutige FileId
     */
    public function generateFileId(string $type): string
    {
        return $type . '_' . uniqid() . '_' . date('Y-m-d_H-i-s');
    }

    /**
     * Liste alle verfügbaren Dateien eines bestimmten Typs
     */
    public function listFiles(string $type = ''): array
    {
        $files = [];
        $searchPaths = [$this->publicStoragePath, $this->llmOutputPath];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $pattern = $type ? "*{$type}*.json" : "*.json";
            $foundFiles = glob($searchPath . $pattern, GLOB_BRACE);

            foreach ($foundFiles as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['file_id'])) {
                        $files[] = [
                            'file_id' => $data['file_id'],
                            'type' => $data['type'] ?? 'unknown',
                            'created_at' => $data['created_at'] ?? filemtime($file),
                            'path' => $file,
                            'size' => filesize($file)
                        ];
                    }
                }
            }
        }

        // Sort by creation time (newest first)
        usort($files, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $files;
    }

    /**
     * Lösche eine Datei nach FileId
     */
    public function deleteFile(string $fileId): bool
    {
        $searchPaths = [$this->publicStoragePath, $this->llmOutputPath];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $files = glob($searchPath . "*{$fileId}*.json", GLOB_BRACE);

            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && 
                        isset($data['file_id']) && $data['file_id'] === $fileId) {
                        
                        if (unlink($file)) {
                            $this->logger->info('File deleted', [
                                'file_id' => $fileId,
                                'path' => $file
                            ]);
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Stelle sicher, dass alle nötigen Verzeichnisse existieren
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [$this->storageBasePath, $this->publicStoragePath, $this->llmOutputPath];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: {$dir}");
                }
            }
        }
    }
}
