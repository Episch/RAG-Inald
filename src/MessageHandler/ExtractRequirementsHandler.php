<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\Schema\SoftwareApplicationDTO;
use App\DTO\Schema\SoftwareRequirementsDTO;
use App\Message\ExtractRequirementsMessage;
use App\Service\DocumentExtractor\TikaExtractorService;
use App\Service\Embeddings\OllamaEmbeddingsService;
use App\Service\LLM\OllamaLLMService;
use App\Service\Neo4j\Neo4jConnectorService;
use HelgeSverre\Toon\Toon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async Handler for Requirements Extraction Pipeline
 * 
 * Pipeline:
 * 1. Extract text from document (Tika)
 * 2. Send to LLM with TOON-formatted prompt
 * 3. Parse requirements from LLM response
 * 4. Generate embeddings (Ollama)
 * 5. Store in Neo4j graph database
 */
#[AsMessageHandler]
class ExtractRequirementsHandler
{
    public function __construct(
        private readonly TikaExtractorService $tikaExtractor,
        private readonly OllamaLLMService $llmService,
        private readonly OllamaEmbeddingsService $embeddingsService,
        private readonly Neo4jConnectorService $neo4jConnector,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ExtractRequirementsMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Starting requirements extraction', [
            'job_id' => $message->jobId,
            'document' => basename($message->documentPath),
            'project' => $message->projectName,
        ]);

        try {
            // Step 1: Extract text from document
            $tikaStart = microtime(true);
            $extractedText = $this->tikaExtractor->extractText($message->documentPath);
            $tikaDuration = microtime(true) - $tikaStart;

            // Step 2: Generate requirements using LLM with TOON format
            $llmStart = microtime(true);
            $requirements = $this->extractRequirementsWithLLM($extractedText, $message->projectName, $message->extractionOptions);
            $llmDuration = microtime(true) - $llmStart;

            // Step 3: Create Software Application DTO
            $application = new SoftwareApplicationDTO(
                name: $message->projectName,
                description: "Requirements extracted from " . basename($message->documentPath),
                requirements: $requirements
            );

            // Step 4: Generate embeddings for each requirement
            $embeddingsStart = microtime(true);
            $embeddings = [];
            foreach ($requirements as $index => $requirement) {
                $embeddingText = "{$requirement->name}: {$requirement->description}";
                $embeddings[$index] = $this->embeddingsService->embed($embeddingText);
            }
            $embeddingsDuration = microtime(true) - $embeddingsStart;

            // Step 5: Store in Neo4j
            $neo4jStart = microtime(true);
            $nodeId = $this->neo4jConnector->storeSoftwareApplication($application, $embeddings);
            $neo4jDuration = microtime(true) - $neo4jStart;

            $totalDuration = microtime(true) - $startTime;

            $this->logger->info('Requirements extraction completed', [
                'job_id' => $message->jobId,
                'requirements_count' => count($requirements),
                'neo4j_node_id' => $nodeId,
                'total_duration_seconds' => round($totalDuration, 3),
                'breakdown' => [
                    'tika' => round($tikaDuration, 3),
                    'llm' => round($llmDuration, 3),
                    'embeddings' => round($embeddingsDuration, 3),
                    'neo4j' => round($neo4jDuration, 3),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Requirements extraction failed', [
                'job_id' => $message->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract requirements using LLM with TOON format
     * 
     * @return SoftwareRequirementsDTO[]
     */
    private function extractRequirementsWithLLM(string $documentText, string $projectName, array $options): array
    {
        // Build extraction prompt
        $prompt = $this->buildExtractionPrompt($projectName);

        // Context as TOON format (for token efficiency)
        $context = [
            'project_name' => $projectName,
            'document_text' => $documentText,
        ];

        // Generate with LLM
        $response = $this->llmService->generate($prompt, $context, $options);

        // Parse requirements from response
        return $this->parseRequirementsFromResponse($response['response']);
    }

    /**
     * Build extraction prompt for LLM
     */
    private function buildExtractionPrompt(string $projectName): string
    {
        return <<<PROMPT
You are a requirements engineer analyzing software requirements documents.

Your task: Extract all software requirements from the provided document text and structure them according to Schema.org SoftwareRequirement format.

For each requirement, provide:
- identifier: Unique ID (e.g., REQ-001, FR-01, NFR-01)
- name: Short title
- description: Detailed description
- requirementType: One of: functional, non-functional, technical, business, security, performance, usability
- priority: One of: must, should, could, wont (MoSCoW method)
- category: Optional grouping (e.g., "User Management", "Authentication")
- tags: Array of relevant keywords

Output the requirements in TOON format like this:

```toon
requirements[3]:
  - identifier: REQ-001
    name: User Login
    description: The system shall allow users to log in using email and password
    requirementType: functional
    priority: must
    category: Authentication
    tags[2]: login, security
  - identifier: REQ-002
    name: Response Time
    description: The system shall respond to user actions within 200ms
    requirementType: performance
    priority: should
    category: Performance
    tags[1]: performance
  - identifier: REQ-003
    name: Data Encryption
    description: All user data must be encrypted at rest using AES-256
    requirementType: security
    priority: must
    category: Security
    tags[2]: encryption, security
```

Analyze the document and extract ALL requirements.
PROMPT;
    }

    /**
     * Parse requirements from LLM response
     * 
     * @return SoftwareRequirementsDTO[]
     */
    private function parseRequirementsFromResponse(string $response): array
    {
        try {
            // Try to parse as TOON format
            $data = $this->llmService->parseToonResponse($response);

            if (isset($data['requirements']) && is_array($data['requirements'])) {
                return array_map(
                    fn($req) => SoftwareRequirementsDTO::fromArray($req),
                    $data['requirements']
                );
            }

            // Fallback: parse entire response as TOON
            if (!empty($data)) {
                return array_map(
                    fn($req) => SoftwareRequirementsDTO::fromArray($req),
                    array_values($data)
                );
            }

            $this->logger->warning('No requirements found in LLM response');
            return [];

        } catch (\Exception $e) {
            $this->logger->error('Failed to parse LLM response', [
                'error' => $e->getMessage(),
                'response_preview' => substr($response, 0, 500),
            ]);

            return [];
        }
    }
}

