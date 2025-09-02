<?php
// src/Service/Connector/Neo4JConnector.php
namespace App\Service\Connector;

use App\Service\HttpClientService;
use App\Contract\ConnectorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Neo4JConnector implements ConnectorInterface
{
    private HttpClientService $httpClient;
    private string $neo4jBaseUrl; // 🔧 Fixed: Missing property declaration

    public function __construct(HttpClientService $httpClient)
    {
        $this->httpClient = $httpClient;
        
        // 🔒 Security: Validate environment variable
        $neo4jUrl = $_ENV['NEO4J_RAG_DATABASE'] ?? '';
        if (empty($neo4jUrl)) {
            throw new \InvalidArgumentException('NEO4J_RAG_DATABASE environment variable is required');
        }
        
        $this->neo4jBaseUrl = rtrim($neo4jUrl, '/');
    }

    public function getStatus(): ResponseInterface
    {
        try {
            return $this->httpClient->get($this->neo4jBaseUrl);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to connect to Neo4J: " . $e->getMessage(), 0, $e);
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
        $url = $this->neo4jUrl . '/db/data/cypher';
        
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
}
