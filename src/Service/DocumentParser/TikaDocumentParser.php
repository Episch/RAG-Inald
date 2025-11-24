<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use App\Service\DocumentExtractor\TikaExtractorService;

/**
 * Apache Tika Document Parser
 * 
 * Universal fallback parser that supports all formats via Tika
 */
class TikaDocumentParser implements DocumentParserInterface
{
    public function __construct(
        private readonly TikaExtractorService $tikaExtractor
    ) {
    }

    public function extractText(string $filePath): string
    {
        return $this->tikaExtractor->extractText($filePath);
    }

    public function supports(string $mimeType): bool
    {
        // Tika supports almost all formats - use as universal fallback
        return true;
    }

    public function getPriority(): int
    {
        // Lowest priority - only use as fallback
        return 0;
    }

    public function getName(): string
    {
        return 'Tika Universal Parser';
    }
}

