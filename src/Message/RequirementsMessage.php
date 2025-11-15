<?php

namespace App\Message;

/**
 * Message für Requirements-Extraktion
 * 
 * Wird über Symfony Messenger Queue verarbeitet für asynchrone Requirements-Extraktion.
 */
class RequirementsMessage
{
    public function __construct(
        public readonly array $filePaths,
        public readonly string $model = 'llama3.2',
        public readonly bool $importToNeo4j = true,
        public readonly bool $saveAsFile = true,
        public readonly ?string $outputFilename = null,
        public readonly ?string $requestId = null,
        public readonly ?array $options = []
    ) {}

    public function getRequestId(): string
    {
        return $this->requestId ?? uniqid('req_ext_');
    }

    public function toArray(): array
    {
        return [
            'file_paths' => $this->filePaths,
            'model' => $this->model,
            'import_to_neo4j' => $this->importToNeo4j,
            'save_as_file' => $this->saveAsFile,
            'output_filename' => $this->outputFilename,
            'request_id' => $this->getRequestId(),
            'options' => $this->options ?? []
        ];
    }
}

