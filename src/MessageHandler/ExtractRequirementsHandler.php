<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\Schema\SoftwareApplication;
use App\DTO\Schema\SoftwareRequirements;
use App\Message\ExtractRequirementsMessage;
use App\Service\DocumentExtractor\DocumentExtractionRouter;
use App\Service\Embeddings\OllamaEmbeddingsService;
use App\Service\LLM\DocumentChunkerService;
use App\Service\LLM\OllamaLLMService;
use App\Service\Neo4j\Neo4jConnectorService;
use App\State\RequirementExtractionProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async Handler for Requirements Extraction Pipeline
 * 
 * Pipeline:
 * 1. Extract text from document (Format Router → Parsers)
 * 2. Send to LLM with JSON-formatted prompt
 * 3. Parse requirements from LLM response
 * 4. Generate embeddings (Ollama)
 * 5. Store in Neo4j graph database
 */
#[AsMessageHandler]
class ExtractRequirementsHandler
{
    public function __construct(
        private readonly DocumentExtractionRouter $extractionRouter,
        private readonly OllamaLLMService $llmService,
        private readonly DocumentChunkerService $chunkerService,
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
            // Step 1: Extract text from document (with format detection & routing)
            $extractionStart = microtime(true);
            $extractionResult = $this->extractionRouter->extractText($message->documentPath);
            $extractionDuration = microtime(true) - $extractionStart;

            $this->logger->info('Document extraction completed', [
                'job_id' => $message->jobId,
                'format' => $extractionResult['format'],
                'mime_type' => $extractionResult['mime_type'],
                'parser' => $extractionResult['parser'],
                'text_length' => strlen($extractionResult['text']),
            ]);

            // Step 2: Generate requirements using LLM with JSON format
            $llmStart = microtime(true);
            $requirements = $this->extractRequirementsWithLLM(
                $extractionResult['text'],
                $message->projectName,
                $message->extractionOptions
            );
            $llmDuration = microtime(true) - $llmStart;

            // Step 3: Create Software Application DTO
            $application = new SoftwareApplication(
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
                'format' => $extractionResult['format'],
                'parser' => $extractionResult['parser'],
                'total_duration_seconds' => round($totalDuration, 3),
                'breakdown' => [
                    'extraction' => round($extractionDuration, 3),
                    'llm' => round($llmDuration, 3),
                    'embeddings' => round($embeddingsDuration, 3),
                    'neo4j' => round($neo4jDuration, 3),
                ],
            ]);

            // Update job status to completed
            RequirementExtractionProcessor::updateJobStatus($message->jobId, 'completed', [
                'result' => $application,
                'neo4jNodeId' => $nodeId,
                'metadata' => [
                    'requirements_count' => count($requirements),
                    'format' => $extractionResult['format'],
                    'parser' => $extractionResult['parser'],
                    'total_duration_seconds' => round($totalDuration, 3),
                    'extraction_duration' => round($extractionDuration, 3),
                    'llm_duration' => round($llmDuration, 3),
                    'embeddings_duration' => round($embeddingsDuration, 3),
                    'neo4j_duration' => round($neo4jDuration, 3),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Requirements extraction failed', [
                'job_id' => $message->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update job status to failed
            RequirementExtractionProcessor::updateJobStatus($message->jobId, 'failed', [
                'errorMessage' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract requirements using LLM with JSON format and chunking for large documents
     * 
     * @return SoftwareRequirements[]
     */
    private function extractRequirementsWithLLM(string $documentText, string $projectName, array $options): array
    {
        $documentLength = strlen($documentText);
        
        // Check if chunking is needed
        $estimatedChunks = $this->chunkerService->estimateChunkCount($documentText);
        
        $this->logger->info('Starting LLM extraction', [
            'document_length' => $documentLength,
            'estimated_chunks' => $estimatedChunks,
            'model' => $options['model'] ?? 'default',
        ]);

        // Chunk document for large files
        $chunks = $this->chunkerService->chunkDocument($documentText);
        
        if (count($chunks) === 1) {
            // Single chunk - process normally
            return $this->extractRequirementsFromChunk($chunks[0], $projectName, $options, 1, 1);
        }

        // Multiple chunks - process each and merge results
        $this->logger->info('Processing document in multiple chunks', [
            'total_chunks' => count($chunks),
            'document_length' => $documentLength,
        ]);

        $allRequirements = [];
        
        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $totalChunksCount = count($chunks);
            
            $this->logger->info("Processing chunk {$chunkNumber}/{$totalChunksCount}", [
                'chunk_number' => $chunkNumber,
                'chunk_length' => strlen($chunk),
            ]);

            try {
                $chunkRequirements = $this->extractRequirementsFromChunk(
                    $chunk,
                    $projectName,
                    $options,
                    $chunkNumber,
                    count($chunks)
                );

                $this->logger->info("Chunk {$chunkNumber} processed successfully", [
                    'requirements_extracted' => count($chunkRequirements),
                ]);

                $allRequirements = array_merge($allRequirements, $chunkRequirements);

            } catch (\Exception $e) {
                $this->logger->error("Failed to process chunk {$chunkNumber}", [
                    'chunk_number' => $chunkNumber,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other chunks even if one fails
            }
        }

        $this->logger->info('All chunks processed', [
            'total_chunks' => count($chunks),
            'total_requirements' => count($allRequirements),
        ]);

        return $allRequirements;
    }

    /**
     * Extract requirements from a single chunk
     * 
     * @return SoftwareRequirements[]
     */
    private function extractRequirementsFromChunk(
        string $chunkText,
        string $projectName,
        array $options,
        int $chunkNumber,
        int $totalChunks
    ): array {
        // Build extraction prompt
        $prompt = $this->buildExtractionPrompt($projectName, $chunkNumber, $totalChunks);

        // Context as JSON format
        $context = [
            'project_name' => $projectName,
            'document_text' => $chunkText,
            'chunk_info' => [
                'current' => $chunkNumber,
                'total' => $totalChunks,
            ],
        ];

        // Ensure very high token limit for complete extraction (model-dependent)
        $options['max_tokens'] = $options['max_tokens'] ?? 32768;

        // Generate with LLM
        $response = $this->llmService->generate($prompt, $context, $options);

        $this->logger->info("LLM generation completed for chunk {$chunkNumber}", [
            'chunk_number' => $chunkNumber,
            'response_length' => strlen($response['response']),
            'tokens_used' => $response['total_tokens'] ?? 'unknown',
        ]);

        // Parse requirements from response
        return $this->parseRequirementsFromResponse($response['response']);
    }

    /**
     * Build extraction prompt for LLM
     */
    private function buildExtractionPrompt(string $projectName, int $chunkNumber = 1, int $totalChunks = 1): string
    {
        $chunkInfo = $totalChunks > 1 
            ? "\n📑 CHUNK INFO: You are processing chunk {$chunkNumber} of {$totalChunks}. Extract ALL requirements from THIS chunk only.\n" 
            : '';

        return <<<PROMPT
You are a precise requirements extraction tool. Your ONLY task is to extract requirements that are EXPLICITLY stated in the provided document.
{$chunkInfo}
⚠️ CRITICAL: DO NOT invent, assume, or add requirements that are not directly mentioned in the document text!

EXTRACTION RULES:
1. Extract ONLY what is EXPLICITLY written in the document
2. Do NOT add common/typical requirements unless they are in the document
3. Do NOT make assumptions about what the system "should" have
4. If the document mentions 8 requirements, extract exactly 8 (not 15, not 20)
5. Use ENGLISH for all output fields (translate if source is German/other language)
6. Keep names SHORT (max 5-8 words)
7. Always respond with valid JSON wrapped in ```json code blocks

OUTPUT FORMAT (IREB-enhanced with Risk Management & Stakeholders):

```json
{
  "requirements": [
    {
      "identifier": "FR-001",
      "name": "User Authentication",
      "description": "The system shall allow users to authenticate using email and password. The system must support password reset functionality and enforce strong password policies (min 8 characters, mixed case, numbers, special characters).",
      "requirementType": "functional",
      "priority": "must",
      "category": "User Management",
      "tags": ["authentication", "login", "security"],
      "rationale": "Users need secure access to protect personal data and comply with GDPR regulations.",
      "acceptanceCriteria": "Given a user on the login page, when valid credentials are entered, then access to the dashboard is granted within 2 seconds.",
      "source": "document",
      "author": "Max Mustermann",
      "involvedStakeholders": ["Security Team", "UX Designer", "Product Owner"],
      "risks": [
        {
          "description": "Password brute-force attacks could compromise user accounts",
          "severity": "high",
          "probability": "medium",
          "impact": "Account takeover and data breach",
          "mitigation": "Implement rate limiting and account lockout after failed attempts"
        }
      ],
      "constraints": [
        {
          "description": "Must comply with GDPR password storage requirements",
          "type": "legal"
        },
        {
          "description": "Authentication must complete within 2 seconds",
          "type": "performance"
        }
      ],
      "assumptions": [
        {
          "description": "Users have access to their email for password reset",
          "validated": true
        }
      ],
      "relatedRequirements": ["FR-002", "SEC-001"],
      "dependencies": {
        "dependsOn": ["FR-005"],
        "conflicts": [],
        "extends": []
      }
    }
  ]
}
```

FIELD RULES (IREB-standard with Risk & Stakeholder Management):
- identifier: FR-XXX (functional), SEC-XXX (security), PERF-XXX (performance), BUS-XXX (business), UX-XXX (usability), NFR-XXX (non-functional)
- name: Short, clear title (max 8 words) - NO prefixes!
- description: "The system shall..." + specific details
- requirementType: functional | security | performance | business | usability | non-functional
- priority: must | should | could | wont (MoSCoW method)
- category: NEVER empty! (e.g., "User Management", "Data Security", "Performance")
- tags: 2-4 lowercase keywords array
- rationale: WHY does this requirement exist? What problem does it solve?
- acceptanceCriteria: HOW to verify? (Given-When-Then format if possible)
- source: "document" (always this value)
- author: Name of the person who authored/documented this requirement (string) - extract from document if mentioned, otherwise leave empty
- involvedStakeholders: Array of stakeholder names mentioned in the document (e.g., ["Product Owner", "UX Team"]) - empty array if not mentioned
- risks: Array of risk objects with:
  * description: What could go wrong?
  * severity: low | medium | high | critical
  * probability: optional estimate (low/medium/high)
  * impact: optional description of consequences
  * mitigation: optional mitigation strategy
  Extract ONLY if risks are mentioned in the document - otherwise empty array []
- constraints: Array of constraint objects with:
  * description: The constraint description
  * type: technical | legal | budget | time | resource | business
  Extract ONLY if constraints are mentioned - otherwise empty array []
- assumptions: Array of assumption objects with:
  * description: The assumption made
  * validated: boolean (true if document confirms it's validated, false otherwise)
  Extract ONLY if assumptions are mentioned - otherwise empty array []
- relatedRequirements: Array of requirement identifiers that are thematically related (e.g., ["FR-002", "SEC-001"]) - empty array if none mentioned
- dependencies: Object with three arrays:
  * dependsOn: Requirements that this one depends on (must exist first)
  * conflicts: Requirements that contradict this one
  * extends: Requirements that this one enhances/extends
  Extract ONLY if dependencies are explicitly mentioned - otherwise use empty arrays

EXTRACTION INSTRUCTIONS:
1. Read the ENTIRE document carefully
2. Extract EVERY requirement mentioned (no limit, no truncation)
3. DO NOT skip requirements due to length - include ALL of them
4. Translate to English if needed
5. Fill ALL fields (especially category - never leave empty)
6. Use sequential identifiers (FR-001, FR-002, ... FR-N)
7. ⚠️ CRITICAL for risks/constraints/assumptions/stakeholders:
   - Extract ONLY if EXPLICITLY mentioned in the document
   - DO NOT invent risks/constraints/assumptions
   - Use empty arrays [] if not mentioned
   - For stakeholders: extract names only if mentioned (e.g., "Stakeholder: Product Team")
8. For relatedRequirements: Only link requirements if explicitly stated (e.g., "depends on FR-001")

Now extract ALL requirements from the document text.
PROMPT;
    }

    /**
     * Parse requirements from LLM response
     * 
     * @return SoftwareRequirements[]
     */
    private function parseRequirementsFromResponse(string $response): array
    {
        try {
            // Parse JSON response from LLM (wrapped in code blocks)
            if (preg_match('/```json\s*(.*?)```/s', $response, $matches)) {
                $jsonContent = trim($matches[1]);
                $data = json_decode($jsonContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($data['requirements'])) {
                    $rawRequirements = $data['requirements'];
                    
                    // Post-process and validate requirements
                    $requirements = $this->validateAndCleanRequirements($rawRequirements);
                    
                    $this->logger->info('Parsed and validated requirements from JSON response', [
                        'raw_count' => count($rawRequirements),
                        'final_count' => count($requirements),
                        'removed_duplicates' => count($rawRequirements) - count($requirements),
                    ]);
                    
                    // Warn if the response seems truncated (ends mid-JSON or with incomplete structure)
                    if (count($requirements) > 0 && (substr($jsonContent, -50) !== substr(trim($jsonContent), -50) || !str_ends_with(trim($jsonContent), '}'))) {
                        $this->logger->warning('LLM response may be truncated - not all requirements might have been extracted', [
                            'extracted_count' => count($requirements),
                            'response_length' => strlen($response),
                        ]);
                    }
                    
                    return array_map(
                        fn($req) => SoftwareRequirements::fromArray($req),
                        $requirements
                    );
                }
            }

            // Fallback: Try to parse raw JSON (without code blocks)
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['requirements'])) {
                $rawRequirements = $data['requirements'];
                $requirements = $this->validateAndCleanRequirements($rawRequirements);
                
                $this->logger->info('Parsed and validated requirements from raw JSON response', [
                    'raw_count' => count($rawRequirements),
                    'final_count' => count($requirements),
                ]);
                
                return array_map(
                    fn($req) => SoftwareRequirements::fromArray($req),
                    $requirements
                );
            }

            $this->logger->warning('No requirements found in LLM response', [
                'response_preview' => substr($response, 0, 500),
                'response_length' => strlen($response),
                'json_error' => json_last_error_msg(),
                'full_response' => $response, // DEBUG: full response for analysis
            ]);
            return [];

        } catch (\Throwable $e) {
            $this->logger->error('Failed to parse LLM response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response_preview' => substr($response, 0, 500),
            ]);

            return [];
        }
    }

    /**
     * Validate and clean requirements (remove duplicates, fill empty categories)
     * 
     * @param array $requirements Raw requirements from LLM
     * @return array Cleaned and validated requirements
     */
    private function validateAndCleanRequirements(array $requirements): array
    {
        $cleaned = [];
        $seenIdentifiers = []; // Only check identifier duplicates (unique IDs)

        foreach ($requirements as $req) {
            // Only skip if IDENTIFIER is truly duplicate (not name/description!)
            $identifier = trim($req['identifier'] ?? '');
            
            if (empty($identifier)) {
                $this->logger->debug('Skipping requirement without identifier', [
                    'name' => $req['name'] ?? 'unknown',
                ]);
                continue;
            }
            
            // Check for identifier duplicates only
            if (in_array($identifier, $seenIdentifiers, true)) {
                $this->logger->debug('Skipping duplicate requirement (by identifier)', [
                    'identifier' => $identifier,
                    'name' => $req['name'] ?? 'unknown',
                ]);
                continue;
            }
            
            // Fill empty category based on requirementType
            if (empty($req['category'])) {
                $req['category'] = $this->inferCategory($req);
                $this->logger->debug('Auto-filled empty category', [
                    'requirement' => $identifier,
                    'category' => $req['category'],
                ]);
            }
            
            // Validate identifier format (should match type prefix)
            if (isset($req['requirementType'])) {
                $req['identifier'] = $this->normalizeIdentifier($identifier, $req['requirementType']);
            }
            
            $seenIdentifiers[] = $identifier;
            $cleaned[] = $req;
        }

        return $cleaned;
    }

    /**
     * Infer category from requirement type and name
     */
    private function inferCategory(array $requirement): string
    {
        $type = $requirement['requirementType'] ?? '';
        $name = strtolower($requirement['name'] ?? '');

        // Category mapping based on type and name keywords
        $categoryMap = [
            'security' => 'Security & Compliance',
            'performance' => 'Performance & Scalability',
            'usability' => 'User Experience',
        ];

        if (isset($categoryMap[$type])) {
            return $categoryMap[$type];
        }

        // Infer from name keywords
        if (str_contains($name, 'user') || str_contains($name, 'profile') || str_contains($name, 'auth') || str_contains($name, 'login')) {
            return 'User Management';
        }
        if (str_contains($name, 'payment') || str_contains($name, 'order') || str_contains($name, 'cart')) {
            return 'E-Commerce';
        }
        if (str_contains($name, 'search') || str_contains($name, 'recommendation') || str_contains($name, 'personalization')) {
            return 'Search & Recommendations';
        }
        if (str_contains($name, 'data') || str_contains($name, 'backup') || str_contains($name, 'recovery')) {
            return 'Data Management';
        }
        if (str_contains($name, 'integration') || str_contains($name, 'api')) {
            return 'Integration';
        }

        return 'General';
    }

    /**
     * Normalize identifier to match requirement type
     */
    private function normalizeIdentifier(string $identifier, string $type): string
    {
        // Extract number from identifier
        if (preg_match('/(\d+)$/', $identifier, $matches)) {
            $number = $matches[1];
            
            // Map type to prefix
            $prefixMap = [
                'functional' => 'FR',
                'non-functional' => 'NFR',
                'security' => 'SEC',
                'performance' => 'PERF',
                'usability' => 'UX',
                'business' => 'BUS',
            ];
            
            $prefix = $prefixMap[$type] ?? 'REQ';
            return sprintf('%s-%03d', $prefix, (int)$number);
        }

        return $identifier;
    }
}

