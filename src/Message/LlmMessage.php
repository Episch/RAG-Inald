<?php

namespace App\Message;

class LlmMessage
{

    private const TYPE_GENERATE = 'generate';
    private const TYPE_CHAT = 'chat';
    private const TYPE_CATEGORIZE = 'categorize';
    private const MODEL_DEFAULT = 'mistral';
    private const TEMPERATURE_DEFAULT = 0.7;
    private const MAX_TOKENS_DEFAULT = 2048;

    public function __construct(
        public readonly string $prompt,
        public readonly string $model = self::MODEL_DEFAULT, // 'llama3.2', 'llama3.1', 'llama3', 'mistral', 'codellama', 'qwen2.5'
        public readonly float $temperature = self::TEMPERATURE_DEFAULT,
        public readonly int $maxTokens = self::MAX_TOKENS_DEFAULT,
        public readonly string $requestId = '',
        public readonly string $type = self::TYPE_GENERATE, // 'generate' or 'chat' or 'categorize'
        public readonly bool $useExtractionFile = false,
        public readonly string $extractionFileId = '',
        public readonly bool $saveAsFile = true,
        public readonly string $outputFilename = ''
    ) {}
}
