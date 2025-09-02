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
        // TODO: Implement Neo4J index generation
        throw new \BadMethodCallException('Neo4J index generation not yet implemented');
    }
}
