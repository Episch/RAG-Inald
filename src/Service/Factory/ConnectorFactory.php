<?php

namespace App\Service\Factory;

use App\Service\Connector\TikaConnector;
use App\Service\Connector\Neo4JConnector;
use App\Service\Connector\LlmConnector;
use App\Service\HttpClientService;
use App\Service\ConfigurationManager;
use App\Contract\ConnectorInterface;

/**
 * Factory for creating connector instances with proper configuration
 */
class ConnectorFactory
{
    public function __construct(
        private HttpClientService $httpClient,
        private ConfigurationManager $config
    ) {
    }

    /**
     * Create Tika connector with configuration
     */
    public function createTikaConnector(): TikaConnector
    {
        // Set environment variables from config for backward compatibility
        $_ENV['DOCUMENT_EXTRACTOR_URL'] = $this->config->get('services.tika.url');
        
        return new TikaConnector($this->httpClient);
    }

    /**
     * Create Neo4j connector with configuration
     */
    public function createNeo4jConnector(): Neo4JConnector
    {
        $_ENV['NEO4J_RAG_DATABASE'] = $this->config->get('services.neo4j.url');
        
        return new Neo4JConnector($this->httpClient);
    }

    /**
     * Create LLM connector with configuration
     */
    public function createLlmConnector(): LlmConnector
    {
        $_ENV['LMM_URL'] = $this->config->get('services.llm.url');
        
        return new LlmConnector($this->httpClient);
    }

    /**
     * Create all connectors
     */
    public function createAllConnectors(): array
    {
        return [
            'TikaConnector' => $this->createTikaConnector(),
            'Neo4jConnector' => $this->createNeo4jConnector(),
            'LlmConnector' => $this->createLlmConnector()
        ];
    }

    /**
     * Test all connector configurations
     */
    public function testAllConfigurations(): array
    {
        $results = [];
        
        try {
            $tika = $this->createTikaConnector();
            $results['tika'] = $this->testConnector('Tika', $tika);
        } catch (\Exception $e) {
            $results['tika'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $neo4j = $this->createNeo4jConnector();
            $results['neo4j'] = $this->testConnector('Neo4j', $neo4j);
        } catch (\Exception $e) {
            $results['neo4j'] = ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $llm = $this->createLlmConnector();
            $results['llm'] = $this->testConnector('LLM', $llm);
        } catch (\Exception $e) {
            $results['llm'] = ['success' => false, 'error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Test a single connector
     */
    private function testConnector(string $name, ConnectorInterface $connector): array
    {
        try {
            $startTime = microtime(true);
            $response = $connector->getStatus();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => $responseTime,
                'healthy' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time_ms' => null,
                'healthy' => false
            ];
        }
    }
}
