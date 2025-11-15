<?php

namespace App\Service\Connector;

use App\Constants\SystemConstants;
use App\Exception\ServiceException;
use App\Service\HttpClientService;
use App\Contract\ConnectorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Neo4j connector for graph database communication.
 * 
 * Provides functionality to interact with Neo4j database including
 * health checks, data indexing, and query execution.
 */
class Neo4JConnector implements ConnectorInterface
{
    private string $neo4jBaseUrl;

    /**
     * Initialize Neo4j connector with validated configuration.
     * 
     * @param HttpClientService $httpClient HTTP client for Neo4j communication
     * 
     * @throws ServiceException If required environment variables are missing
     */
    public function __construct(
        private readonly HttpClientService $httpClient
    ) {
        // Security: Validate environment variable
        $neo4jUrl = $_ENV['NEO4J_RAG_DATABASE'] ?? '';
        if (empty($neo4jUrl)) {
            throw ServiceException::configurationError('Neo4j', 'NEO4J_RAG_DATABASE environment variable');
        }
        
        $this->neo4jBaseUrl = rtrim($neo4jUrl, '/');
    }

    /**
     * Get Neo4j service status.
     * 
     * @return ResponseInterface HTTP response from Neo4j
     * @throws ServiceException If connection fails
     */
    public function getStatus(): ResponseInterface
    {
        try {
            return $this->httpClient->get($this->neo4jBaseUrl);
        } catch (TransportExceptionInterface $e) {
            throw ServiceException::connectionFailed('Neo4j', $e);
        }
    }

    public function getServiceInfo(): array
    {
        try {
            $response = $this->getStatus();
            $content = $response->getContent();
            $statusCode = $response->getStatusCode();
            
            // Try to parse Neo4j response - it might be JSON or plain text
            $version = 'unknown';
            if ($statusCode === 200) {
                $jsonData = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $version = $jsonData['neo4j_version'] ?? $jsonData['version'] ?? 'unknown';
                } else {
                    // If not JSON, try to extract version from text
                    if (preg_match('/(\d+\.\d+\.\d+)/', $content, $matches)) {
                        $version = $matches[1];
                    } else {
                        $version = trim($content);
                    }
                }
            }
            
            return [
                'name' => 'Neo4j',
                'version' => $version,
                'status_code' => $statusCode,
                'healthy' => $statusCode === 200
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Neo4j',
                'version' => 'unknown',
                'status_code' => 503,
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function generateIndex($documentObject): ResponseInterface
    {
        if (!is_array($documentObject)) {
            throw new \InvalidArgumentException('Document object must be an array');
        }

        // Prepare Cypher query to create nodes and relationships
        $cypherQuery = $this->buildCypherQuery($documentObject);
        
        $payload = [
            'statements' => [
                [
                    'statement' => $cypherQuery,
                    'parameters' => $this->extractParameters($documentObject)
                ]
            ]
        ];

        try {
            return $this->httpClient->post($this->neo4jBaseUrl . '/db/data/transaction/commit', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'timeout' => 30
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to create Neo4J index: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build Cypher query from document object
     */
    private function buildCypherQuery(array $documentObject): string
    {
        $queries = [];

        // Create document node
        if (isset($documentObject['document'])) {
            $queries[] = "CREATE (doc:Document {id: \$doc_id, title: \$doc_title, content: \$doc_content, created_at: datetime()})";
        }

        // Create entity nodes and relationships
        if (isset($documentObject['entities'])) {
            foreach ($documentObject['entities'] as $type => $entities) {
                switch ($type) {
                    case 'persons':
                        $queries[] = "WITH doc UNWIND \$persons AS person 
                                     CREATE (p:Person {name: person.name, role: person.role}) 
                                     CREATE (doc)-[:MENTIONS]->(p)";
                        break;
                    case 'organizations':
                        $queries[] = "WITH doc UNWIND \$organizations AS org 
                                     CREATE (o:Organization {name: org.name, type: org.type}) 
                                     CREATE (doc)-[:MENTIONS]->(o)";
                        break;
                    case 'projects':
                        $queries[] = "WITH doc UNWIND \$projects AS project 
                                     CREATE (pr:Project {name: project.name, description: project.description}) 
                                     CREATE (doc)-[:DESCRIBES]->(pr)";
                        break;
                    case 'requirements':
                        $queries[] = "WITH doc UNWIND \$requirements AS req 
                                     CREATE (r:Requirement {text: req.text, priority: req.priority}) 
                                     CREATE (doc)-[:CONTAINS]->(r)";
                        break;
                }
            }
        }

        return implode(' ', $queries);
    }

    /**
     * Execute a custom Cypher query with parameters
     */
    public function executeCypherQuery(string $cypher, array $parameters = []): array
    {
        $url = $this->neo4jBaseUrl . '/db/data/cypher';
        
        $requestData = [
            'query' => $cypher,
            'params' => $parameters
        ];

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $requestData,
                'timeout' => 30
            ]);

            $statusCode = $response->getStatusCode();
            $responseData = json_decode($response->getContent(), true);

            if ($statusCode !== 200) {
                throw new \RuntimeException("Neo4j query failed with status {$statusCode}: " . ($responseData['message'] ?? 'Unknown error'));
            }

            return [
                'data' => $responseData['data'] ?? [],
                'columns' => $responseData['columns'] ?? [],
                'stats' => $responseData['stats'] ?? null
            ];

        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to execute Neo4j query: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract parameters from document object for Cypher query
     */
    private function extractParameters(array $documentObject): array
    {
        $parameters = [];

        // Document parameters
        if (isset($documentObject['document'])) {
            $doc = $documentObject['document'];
            $parameters['doc_id'] = $doc['id'] ?? uniqid('doc_');
            $parameters['doc_title'] = $doc['title'] ?? 'Untitled';
            $parameters['doc_content'] = substr($doc['content'] ?? '', 0, 1000); // Limit content
        }

        // Entity parameters
        if (isset($documentObject['entities'])) {
            foreach ($documentObject['entities'] as $type => $entities) {
                $parameters[$type] = array_map(function ($entity) {
                    return [
                        'name' => $entity['name'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'role' => $entity['role'] ?? '',
                        'description' => $entity['description'] ?? '',
                        'text' => $entity['text'] ?? '',
                        'priority' => $entity['priority'] ?? 'medium'
                    ];
                }, $entities);
            }
        }

        return $parameters;
    }

    /**
     * Search nodes and relationships in Neo4j
     */
    public function searchIndex(string $query, int $limit = 10): ResponseInterface
    {
        $cypherQuery = "
            MATCH (n) 
            WHERE toLower(toString(n.name)) CONTAINS toLower(\$query) 
               OR toLower(toString(n.title)) CONTAINS toLower(\$query)
               OR toLower(toString(n.text)) CONTAINS toLower(\$query)
            RETURN n, labels(n) as types 
            LIMIT \$limit
        ";

        $payload = [
            'statements' => [
                [
                    'statement' => $cypherQuery,
                    'parameters' => [
                        'query' => $query,
                        'limit' => $limit
                    ]
                ]
            ]
        ];

        try {
            return $this->httpClient->post($this->neo4jBaseUrl . '/db/data/transaction/commit', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'timeout' => 15
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to search Neo4J index: " . $e->getMessage(), 0, $e);
        }
    }

    // ========================================================================
    // IRREB-SPEZIFISCHE METHODEN
    // ========================================================================

    /**
     * Erstellt eine OWNED_BY-Beziehung zwischen Requirement und Role
     * 
     * @param string $requirementId ID des Requirements
     * @param string $roleId ID der Rolle
     * @return array Query-Ergebnis
     */
    public function createOwnedByRelationship(string $requirementId, string $roleId): array
    {
        $cypher = "
            MATCH (req:Requirement {id: \$req_id})
            MATCH (role:Role {id: \$role_id})
            MERGE (req)-[r:OWNED_BY]->(role)
            RETURN req, role, r
        ";

        return $this->executeCypherQuery($cypher, [
            'req_id' => $requirementId,
            'role_id' => $roleId
        ]);
    }

    /**
     * Erstellt eine APPLIES_TO-Beziehung zwischen Requirement und Environment
     * 
     * @param string $requirementId ID des Requirements
     * @param string $environmentId ID der Umgebung
     * @return array Query-Ergebnis
     */
    public function createAppliesToRelationship(string $requirementId, string $environmentId): array
    {
        $cypher = "
            MATCH (req:Requirement {id: \$req_id})
            MATCH (env:Environment {id: \$env_id})
            MERGE (req)-[r:APPLIES_TO]->(env)
            RETURN req, env, r
        ";

        return $this->executeCypherQuery($cypher, [
            'req_id' => $requirementId,
            'env_id' => $environmentId
        ]);
    }

    /**
     * Erstellt eine SUPPORTS-Beziehung zwischen Requirement und Business
     * 
     * @param string $requirementId ID des Requirements
     * @param string $businessId ID des Business-Kontexts
     * @return array Query-Ergebnis
     */
    public function createSupportsRelationship(string $requirementId, string $businessId): array
    {
        $cypher = "
            MATCH (req:Requirement {id: \$req_id})
            MATCH (biz:Business {id: \$biz_id})
            MERGE (req)-[r:SUPPORTS]->(biz)
            RETURN req, biz, r
        ";

        return $this->executeCypherQuery($cypher, [
            'req_id' => $requirementId,
            'biz_id' => $businessId
        ]);
    }

    /**
     * Erstellt eine DEPENDS_ON-Beziehung zwischen Requirement und Infrastructure
     * 
     * @param string $requirementId ID des Requirements
     * @param string $infrastructureId ID der Infrastruktur
     * @return array Query-Ergebnis
     */
    public function createDependsOnRelationship(string $requirementId, string $infrastructureId): array
    {
        $cypher = "
            MATCH (req:Requirement {id: \$req_id})
            MATCH (infra:Infrastructure {id: \$infra_id})
            MERGE (req)-[r:DEPENDS_ON]->(infra)
            RETURN req, infra, r
        ";

        return $this->executeCypherQuery($cypher, [
            'req_id' => $requirementId,
            'infra_id' => $infrastructureId
        ]);
    }

    /**
     * Erstellt eine USES_SOFTWARE-Beziehung zwischen Requirement und SoftwareApplication
     * 
     * @param string $requirementId ID des Requirements
     * @param string $softwareId ID der Software-Anwendung
     * @return array Query-Ergebnis
     */
    public function createUsesSoftwareRelationship(string $requirementId, string $softwareId): array
    {
        $cypher = "
            MATCH (req:Requirement {id: \$req_id})
            MATCH (sw:SoftwareApplication {id: \$sw_id})
            MERGE (req)-[r:USES_SOFTWARE]->(sw)
            RETURN req, sw, r
        ";

        return $this->executeCypherQuery($cypher, [
            'req_id' => $requirementId,
            'sw_id' => $softwareId
        ]);
    }

    /**
     * Erstellt alle IRREB-Beziehungen für ein Requirement auf einmal
     * 
     * @param string $requirementId ID des Requirements
     * @param array $relationships Assoziatives Array mit Beziehungen
     *        ['roles' => ['ROLE-001'], 'environments' => ['ENV-001'], ...]
     * @return array Statistiken über erstellte Beziehungen
     */
    public function createAllRequirementRelationships(string $requirementId, array $relationships): array
    {
        $stats = [
            'owned_by' => 0,
            'applies_to' => 0,
            'supports' => 0,
            'depends_on' => 0,
            'uses_software' => 0,
            'errors' => []
        ];

        // OWNED_BY zu Roles
        if (!empty($relationships['roles'])) {
            foreach ($relationships['roles'] as $roleId) {
                try {
                    $this->createOwnedByRelationship($requirementId, $roleId);
                    $stats['owned_by']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "OWNED_BY -> {$roleId}: " . $e->getMessage();
                }
            }
        }

        // APPLIES_TO zu Environments
        if (!empty($relationships['environments'])) {
            foreach ($relationships['environments'] as $envId) {
                try {
                    $this->createAppliesToRelationship($requirementId, $envId);
                    $stats['applies_to']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "APPLIES_TO -> {$envId}: " . $e->getMessage();
                }
            }
        }

        // SUPPORTS zu Businesses
        if (!empty($relationships['businesses'])) {
            foreach ($relationships['businesses'] as $bizId) {
                try {
                    $this->createSupportsRelationship($requirementId, $bizId);
                    $stats['supports']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "SUPPORTS -> {$bizId}: " . $e->getMessage();
                }
            }
        }

        // DEPENDS_ON zu Infrastructures
        if (!empty($relationships['infrastructures'])) {
            foreach ($relationships['infrastructures'] as $infraId) {
                try {
                    $this->createDependsOnRelationship($requirementId, $infraId);
                    $stats['depends_on']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "DEPENDS_ON -> {$infraId}: " . $e->getMessage();
                }
            }
        }

        // USES_SOFTWARE zu SoftwareApplications
        if (!empty($relationships['softwareApplications'])) {
            foreach ($relationships['softwareApplications'] as $swId) {
                try {
                    $this->createUsesSoftwareRelationship($requirementId, $swId);
                    $stats['uses_software']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "USES_SOFTWARE -> {$swId}: " . $e->getMessage();
                }
            }
        }

        return $stats;
    }

    /**
     * Findet alle Requirements mit ihren IRREB-Beziehungen
     * 
     * @param int $limit Maximale Anzahl Requirements
     * @return array Liste von Requirements mit Beziehungen
     */
    public function findRequirementsWithRelationships(int $limit = 100): array
    {
        $cypher = "
            MATCH (req:Requirement)
            OPTIONAL MATCH (req)-[r1:OWNED_BY]->(role:Role)
            OPTIONAL MATCH (req)-[r2:APPLIES_TO]->(env:Environment)
            OPTIONAL MATCH (req)-[r3:SUPPORTS]->(biz:Business)
            OPTIONAL MATCH (req)-[r4:DEPENDS_ON]->(infra:Infrastructure)
            OPTIONAL MATCH (req)-[r5:USES_SOFTWARE]->(sw:SoftwareApplication)
            RETURN req,
                   collect(DISTINCT role) as roles,
                   collect(DISTINCT env) as environments,
                   collect(DISTINCT biz) as businesses,
                   collect(DISTINCT infra) as infrastructures,
                   collect(DISTINCT sw) as software_applications
            LIMIT \$limit
        ";

        return $this->executeCypherQuery($cypher, ['limit' => $limit]);
    }

    /**
     * Erstellt Indizes für IRREB-Entitäten zur Performance-Optimierung
     * 
     * @return array Ergebnis der Index-Erstellung
     */
    public function createIrrebIndexes(): array
    {
        $indexes = [
            "CREATE INDEX requirement_id_index IF NOT EXISTS FOR (n:Requirement) ON (n.id)",
            "CREATE INDEX requirement_name_index IF NOT EXISTS FOR (n:Requirement) ON (n.name)",
            "CREATE INDEX requirement_type_index IF NOT EXISTS FOR (n:Requirement) ON (n.type)",
            "CREATE INDEX requirement_priority_index IF NOT EXISTS FOR (n:Requirement) ON (n.priority)",
            "CREATE INDEX role_id_index IF NOT EXISTS FOR (n:Role) ON (n.id)",
            "CREATE INDEX environment_id_index IF NOT EXISTS FOR (n:Environment) ON (n.id)",
            "CREATE INDEX business_id_index IF NOT EXISTS FOR (n:Business) ON (n.id)",
            "CREATE INDEX infrastructure_id_index IF NOT EXISTS FOR (n:Infrastructure) ON (n.id)",
            "CREATE INDEX software_id_index IF NOT EXISTS FOR (n:SoftwareApplication) ON (n.id)"
        ];

        $results = [];
        foreach ($indexes as $index) {
            try {
                $results[] = $this->executeCypherQuery($index, []);
            } catch (\Exception $e) {
                $results[] = ['error' => $e->getMessage(), 'query' => $index];
            }
        }

        return $results;
    }

    /**
     * Löscht alle IRREB-Daten aus Neo4j (Vorsicht!)
     * 
     * @param bool $confirm Sicherheitsbestätigung
     * @return array Statistiken über gelöschte Nodes und Relationships
     */
    public function deleteAllIrrebData(bool $confirm = false): array
    {
        if (!$confirm) {
            throw new \RuntimeException('Deletion must be confirmed with $confirm = true');
        }

        $cypher = "
            MATCH (n)
            WHERE n:Requirement OR n:Role OR n:Environment OR n:Business OR n:Infrastructure OR n:SoftwareApplication
            DETACH DELETE n
            RETURN count(n) as deleted_count
        ";

        return $this->executeCypherQuery($cypher, []);
    }
}
