<?php

namespace App\Controller;

use ApiPlatform\OpenApi\Model\Response;
use App\Service\Connector\Neo4JConnector;
use App\Service\Connector\OllamaConnector;
use App\Service\Connector\TikaConnector;
use Symfony\Component\HttpFoundation\JsonResponse;

class StatusController
{
    private TikaConnector $tikaConnector;
    private Neo4JConnector $neo4JConnector;
    private OllamaConnector $ollamaConnector;

    public function __construct(TikaConnector $tikaConnector, Neo4JConnector $neo4JConnector, OllamaConnector $ollamaConnector)
    {
        $this->tikaConnector = $tikaConnector;
        $this->neo4JConnector = $neo4JConnector;
        $this->ollamaConnector = $ollamaConnector;
    }

    public function __invoke(): JsonResponse
    {
        try{
            return new JsonResponse([
                'status' =>
                    [
                        [
                            'DocumentConnector' => $this->tikaConnector?->getStatus()?->getContent() ?? '',
                            'StatusCode' => $this->tikaConnector?->getStatus()?->getStatusCode()
                        ],
                        [
                            'RagConnector' => json_decode($this->neo4JConnector?->getStatus()?->getContent())->neo4j_version ?? '',
                            'StatusCode' => $this->neo4JConnector?->getStatus()?->getStatusCode()
                        ],
                        [
                            'LmmConnector' => json_decode($this->ollamaConnector?->getStatus()?->getContent())->version ?? '',
                            'StatusCode' => $this->ollamaConnector?->getStatus()?->getStatusCode()
                        ],
                    ]], 200);
        }catch (\Exception $exception){
            return new JsonResponse(['StatusCode' => $exception->getCode(), 'message' => $exception->getMessage()], 422);
        }

    }
}
