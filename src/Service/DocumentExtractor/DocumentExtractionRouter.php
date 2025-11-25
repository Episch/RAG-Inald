<?php

declare(strict_types=1);

namespace App\Service\DocumentExtractor;

use App\Contract\DocumentParserInterface;
use Psr\Log\LoggerInterface;

/**
 * Document Extraction Router
 * 
 * Orchestrates format detection and routes to appropriate parser
 * 
 * Architecture:
 * 1. Detect document format (MIME type)
 * 2. Find best parser for format (by priority)
 * 3. Extract text with selected parser
 * 4. Fallback to Tika if primary parser fails
 */
class DocumentExtractionRouter
{
    /** @var DocumentParserInterface[] */
    private array $parsers = [];

    public function __construct(
        private readonly DocumentFormatDetector $formatDetector,
        private readonly LoggerInterface $logger,
        iterable $parsers = []
    ) {
        foreach ($parsers as $parser) {
            $this->registerParser($parser);
        }

        // Sort parsers by priority (highest first)
        usort($this->parsers, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * Register a document parser
     */
    public function registerParser(DocumentParserInterface $parser): void
    {
        $this->parsers[] = $parser;
    }

    /**
     * Extract text from document using best available parser
     * 
     * @param string $filePath Path to document
     * @return array{text: string, parser: string, mime_type: string, format: string}
     * @throws \RuntimeException If extraction fails
     */
    public function extractText(string $filePath): array
    {
        $startTime = microtime(true);

        // Step 1: Detect format
        $mimeType = $this->formatDetector->detectMimeType($filePath);
        $formatName = $this->formatDetector->getFormatName($mimeType);

        $this->logger->info('Document format detected', [
            'file' => basename($filePath),
            'mime_type' => $mimeType,
            'format' => $formatName,
        ]);

        // Step 2: Find compatible parsers
        $compatibleParsers = array_filter(
            $this->parsers,
            fn($parser) => $parser->supports($mimeType)
        );

        if (empty($compatibleParsers)) {
            $this->logger->error('No compatible parser found', [
                'mime_type' => $mimeType,
                'available_parsers' => array_map(fn($p) => [
                    'name' => $p->getName(),
                    'priority' => $p->getPriority(),
                ], $this->parsers),
            ]);
            
            throw new \RuntimeException("No parser available for MIME type: {$mimeType}");
        }

        $this->logger->info('Compatible parsers found', [
            'count' => count($compatibleParsers),
            'parsers' => array_map(fn($p) => [
                'name' => $p->getName(),
                'priority' => $p->getPriority(),
            ], $compatibleParsers),
        ]);

        // Step 3: Try parsers in priority order
        $lastException = null;

        foreach ($compatibleParsers as $parser) {
            try {
                $text = $parser->extractText($filePath);
                $duration = microtime(true) - $startTime;

                $this->logger->info('Document extraction successful', [
                    'file' => basename($filePath),
                    'parser' => $parser->getName(),
                    'mime_type' => $mimeType,
                    'text_length' => strlen($text),
                    'duration_seconds' => round($duration, 3),
                ]);

                return [
                    'text' => $text,
                    'parser' => $parser->getName(),
                    'mime_type' => $mimeType,
                    'format' => $formatName,
                ];
            } catch (\Exception $e) {
                $this->logger->warning('Parser failed, trying next', [
                    'parser' => $parser->getName(),
                    'file' => basename($filePath),
                    'error' => $e->getMessage(),
                ]);

                $lastException = $e;
            }
        }

        // All parsers failed
        throw new \RuntimeException(
            "All parsers failed for {$mimeType}. Last error: " . ($lastException?->getMessage() ?? 'unknown'),
            0,
            $lastException
        );
    }

    /**
     * Get list of supported MIME types
     * 
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array
    {
        $mimeTypes = [];

        foreach ($this->parsers as $parser) {
            // This is a simplified approach - in production, parsers should
            // provide a list of supported MIME types
            $mimeTypes[] = get_class($parser);
        }

        return array_unique($mimeTypes);
    }
}

