<?php

namespace App\Service;

use App\Constants\SystemConstants;
use App\Dto\Requirements\RequirementsGraphDto;
use App\Service\Connector\TikaConnector;
use App\Service\Connector\LlmConnector;
use App\Service\Connector\Neo4JConnector;
use Psr\Log\LoggerInterface;

/**
 * Service für Requirements-Extraktion aus Dokumenten
 * 
 * Orchestriert den gesamten Prozess: Tika → Ollama → Neo4j
 * mit IRREB + schema.org Struktur + Token-Chunking
 */
class RequirementsExtractionService
{
    public function __construct(
        private readonly TikaConnector $tikaConnector,
        private readonly LlmConnector $llmConnector,
        private readonly Neo4JConnector $neo4jConnector,
        private readonly TokenChunker $tokenChunker,
        private readonly ToonFormatterService $toonFormatter,
        private readonly LoggerInterface $logger
    ) {}

    private array $tokenStats = [];

    /**
     * Extrahiert Requirements aus einem oder mehreren Dokumenten
     * 
     * @param array $filePaths Array von Dateipfaden zu verarbeitenden Dokumenten
     * @param string $model LLM-Modell (default: llama3.2)
     * @param bool $importToNeo4j Direkt in Neo4j importieren
     * @return RequirementsGraphDto
     */
    public function extractFromDocuments(
        array $filePaths,
        string $model = 'llama3.2',
        bool $importToNeo4j = true
    ): RequirementsGraphDto {
        // Reset Token-Stats für neuen Request
        $this->tokenStats = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'chunks_processed' => 0,
            'format' => 'TOON',
            'model' => $model
        ];
        $startTime = microtime(true);

        $this->logger->info('Starting requirements extraction', [
            'file_count' => count($filePaths),
            'model' => $model,
            'import_to_neo4j' => $importToNeo4j
        ]);

        // Schritt 1: Text aus allen Dokumenten extrahieren
        $extractedTexts = $this->extractTextsFromDocuments($filePaths);

        // Schritt 2: Texte kombinieren für LLM-Verarbeitung
        $combinedText = $this->combineExtractedTexts($extractedTexts);

        // Schritt 3: LLM-Prompt generieren und Requirements extrahieren
        $requirementsGraph = $this->extractRequirementsWithLlm($combinedText, $model);

        // Schritt 4: Optional in Neo4j importieren
        if ($importToNeo4j) {
            $this->importToNeo4j($requirementsGraph);
        }

        $executionTime = round(microtime(true) - $startTime, 3);

        $this->logger->info('Requirements extraction completed', [
            'execution_time' => $executionTime . 's',
            'requirements_count' => count($requirementsGraph->requirements),
            'roles_count' => count($requirementsGraph->roles),
            'relationships_count' => count($requirementsGraph->relationships)
        ]);

        return $requirementsGraph;
    }

    /**
     * Extrahiert Text aus allen Dokumenten via Tika
     */
    private function extractTextsFromDocuments(array $filePaths): array
    {
        $extractedTexts = [];

        foreach ($filePaths as $filePath) {
            try {
                $this->logger->debug('Extracting text from document', ['file' => basename($filePath)]);

                $response = $this->tikaConnector->analyzeDocument($filePath);
                $rawContent = $response->getContent();
                $optimizedText = $this->tikaConnector->parseOptimization($rawContent);

                if ($optimizedText) {
                    $extractedTexts[] = [
                        'file' => basename($filePath),
                        'path' => $filePath,
                        'content' => $optimizedText,
                        'length' => strlen($optimizedText)
                    ];

                    $this->logger->debug('Text extracted successfully', [
                        'file' => basename($filePath),
                        'content_length' => strlen($optimizedText)
                    ]);
                }

            } catch (\Exception $e) {
                $this->logger->error('Failed to extract text from document', [
                    'file' => basename($filePath),
                    'error' => $e->getMessage()
                ]);
                // Weiter mit nächster Datei
            }
        }

        if (empty($extractedTexts)) {
            throw new \RuntimeException('Keine Texte konnten aus den Dokumenten extrahiert werden');
        }

        return $extractedTexts;
    }

    /**
     * Kombiniert extrahierte Texte zu einem Gesamt-Kontext
     */
    private function combineExtractedTexts(array $extractedTexts): string
    {
        $combined = "# Extrahierte Dokumente\n\n";

        foreach ($extractedTexts as $index => $extracted) {
            $combined .= "## Dokument " . ($index + 1) . ": {$extracted['file']}\n\n";
            $combined .= $extracted['content'] . "\n\n";
            $combined .= "---\n\n";
        }

        return $combined;
    }

    /**
     * Extrahiert Requirements via LLM mit strukturiertem Prompt
     * Verwendet Token-Chunking für große Dokumente
     */
    private function extractRequirementsWithLlm(string $text, string $model): RequirementsGraphDto
    {
        $prompt = $this->buildRequirementsExtractionPrompt($text);

        // 🔥 WICHTIG: Token-Counting VOR dem LLM-Request
        $tokenCount = $this->tokenChunker->countTokens($prompt, $model);
        
        // Speichere Prompt-Tokens
        $this->tokenStats['prompt_tokens'] = $tokenCount;

        $this->logger->debug('Sending requirements extraction prompt to LLM', [
            'prompt_length' => strlen($prompt),
            'token_count' => $tokenCount,
            'model' => $model,
            'needs_chunking' => $tokenCount > SystemConstants::TOKEN_SYNC_LIMIT
        ]);

        try {
            // Entscheide ob Chunking nötig ist
            if ($tokenCount > SystemConstants::TOKEN_SYNC_LIMIT) {
                $this->logger->warning('Large prompt detected, using chunking strategy', [
                    'token_count' => $tokenCount,
                    'limit' => SystemConstants::TOKEN_SYNC_LIMIT
                ]);
                
                return $this->extractWithChunking($text, $model);
            }

            // Normaler Request für kleinere Prompts
            return $this->extractWithoutChunking($prompt, $model);

        } catch (\Exception $e) {
            $this->logger->error('LLM requirements extraction failed', [
                'error' => $e->getMessage(),
                'token_count' => $tokenCount
            ]);
            throw new \RuntimeException('Requirements-Extraktion fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extrahiert Requirements ohne Chunking (für kleinere Dokumente)
     */
    private function extractWithoutChunking(string $prompt, string $model): RequirementsGraphDto
    {
        $response = $this->llmConnector->chatCompletion([
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
            ['role' => 'user', 'content' => $prompt]
        ], $model, [
            'temperature' => 0.3,
            'num_predict' => 8192
        ]);

        $content = $response->getContent();
        $llmData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('LLM gab ungültiges JSON zurück: ' . json_last_error_msg());
        }

        $responseText = $llmData['message']['content'] ?? $llmData['response'] ?? '';
        
        // Schätze Completion-Tokens
        $completionTokens = $this->tokenChunker->countTokens($responseText, $model);
        $this->tokenStats['completion_tokens'] += $completionTokens;
        $this->tokenStats['total_tokens'] = $this->tokenStats['prompt_tokens'] + $this->tokenStats['completion_tokens'];
        
        $requirementsData = $this->extractJsonFromResponse($responseText);

        return RequirementsGraphDto::fromArray($requirementsData);
    }

    /**
     * Extrahiert Requirements mit Chunking für große Dokumente
     */
    private function extractWithChunking(string $text, string $model): RequirementsGraphDto
    {
        // Chunke nur den extrahierten Text, nicht den Prompt-Template
        $chunks = $this->tokenChunker->chunk($text, $model);
        $chunkCount = count($chunks);

        $this->logger->info('Processing large document with chunking', [
            'total_chunks' => $chunkCount,
            'chunk_size' => SystemConstants::TOKEN_CHUNK_SIZE
        ]);

        $allRequirements = [];
        $allRoles = [];
        $allEnvironments = [];
        $allBusinesses = [];
        $allInfrastructures = [];
        $allSoftwareApplications = [];
        $allRelationships = [];

        // Verarbeite jeden Chunk einzeln
        foreach ($chunks as $index => $chunk) {
            $this->logger->debug('Processing chunk', [
                'chunk' => $index + 1,
                'total' => $chunkCount
            ]);

            try {
                $chunkPrompt = $this->buildChunkPrompt($chunk, $index + 1, $chunkCount);
                
                // Count Tokens für diesen Chunk
                $chunkTokens = $this->tokenChunker->countTokens($chunkPrompt, $model);
                $this->tokenStats['prompt_tokens'] += $chunkTokens;
                
                $chunkGraph = $this->extractWithoutChunking($chunkPrompt, $model);

                // Sammle alle Entitäten aus dem Chunk
                $allRequirements = array_merge($allRequirements, $chunkGraph->requirements);
                $allRoles = array_merge($allRoles, $chunkGraph->roles);
                $allEnvironments = array_merge($allEnvironments, $chunkGraph->environments);
                $allBusinesses = array_merge($allBusinesses, $chunkGraph->businesses);
                $allInfrastructures = array_merge($allInfrastructures, $chunkGraph->infrastructures);
                $allSoftwareApplications = array_merge($allSoftwareApplications, $chunkGraph->softwareApplications);
                $allRelationships = array_merge($allRelationships, $chunkGraph->relationships);

            } catch (\Exception $e) {
                $this->logger->error('Failed to process chunk', [
                    'chunk' => $index + 1,
                    'error' => $e->getMessage()
                ]);
                // Weiter mit nächstem Chunk
            }
        }

        // Dedupliziere Entitäten basierend auf IDs
        $allRequirements = $this->deduplicateById($allRequirements);
        $allRoles = $this->deduplicateById($allRoles);
        $allEnvironments = $this->deduplicateById($allEnvironments);
        $allBusinesses = $this->deduplicateById($allBusinesses);
        $allInfrastructures = $this->deduplicateById($allInfrastructures);
        $allSoftwareApplications = $this->deduplicateById($allSoftwareApplications);
        $allRelationships = $this->deduplicateRelationships($allRelationships);

        // Update Token-Stats für Chunking
        $this->tokenStats['chunks_processed'] = $chunkCount;
        $this->tokenStats['total_tokens'] = $this->tokenStats['prompt_tokens'] + $this->tokenStats['completion_tokens'];

        $this->logger->info('Chunking completed, merged results', [
            'total_requirements' => count($allRequirements),
            'total_roles' => count($allRoles),
            'total_relationships' => count($allRelationships),
            'chunks_processed' => $chunkCount
        ]);

        return new RequirementsGraphDto(
            requirements: $allRequirements,
            roles: $allRoles,
            environments: $allEnvironments,
            businesses: $allBusinesses,
            infrastructures: $allInfrastructures,
            softwareApplications: $allSoftwareApplications,
            relationships: $allRelationships
        );
    }

    /**
     * Baut Prompt für einen Chunk mit Kontext-Information
     */
    private function buildChunkPrompt(string $chunk, int $chunkIndex, int $totalChunks): string
    {
        $contextNote = "";
        if ($totalChunks > 1) {
            $contextNote = "\n\n⚠️ HINWEIS: Dies ist Teil {$chunkIndex} von {$totalChunks} eines größeren Dokuments.\n";
            $contextNote .= "Extrahiere NUR die Requirements aus diesem Textabschnitt.\n";
            $contextNote .= "Verwende eindeutige IDs im Format: REQ-{$chunkIndex}XX (z.B. REQ-101, REQ-102 für Chunk 1)\n\n";
        }

        return $this->buildRequirementsExtractionPrompt($chunk) . $contextNote;
    }

    /**
     * Dedupliziert Entitäten basierend auf ID
     */
    private function deduplicateById(array $entities): array
    {
        $seen = [];
        $unique = [];

        foreach ($entities as $entity) {
            $id = is_array($entity) ? ($entity['id'] ?? null) : $entity->id;
            
            if ($id && !isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $entity;
            }
        }

        return $unique;
    }

    /**
     * Dedupliziert Relationships
     */
    private function deduplicateRelationships(array $relationships): array
    {
        $seen = [];
        $unique = [];

        foreach ($relationships as $rel) {
            $key = ($rel['type'] ?? '') . '_' . ($rel['source'] ?? '') . '_' . ($rel['target'] ?? '');
            
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $rel;
            }
        }

        return $unique;
    }

    /**
     * Extrahiert TOON oder JSON aus LLM-Antwort
     */
    private function extractJsonFromResponse(string $response): array
    {
        // Versuche zuerst TOON-Format (bevorzugt)
        if (preg_match('/```toon\s*(.*?)\s*```/s', $response, $matches)) {
            $toonStr = $matches[1];
            
            try {
                $data = $this->toonFormatter->decode($toonStr);
                $this->logger->debug('Successfully parsed TOON response');
                return $data;
            } catch (\Exception $e) {
                $this->logger->warning('TOON parsing failed, falling back to JSON', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fallback zu JSON
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $jsonStr = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonStr = $matches[0];
        } else {
            $jsonStr = $response;
        }

        $data = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Konnte weder TOON noch JSON aus LLM-Antwort extrahieren: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * System-Prompt für LLM mit TOON-Format
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
Du bist ein Experte für Requirements-Engineering nach IREB/IRREB-Standard.

Deine Aufgabe ist es, aus Dokumenten strukturierte Requirements zu extrahieren und im **TOON-Format** zu formatieren.

TOON (Token-Oriented Object Notation) ist kompakt und strukturiert:
- Format: name[N]{field1,field2}: gefolgt von Daten-Rows
- 2-space indent für Daten-Rows
- Comma-separated values
- Quotes nur wenn nötig (bei Kommas, Newlines)

WICHTIG:
1. Verwende IRREB-konforme Kategorien für Requirements
2. Identifiziere alle relevanten Entitäten: Requirements, Rollen, Umgebungen, Business-Kontext, Infrastruktur, Software
3. Erstelle Beziehungen zwischen Entitäten nach IRREB-Standard:
   - OWNED_BY: Requirement → Role
   - APPLIES_TO: Requirement → Environment
   - SUPPORTS: Requirement → Business
   - DEPENDS_ON: Requirement → Infrastructure
   - USES_SOFTWARE: Requirement → SoftwareApplication

4. Antworte AUSSCHLIESSLICH im TOON-Format in einem ```toon Code-Block
5. Verwende eindeutige IDs für alle Entitäten
6. [N] muss exakt der Anzahl der Rows entsprechen

PROMPT;
    }

    /**
     * Baut den Requirements-Extraction-Prompt mit TOON-Format
     */
    private function buildRequirementsExtractionPrompt(string $text): string
    {
        $example = $this->toonFormatter->generateExampleForPrompt();
        
        return <<<PROMPT
Analysiere den folgenden Text und extrahiere alle Requirements nach IRREB-Standard.

Erstelle die Ausgabe im **TOON-Format** (Token-optimiert, kompakt):

**TOON-Beispiel:**
```toon
{$example}
```

**Erwartete TOON-Struktur:**

```toon
requirements[N]{id,name,description,type,priority,status,source}:
  REQ-001,User Authentication,System must authenticate users securely,functional,critical,approved,Security Doc v2.1
  REQ-002,Data Encryption,All data must be encrypted at rest,non-functional,high,draft,Compliance Requirements

roles[N]{id,name,description,level,department}:
  ROLE-001,Security Officer,Responsible for security requirements,manager,IT Security
  ROLE-002,Product Owner,Prioritizes features and accepts requirements,executive,Product

environments[N]{id,name,type,description,location}:
  ENV-001,Production,production,Main production environment,AWS us-east-1

businesses[N]{id,name,goal,objective}:
  BIZ-001,User Security,Protect user data and system access,Ensure secure authentication and authorization

infrastructures[N]{id,name,type,description,provider}:
  INFRA-001,Auth Server,server,Authentication service cluster,AWS EC2

softwareApplications[N]{id,name,version,operatingSystem,category}:
  SW-001,OAuth2 Provider,2.1.0,Linux,SecurityApplication

relationships[N]{type,source,target}:
  OWNED_BY,REQ-001,ROLE-001
  APPLIES_TO,REQ-001,ENV-001
  SUPPORTS,REQ-001,BIZ-001
  DEPENDS_ON,REQ-001,INFRA-001
  USES_SOFTWARE,REQ-001,SW-001
```

**ZU ANALYSIERENDER TEXT:**

{$text}

**WICHTIG:**
- Antworte NUR mit TOON-Format in ```toon Code-Block
- [N] muss exakt der Anzahl der Rows entsprechen
- Verwende 2-space indent für Daten-Rows
- Quotes nur bei Werten mit Kommas/Newlines
- Keine zusätzlichen Erklärungen außerhalb des Code-Blocks
PROMPT;
    }

    /**
     * Importiert Requirements-Graph nach Neo4j
     */
    private function importToNeo4j(RequirementsGraphDto $graph): void
    {
        $startTime = microtime(true);

        $this->logger->info('Importing requirements graph to Neo4j', [
            'requirements' => count($graph->requirements),
            'roles' => count($graph->roles),
            'environments' => count($graph->environments),
            'relationships' => count($graph->relationships)
        ]);

        try {
            // Importiere alle Entitäten
            $this->importRequirements($graph->requirements);
            $this->importRoles($graph->roles);
            $this->importEnvironments($graph->environments);
            $this->importBusinesses($graph->businesses);
            $this->importInfrastructures($graph->infrastructures);
            $this->importSoftwareApplications($graph->softwareApplications);

            // Erstelle Beziehungen
            $this->createRelationships($graph->relationships);

            $executionTime = round(microtime(true) - $startTime, 3);

            $this->logger->info('Neo4j import completed', [
                'execution_time' => $executionTime . 's'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Neo4j import failed', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Neo4j-Import fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Importiert Requirements nach Neo4j
     */
    private function importRequirements(array $requirements): void
    {
        foreach ($requirements as $requirement) {
            $data = is_array($requirement) ? $requirement : $requirement->toArray();

            $cypher = "MERGE (r:Requirement {id: \$id}) 
                       SET r.name = \$name,
                           r.description = \$description,
                           r.type = \$type,
                           r.priority = \$priority,
                           r.status = \$status,
                           r.source = \$source,
                           r.rationale = \$rationale,
                           r.acceptanceCriteria = \$acceptanceCriteria,
                           r.updatedAt = datetime()
                       RETURN r";

            $this->neo4jConnector->executeCypherQuery($cypher, $data);
        }

        $this->logger->debug('Requirements imported to Neo4j', ['count' => count($requirements)]);
    }

    /**
     * Importiert Roles nach Neo4j
     */
    private function importRoles(array $roles): void
    {
        foreach ($roles as $role) {
            $data = is_array($role) ? $role : $role->toArray();

            $cypher = "MERGE (r:Role {id: \$id})
                       SET r.name = \$name,
                           r.description = \$description,
                           r.department = \$department,
                           r.level = \$level
                       RETURN r";

            $this->neo4jConnector->executeCypherQuery($cypher, $data);
        }

        $this->logger->debug('Roles imported to Neo4j', ['count' => count($roles)]);
    }

    /**
     * Importiert Environments nach Neo4j
     */
    private function importEnvironments(array $environments): void
    {
        foreach ($environments as $environment) {
            $data = is_array($environment) ? $environment : $environment->toArray();

            $cypher = "MERGE (e:Environment {id: \$id})
                       SET e.name = \$name,
                           e.type = \$type,
                           e.description = \$description,
                           e.location = \$location
                       RETURN e";

            $this->neo4jConnector->executeCypherQuery($cypher, $data);
        }

        $this->logger->debug('Environments imported to Neo4j', ['count' => count($environments)]);
    }

    /**
     * Importiert Businesses nach Neo4j
     */
    private function importBusinesses(array $businesses): void
    {
        foreach ($businesses as $business) {
            $data = is_array($business) ? $business : $business->toArray();

            $cypher = "MERGE (b:Business {id: \$id})
                       SET b.name = \$name,
                           b.goal = \$goal,
                           b.objective = \$objective,
                           b.roi = \$roi,
                           b.timeframe = \$timeframe
                       RETURN b";

            $this->neo4jConnector->executeCypherQuery($cypher, $data);
        }

        $this->logger->debug('Businesses imported to Neo4j', ['count' => count($businesses)]);
    }

    /**
     * Importiert Infrastructures nach Neo4j
     */
    private function importInfrastructures(array $infrastructures): void
    {
        foreach ($infrastructures as $infrastructure) {
            $data = is_array($infrastructure) ? $infrastructure : $infrastructure->toArray();

            $cypher = "MERGE (i:Infrastructure {id: \$id})
                       SET i.name = \$name,
                           i.type = \$type,
                           i.description = \$description,
                           i.provider = \$provider,
                           i.location = \$location
                       RETURN i";

            $this->neo4jConnector->executeCypherQuery($cypher, $data);
        }

        $this->logger->debug('Infrastructures imported to Neo4j', ['count' => count($infrastructures)]);
    }

    /**
     * Importiert SoftwareApplications nach Neo4j
     */
    private function importSoftwareApplications(array $softwareApplications): void
    {
        foreach ($softwareApplications as $software) {
            $data = is_array($software) ? $software : $software->toArray();

            $cypher = "MERGE (s:SoftwareApplication {id: \$id})
                       SET s.name = \$name,
                           s.version = \$version,
                           s.description = \$description,
                           s.operatingSystem = \$operatingSystem,
                           s.category = \$category,
                           s.downloadUrl = \$downloadUrl,
                           s.license = \$license
                       RETURN s";

            $this->neo4jConnector->executeCypherQuery($cypher, $data);
        }

        $this->logger->debug('SoftwareApplications imported to Neo4j', ['count' => count($softwareApplications)]);
    }

    /**
     * Erstellt Beziehungen in Neo4j
     */
    private function createRelationships(array $relationships): void
    {
        foreach ($relationships as $relationship) {
            try {
                $type = $relationship['type'];
                $source = $relationship['source'];
                $target = $relationship['target'];

                // Bestimme Source- und Target-Labels basierend auf ID-Präfixen
                $sourceLabel = $this->determineLabelFromId($source);
                $targetLabel = $this->determineLabelFromId($target);

                $cypher = "MATCH (source:{$sourceLabel} {id: \$source_id})
                           MATCH (target:{$targetLabel} {id: \$target_id})
                           MERGE (source)-[r:{$type}]->(target)
                           RETURN r";

                $this->neo4jConnector->executeCypherQuery($cypher, [
                    'source_id' => $source,
                    'target_id' => $target
                ]);

            } catch (\Exception $e) {
                $this->logger->warning('Failed to create relationship', [
                    'relationship' => $relationship,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->debug('Relationships created in Neo4j', ['count' => count($relationships)]);
    }

    /**
     * Bestimmt Neo4j-Label anhand der ID
     */
    private function determineLabelFromId(string $id): string
    {
        return match (true) {
            str_starts_with($id, 'REQ-') => 'Requirement',
            str_starts_with($id, 'ROLE-') => 'Role',
            str_starts_with($id, 'ENV-') => 'Environment',
            str_starts_with($id, 'BIZ-') => 'Business',
            str_starts_with($id, 'INFRA-') => 'Infrastructure',
            str_starts_with($id, 'SW-') => 'SoftwareApplication',
            default => 'Node'
        };
    }

    /**
     * Gibt Token-Statistiken vom letzten Request zurück
     */
    public function getTokenStats(): array
    {
        return $this->tokenStats;
    }
}

