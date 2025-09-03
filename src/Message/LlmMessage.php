<?php

namespace App\Message;

class LlmMessage
{
    public function __construct(
        public readonly string $prompt,
        public readonly string $model = 'llama3.2',
        public readonly float $temperature = 0.7,
        public readonly int $maxTokens = 2048,
        public readonly string $requestId = '',
        public readonly string $type = 'generate', // 'generate' or 'chat' or 'categorize'
        public readonly bool $useExtractionFile = false,
        public readonly string $extractionFileId = '',
        public readonly bool $saveAsFile = true,
        public readonly string $outputFilename = ''
    ) {}
}
