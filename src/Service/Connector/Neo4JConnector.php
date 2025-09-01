<?php
// src/Service/Connector/TikaConnector.php
namespace App\Service\Connector;

use App\Service\HttpClientService;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Neo4JConnector
{
    private HttpClientService $httpClient;

    public function __construct(HttpClientService $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->neo4jBaseUrl = rtrim($_ENV['NEO4J_RAG_DATABASE'], '/');
    }

    public function getStatus(): ResponseInterface
    {
        return $this->httpClient->get($this->neo4jBaseUrl);
    }

    public function generateIndex($documentObject): ResponseInterface
    {
       //TODO
    }
}
