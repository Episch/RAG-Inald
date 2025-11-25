<?php

declare(strict_types=1);

namespace App\Service\Neo4j;

use App\DTO\Schema\SoftwareApplication;
use App\DTO\Schema\SoftwareRequirements;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Neo4j Connector Service for Requirements Storage
 * 
 * Stores software requirements as nodes with embeddings for semantic search
 */
class Neo4jConnectorService
{
    private ClientInterface $client;

    public function __construct(
        private readonly string $neo4jUrl,
        private readonly string $neo4jUser,
        private readonly string $neo4jPassword,
        private readonly LoggerInterface $logger
    ) {
        $this->client = ClientBuilder::create()
            ->withDriver(
                'default',
                $this->neo4jUrl,
                Authenticate::basic($this->neo4jUser, $this->neo4jPassword)
            )
            ->build();
    }

    /**
     * Get Neo4j client for direct queries
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * Store Software Application with Requirements in Neo4j (UPSERT)
     * 
     * Uses MERGE to prevent duplicate applications based on name.
     * If an application with the same name exists, it will be reused.
     * 
     * @return string Node ID
     */
    public function storeSoftwareApplication(
        SoftwareApplication $application,
        array $embeddings = []
    ): string {
        $startTime = microtime(true);

        try {
            // Normalize application name for MERGE (trim, lowercase for matching)
            $normalizedName = trim($application->name);
            $matchKey = mb_strtolower($normalizedName); // Case-insensitive matching
            
            // MERGE Application Node (prevent duplicates by normalized name)
            $appResult = $this->client->run(
                'MERGE (app:SoftwareApplication {nameKey: $nameKey})
                ON CREATE SET
                    app.name = $name,
                    app.description = $description,
                    app.version = $version,
                    app.applicationCategory = $applicationCategory,
                    app.operatingSystem = $operatingSystem,
                    app.softwareVersion = $softwareVersion,
                    app.license = $license,
                    app.provider = $provider,
                    app.keywords = $keywords,
                    app.createdAt = datetime(),
                    app.updatedAt = datetime()
                ON MATCH SET
                    app.name = $name,
                    app.description = $description,
                    app.version = $version,
                    app.applicationCategory = $applicationCategory,
                    app.operatingSystem = $operatingSystem,
                    app.softwareVersion = $softwareVersion,
                    app.license = $license,
                    app.provider = $provider,
                    app.keywords = $keywords,
                    app.updatedAt = datetime()
                RETURN elementId(app) as id',
                [
                    'nameKey' => $matchKey,
                    'name' => $normalizedName,
                    'description' => $application->description,
                    'version' => $application->version,
                    'applicationCategory' => $application->applicationCategory,
                    'operatingSystem' => $application->operatingSystem,
                    'softwareVersion' => $application->softwareVersion,
                    'license' => $application->license,
                    'provider' => $application->provider,
                    'keywords' => $application->keywords,
                ]
            );

            $appNodeId = $appResult->first()->get('id');

            // Store Requirements
            foreach ($application->requirements as $index => $requirement) {
                $reqEmbedding = $embeddings[$index] ?? [];
                $this->storeRequirement($appNodeId, $requirement, $reqEmbedding);
            }

            // Create Relations between Requirements (IREB Graph)
            $this->createRequirementRelations($application->requirements);

            $duration = microtime(true) - $startTime;

            $this->logger->info('Stored software application in Neo4j', [
                'app_name' => $application->name,
                'requirements_count' => count($application->requirements),
                'node_id' => $appNodeId,
                'duration_seconds' => round($duration, 3),
            ]);

            return $appNodeId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to store application in Neo4j', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to store in Neo4j: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Store individual requirement with embedding
     */
    public function storeRequirement(
        string $appNodeId,
        SoftwareRequirements $requirement,
        array $embedding = []
    ): string {
        try {
            $result = $this->client->run(
                'MATCH (app) WHERE elementId(app) = $appId
                MERGE (req:SoftwareRequirement {identifier: $identifier})
                ON CREATE SET
                    req.name = $name,
                    req.description = $description,
                    req.requirementType = $requirementType,
                    req.priority = $priority,
                    req.category = $category,
                    req.tags = $tags,
                    req.source = $source,
                    req.rationale = $rationale,
                    req.acceptanceCriteria = $acceptanceCriteria,
                    req.verificationMethod = $verificationMethod,
                    req.validationCriteria = $validationCriteria,
                    req.status = $status,
                    req.version = $version,
                    req.stakeholder = $stakeholder,
                    req.author = $author,
                    req.involvedStakeholders = $involvedStakeholders,
                    req.constraints = $constraints,
                    req.assumptions = $assumptions,
                    req.relatedRequirements = $relatedRequirements,
                    req.dependencies = $dependencies,
                    req.risks = $risks,
                    req.riskLevel = $riskLevel,
                    req.estimatedEffort = $estimatedEffort,
                    req.actualEffort = $actualEffort,
                    req.traceabilityTo = $traceabilityTo,
                    req.traceabilityFrom = $traceabilityFrom,
                    req.embedding = $embedding,
                    req.createdAt = datetime(),
                    req.updatedAt = datetime()
                ON MATCH SET
                    req.name = $name,
                    req.description = $description,
                    req.requirementType = $requirementType,
                    req.priority = $priority,
                    req.category = $category,
                    req.tags = $tags,
                    req.rationale = $rationale,
                    req.acceptanceCriteria = $acceptanceCriteria,
                    req.verificationMethod = $verificationMethod,
                    req.validationCriteria = $validationCriteria,
                    req.status = $status,
                    req.version = $version,
                    req.stakeholder = $stakeholder,
                    req.author = $author,
                    req.involvedStakeholders = $involvedStakeholders,
                    req.constraints = $constraints,
                    req.assumptions = $assumptions,
                    req.relatedRequirements = $relatedRequirements,
                    req.dependencies = $dependencies,
                    req.risks = $risks,
                    req.riskLevel = $riskLevel,
                    req.estimatedEffort = $estimatedEffort,
                    req.actualEffort = $actualEffort,
                    req.traceabilityTo = $traceabilityTo,
                    req.traceabilityFrom = $traceabilityFrom,
                    req.embedding = $embedding,
                    req.updatedAt = datetime()
                MERGE (app)-[:HAS_REQUIREMENT]->(req)
                RETURN elementId(req) as id',
                [
                    'appId' => $appNodeId,
                    'identifier' => $requirement->identifier,
                    'name' => $requirement->name,
                    'description' => $requirement->description,
                    'requirementType' => $requirement->requirementType,
                    'priority' => $requirement->priority,
                    'category' => $requirement->category ?? '',
                    'tags' => $requirement->tags,
                    'source' => $requirement->source ?? 'document',
                    'rationale' => $requirement->rationale,
                    'acceptanceCriteria' => $requirement->acceptanceCriteria,
                    'verificationMethod' => $requirement->verificationMethod,
                    'validationCriteria' => $requirement->validationCriteria,
                    'status' => $requirement->status,
                    'version' => $requirement->version ?? '1.0',
                    'stakeholder' => $requirement->stakeholder,
                    'author' => $requirement->author,
                    'involvedStakeholders' => $requirement->involvedStakeholders,
                    'constraints' => $requirement->constraints,
                    'assumptions' => $requirement->assumptions,
                    'relatedRequirements' => $requirement->relatedRequirements,
                    'dependencies' => $requirement->dependencies,
                    'risks' => $requirement->risks,
                    'riskLevel' => $requirement->riskLevel,
                    'estimatedEffort' => $requirement->estimatedEffort,
                    'actualEffort' => $requirement->actualEffort,
                    'traceabilityTo' => $requirement->traceabilityTo,
                    'traceabilityFrom' => $requirement->traceabilityFrom,
                    'embedding' => $embedding,
                ]
            );

            $nodeId = $result->first()->get('id');
            
            $this->logger->debug('Requirement stored/updated', [
                'identifier' => $requirement->identifier,
                'node_id' => $nodeId,
            ]);
            
            return $nodeId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to store requirement in Neo4j', [
                'requirement' => $requirement->identifier,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to store requirement: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Find similar existing requirement
     * 
     * Checks for duplicates based on:
     * 1. Exact identifier match
     * 2. Semantic similarity (> 0.95 = very similar)
     * 3. Name similarity
     */
    private function findSimilarRequirement(
        SoftwareRequirements $requirement,
        array $embedding
    ): ?array {
        try {
            // Check 1: Exact identifier match
            $identifierResult = $this->client->run(
                'MATCH (req:SoftwareRequirement {identifier: $identifier})
                RETURN elementId(req) as id, req.name as name, req.description as description
                LIMIT 1',
                ['identifier' => $requirement->identifier]
            );
            
            if ($identifierResult->count() > 0) {
                $this->logger->info('Found existing requirement by identifier', [
                    'identifier' => $requirement->identifier,
                ]);
                return [
                    'id' => $identifierResult->first()->get('id'),
                    'match_type' => 'identifier',
                ];
            }
            
            // Check 2: Semantic similarity (only if embedding is provided)
            if (!empty($embedding)) {
                $similarityResult = $this->client->run(
                    'MATCH (req:SoftwareRequirement)
                    WHERE req.embedding IS NOT NULL
                    WITH req, gds.similarity.cosine(req.embedding, $embedding) AS similarity
                    WHERE similarity > 0.95
                    RETURN elementId(req) as id, req.identifier as identifier, similarity
                    ORDER BY similarity DESC
                    LIMIT 1',
                    ['embedding' => $embedding]
                );
                
                if ($similarityResult->count() > 0) {
                    $record = $similarityResult->first();
                    $this->logger->info('Found similar requirement by embedding', [
                        'identifier' => $requirement->identifier,
                        'similar_to' => $record->get('identifier'),
                        'similarity' => $record->get('similarity'),
                    ]);
                    return [
                        'id' => $record->get('id'),
                        'match_type' => 'semantic',
                        'similarity' => $record->get('similarity'),
                    ];
                }
            }
            
            // Check 3: Name similarity (fuzzy match)
            $nameResult = $this->client->run(
                'MATCH (req:SoftwareRequirement)
                WHERE toLower(req.name) = toLower($name)
                RETURN elementId(req) as id, req.identifier as identifier
                LIMIT 1',
                ['name' => $requirement->name]
            );
            
            if ($nameResult->count() > 0) {
                $this->logger->info('Found similar requirement by name', [
                    'identifier' => $requirement->identifier,
                    'name' => $requirement->name,
                ]);
                return [
                    'id' => $nameResult->first()->get('id'),
                    'match_type' => 'name',
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->warning('Error checking for similar requirements', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update existing requirement instead of creating duplicate
     */
    private function updateRequirement(
        string $appNodeId,
        array $existingReq,
        SoftwareRequirements $requirement,
        array $embedding
    ): string {
        $existingNodeId = $existingReq['id'];
        $matchType = $existingReq['match_type'];
        
        $this->logger->info('Updating existing requirement', [
            'identifier' => $requirement->identifier,
            'match_type' => $matchType,
            'node_id' => $existingNodeId,
        ]);
        
        // Update the existing node
        $this->client->run(
            'MATCH (req) WHERE elementId(req) = $reqId
            SET 
                req.name = $name,
                req.description = $description,
                req.requirementType = $requirementType,
                req.priority = $priority,
                req.category = $category,
                req.tags = $tags,
                req.source = $source,
                req.acceptanceCriteria = $acceptanceCriteria,
                req.embedding = $embedding,
                req.updatedAt = datetime(),
                req.version = coalesce(req.version, 0) + 1',
            [
                'reqId' => $existingNodeId,
                'name' => $requirement->name,
                'description' => $requirement->description,
                'requirementType' => $requirement->requirementType,
                'priority' => $requirement->priority,
                'category' => $requirement->category,
                'tags' => $requirement->tags,
                'source' => $requirement->source,
                'acceptanceCriteria' => $requirement->acceptanceCriteria,
                'embedding' => $embedding,
            ]
        );
        
        // Ensure relationship exists
        $this->client->run(
            'MATCH (app) WHERE elementId(app) = $appId
            MATCH (req) WHERE elementId(req) = $reqId
            MERGE (app)-[:HAS_REQUIREMENT]->(req)',
            [
                'appId' => $appNodeId,
                'reqId' => $existingNodeId,
            ]
        );
        
        return $existingNodeId;
    }

    /**
     * Semantic search for similar requirements
     * 
     * @param float[] $queryEmbedding
     * @return array Similar requirements
     */
    /**
     * Search for similar requirements using vector similarity
     * 
     * @param array $queryEmbedding The embedding vector to search with
     * @param int $limit Maximum number of results
     * @param float $minSimilarity Minimum similarity threshold (0.0-1.0)
     * @param string|null $requirementType Filter by requirement type
     * @param string|null $priority Filter by priority
     * @param string|null $status Filter by status
     * @return array List of similar requirements with similarity scores
     */
    public function searchSimilarRequirements(
        array $queryEmbedding,
        string $queryText = '',
        int $limit = 10,
        float $minSimilarity = 0.0,
        ?string $requirementType = null,
        ?string $priority = null,
        ?string $status = null
    ): array {
        try {
            // Build WHERE clause with filters
            $whereConditions = ['req.embedding IS NOT NULL'];
            $params = [
                'queryEmbedding' => $queryEmbedding,
                'queryText' => strtolower($queryText),
                'limit' => $limit,
            ];

            if ($minSimilarity > 0.0) {
                $params['minSimilarity'] = $minSimilarity;
            }

            if ($requirementType !== null) {
                $whereConditions[] = 'req.requirementType = $requirementType';
                $params['requirementType'] = $requirementType;
            }

            if ($priority !== null) {
                $whereConditions[] = 'req.priority = $priority';
                $params['priority'] = $priority;
            }

            if ($status !== null) {
                $whereConditions[] = 'req.status = $status';
                $params['status'] = $status;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // HYBRID SEARCH: Combine semantic + keyword matching
            $query = "MATCH (req:SoftwareRequirement)
                WHERE {$whereClause}
                WITH req,
                     gds.similarity.cosine(req.embedding, \$queryEmbedding) AS semantic_sim,
                     CASE
                         WHEN toLower(req.category) CONTAINS \$queryText THEN 0.3
                         WHEN toLower(req.name) CONTAINS \$queryText THEN 0.2
                         WHEN any(tag IN req.tags WHERE toLower(tag) CONTAINS \$queryText) THEN 0.15
                         WHEN toLower(req.description) CONTAINS \$queryText THEN 0.1
                         ELSE 0.0
                     END AS keyword_boost
                WITH req,
                     semantic_sim,
                     keyword_boost,
                     (semantic_sim * 0.7 + keyword_boost * 1.5) AS combined_score";
            
            if ($minSimilarity > 0.0) {
                $query .= "\nWHERE combined_score >= \$minSimilarity OR keyword_boost > 0.0";
            }
            
            $query .= "\nORDER BY combined_score DESC
                LIMIT \$limit
                RETURN req, combined_score AS similarity, semantic_sim, keyword_boost";

            $result = $this->client->run($query, $params);

            $requirements = [];
            foreach ($result as $record) {
                $req = $record->get('req');
                $requirements[] = [
                    'requirement' => $req->getProperties(),
                    'similarity' => $record->get('similarity'),
                ];
            }

            $this->logger->info('Semantic search completed', [
                'results_count' => count($requirements),
                'filters' => [
                    'minSimilarity' => $minSimilarity,
                    'requirementType' => $requirementType,
                    'priority' => $priority,
                    'status' => $status,
                ],
            ]);

            return $requirements;
        } catch (\Exception $e) {
            $this->logger->error('Semantic search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Check if Neo4j is available
     */
    public function isAvailable(): bool
    {
        try {
            $this->client->run('RETURN 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create indexes for better performance
     */
    public function createIndexes(): void
    {
        try {
            // ============================================
            // CLEANUP: Drop old indexes that conflict with constraints
            // ============================================
            try {
                $this->client->run('DROP INDEX requirement_identifier IF EXISTS');
                $this->client->run('DROP INDEX app_name_key IF EXISTS');
            } catch (\Exception $e) {
                // Ignore errors if indexes don't exist
            }
            
            // ============================================
            // UNIQUE CONSTRAINTS (IREB-Standard)
            // ============================================
            $this->client->run('CREATE CONSTRAINT requirement_id_unique IF NOT EXISTS FOR (r:SoftwareRequirement) REQUIRE r.identifier IS UNIQUE');
            $this->client->run('CREATE CONSTRAINT app_namekey_unique IF NOT EXISTS FOR (a:SoftwareApplication) REQUIRE a.nameKey IS UNIQUE');
            
            // ============================================
            // REQUIREMENT INDEXES (IREB)
            // ============================================
            
            // Note: identifier index is created automatically by UNIQUE CONSTRAINT above
            // Core identification
            $this->client->run('CREATE INDEX requirement_name IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.name)');
            
            // Classification (häufig für Filterung)
            $this->client->run('CREATE INDEX requirement_type IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.requirementType)');
            $this->client->run('CREATE INDEX requirement_priority IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.priority)');
            $this->client->run('CREATE INDEX requirement_status IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.status)');
            $this->client->run('CREATE INDEX requirement_category IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.category)');
            
            // Composite Index für häufige Queries (status + priority)
            $this->client->run('CREATE INDEX requirement_status_priority IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.status, r.priority)');
            
            // Risk Level für Risk Management
            $this->client->run('CREATE INDEX requirement_risk IF NOT EXISTS FOR (r:SoftwareRequirement) ON (r.riskLevel)');
            
            // ============================================
            // APPLICATION INDEXES
            // ============================================
            // Note: nameKey index is created automatically by UNIQUE CONSTRAINT above
            $this->client->run('CREATE INDEX app_name IF NOT EXISTS FOR (a:SoftwareApplication) ON (a.name)');
            $this->client->run('CREATE INDEX app_version IF NOT EXISTS FOR (a:SoftwareApplication) ON (a.version)');

            $this->logger->info('Neo4j IREB indexes and constraints created successfully', [
                'constraints' => 2,  // identifier, nameKey
                'indexes' => 10,     // Reduced because constraints create their own indexes
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to create Neo4j indexes', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create Relations between Requirements (IREB Graph)
     * 
     * Creates graph relationships based on requirement dependencies and related requirements
     * 
     * @param SoftwareRequirements[] $requirements
     */
    private function createRequirementRelations(array $requirements): void
    {
        $relationCount = 0;
        
        try {
            foreach ($requirements as $requirement) {
                // DEPENDS_ON Relations (from dependencies.dependsOn array)
                if (isset($requirement->dependencies['dependsOn']) && is_array($requirement->dependencies['dependsOn'])) {
                    foreach ($requirement->dependencies['dependsOn'] as $dependencyId) {
                        try {
                            $this->client->run(
                                'MATCH (source:SoftwareRequirement {identifier: $sourceId})
                                MATCH (target:SoftwareRequirement {identifier: $targetId})
                                MERGE (source)-[r:DEPENDS_ON]->(target)
                                ON CREATE SET r.type = $type, r.strength = $strength, r.createdAt = datetime()
                                RETURN r',
                                [
                                    'sourceId' => $requirement->identifier,
                                    'targetId' => $dependencyId,
                                    'type' => 'logical',
                                    'strength' => 'mandatory',
                                ]
                            );
                            $relationCount++;
                        } catch (\Exception $e) {
                            $this->logger->debug('Failed to create DEPENDS_ON relation', [
                                'source' => $requirement->identifier,
                                'target' => $dependencyId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // CONFLICTS_WITH Relations (from dependencies.conflicts array)
                if (isset($requirement->dependencies['conflicts']) && is_array($requirement->dependencies['conflicts'])) {
                    foreach ($requirement->dependencies['conflicts'] as $conflictId) {
                        try {
                            $this->client->run(
                                'MATCH (source:SoftwareRequirement {identifier: $sourceId})
                                MATCH (target:SoftwareRequirement {identifier: $targetId})
                                MERGE (source)-[r:CONFLICTS_WITH]->(target)
                                ON CREATE SET r.severity = $severity, r.resolved = $resolved, r.createdAt = datetime()
                                RETURN r',
                                [
                                    'sourceId' => $requirement->identifier,
                                    'targetId' => $conflictId,
                                    'severity' => 'medium',
                                    'resolved' => false,
                                ]
                            );
                            $relationCount++;
                        } catch (\Exception $e) {
                            $this->logger->debug('Failed to create CONFLICTS_WITH relation', [
                                'source' => $requirement->identifier,
                                'target' => $conflictId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // EXTENDS Relations (from dependencies.extends array)
                if (isset($requirement->dependencies['extends']) && is_array($requirement->dependencies['extends'])) {
                    foreach ($requirement->dependencies['extends'] as $extendsId) {
                        try {
                            $this->client->run(
                                'MATCH (source:SoftwareRequirement {identifier: $sourceId})
                                MATCH (target:SoftwareRequirement {identifier: $targetId})
                                MERGE (source)-[r:EXTENDS]->(target)
                                ON CREATE SET r.extensionType = $extensionType, r.createdAt = datetime()
                                RETURN r',
                                [
                                    'sourceId' => $requirement->identifier,
                                    'targetId' => $extendsId,
                                    'extensionType' => 'optional',
                                ]
                            );
                            $relationCount++;
                        } catch (\Exception $e) {
                            $this->logger->debug('Failed to create EXTENDS relation', [
                                'source' => $requirement->identifier,
                                'target' => $extendsId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // Generic RELATED_TO Relations (from relatedRequirements array)
                if (!empty($requirement->relatedRequirements)) {
                    foreach ($requirement->relatedRequirements as $relatedId) {
                        // Skip if already covered by specific relations
                        if (isset($requirement->dependencies['dependsOn']) && in_array($relatedId, $requirement->dependencies['dependsOn'])) {
                            continue;
                        }
                        if (isset($requirement->dependencies['extends']) && in_array($relatedId, $requirement->dependencies['extends'])) {
                            continue;
                        }
                        if (isset($requirement->dependencies['conflicts']) && in_array($relatedId, $requirement->dependencies['conflicts'])) {
                            continue;
                        }
                        
                        try {
                            $this->client->run(
                                'MATCH (source:SoftwareRequirement {identifier: $sourceId})
                                MATCH (target:SoftwareRequirement {identifier: $targetId})
                                MERGE (source)-[r:RELATED_TO]->(target)
                                ON CREATE SET r.createdAt = datetime()
                                RETURN r',
                                [
                                    'sourceId' => $requirement->identifier,
                                    'targetId' => $relatedId,
                                ]
                            );
                            $relationCount++;
                        } catch (\Exception $e) {
                            $this->logger->debug('Failed to create RELATED_TO relation', [
                                'source' => $requirement->identifier,
                                'target' => $relatedId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
            
            if ($relationCount > 0) {
                $this->logger->info('Created requirement relations', [
                    'relation_count' => $relationCount,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to create requirement relations', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

