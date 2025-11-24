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
     * Store Software Application with Requirements in Neo4j
     * 
     * @return string Node ID
     */
    public function storeSoftwareApplication(
        SoftwareApplication $application,
        array $embeddings = []
    ): string {
        $startTime = microtime(true);

        try {
            // Create Application Node
            $appResult = $this->client->run(
                'CREATE (app:SoftwareApplication {
                    name: $name,
                    description: $description,
                    version: $version,
                    applicationCategory: $applicationCategory,
                    operatingSystem: $operatingSystem,
                    softwareVersion: $softwareVersion,
                    license: $license,
                    provider: $provider,
                    keywords: $keywords,
                    createdAt: datetime()
                })
                RETURN elementId(app) as id',
                [
                    'name' => $application->name,
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
                CREATE (req:Requirement {
                    identifier: $identifier,
                    name: $name,
                    description: $description,
                    requirementType: $requirementType,
                    priority: $priority,
                    category: $category,
                    tags: $tags,
                    source: $source,
                    acceptanceCriteria: $acceptanceCriteria,
                    embedding: $embedding,
                    createdAt: datetime()
                })
                CREATE (app)-[:HAS_REQUIREMENT]->(req)
                RETURN elementId(req) as id',
                [
                    'appId' => $appNodeId,
                    'identifier' => $requirement->identifier,
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

            return $result->first()->get('id');
        } catch (\Exception $e) {
            $this->logger->error('Failed to store requirement in Neo4j', [
                'requirement' => $requirement->identifier,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to store requirement: {$e->getMessage()}", 0, $e);
        }
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

            // Build query with optional similarity filter
            $query = "MATCH (req:Requirement)
                WHERE {$whereClause}
                WITH req, 
                     gds.similarity.cosine(req.embedding, \$queryEmbedding) AS similarity";
            
            if ($minSimilarity > 0.0) {
                $query .= "\nWHERE similarity >= \$minSimilarity";
            }
            
            $query .= "\nORDER BY similarity DESC
                LIMIT \$limit
                RETURN req, similarity";

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
            // Index on requirement identifier
            $this->client->run('CREATE INDEX requirement_identifier IF NOT EXISTS FOR (r:Requirement) ON (r.identifier)');
            
            // Index on requirement type
            $this->client->run('CREATE INDEX requirement_type IF NOT EXISTS FOR (r:Requirement) ON (r.requirementType)');
            
            // Index on application name
            $this->client->run('CREATE INDEX app_name IF NOT EXISTS FOR (a:SoftwareApplication) ON (a.name)');

            $this->logger->info('Neo4j indexes created successfully');
        } catch (\Exception $e) {
            $this->logger->warning('Failed to create Neo4j indexes', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

