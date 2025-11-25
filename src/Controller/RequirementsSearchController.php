<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Schema\RequirementSearchInput;
use App\Exception\EmbeddingModelNotAvailableException;
use App\Service\Embeddings\OllamaEmbeddingsService;
use App\Service\Neo4j\Neo4jConnectorService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Semantic Search Controller for Requirements
 * 
 * Allows natural language queries to find similar requirements
 * using embeddings and Neo4j vector search
 */
class RequirementsSearchController extends AbstractController
{
    public function __construct(
        private readonly OllamaEmbeddingsService $embeddingsService,
        private readonly Neo4jConnectorService $neo4jConnector,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/api/requirements/search', name: 'api_requirements_search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        // Deserialize and validate input
        try {
            /** @var RequirementSearchInput $searchInput */
            $searchInput = $this->serializer->deserialize(
                $request->getContent(),
                RequirementSearchInput::class,
                'json'
            );
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Invalid JSON',
                'message' => $e->getMessage(),
            ], 400);
        }

        // Validate input
        $errors = $this->validator->validate($searchInput);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'error' => 'Validation failed',
                'violations' => $errorMessages,
            ], 422);
        }

        try {
            $startTime = microtime(true);

            // 1. Generate embedding for the query
            $this->logger->info('Generating embedding for search query', [
                'query' => $searchInput->query,
                'filters' => [
                    'type' => $searchInput->requirementType,
                    'priority' => $searchInput->priority,
                    'status' => $searchInput->status,
                ],
            ]);

            $queryEmbedding = $this->embeddingsService->embed($searchInput->query);

            // 2. Search similar requirements in Neo4j with filters
            $this->logger->info('Searching similar requirements in Neo4j', [
                'limit' => $searchInput->limit,
                'minSimilarity' => $searchInput->minSimilarity,
            ]);

            $results = $this->neo4jConnector->searchSimilarRequirements(
                queryEmbedding: $queryEmbedding,
                queryText: $searchInput->query,
                limit: $searchInput->limit,
                minSimilarity: $searchInput->minSimilarity,
                requirementType: $searchInput->requirementType,
                priority: $searchInput->priority,
                status: $searchInput->status
            );

            $duration = microtime(true) - $startTime;

            return $this->json([
                'query' => $searchInput->query,
                'results' => $results,
                'count' => count($results),
                'limit' => $searchInput->limit,
                'duration_seconds' => round($duration, 3),
                'filters' => [
                    'minSimilarity' => $searchInput->minSimilarity,
                    'requirementType' => $searchInput->requirementType,
                    'priority' => $searchInput->priority,
                    'status' => $searchInput->status,
                ],
                'metadata' => [
                    'embedding_model' => $_ENV['OLLAMA_EMBEDDING_MODEL'] ?? 'nomic-embed-text',
                    'embedding_dimensions' => count($queryEmbedding),
                ],
            ]);
        } catch (EmbeddingModelNotAvailableException $e) {
            $this->logger->error('Embedding model not available', [
                'query' => $searchInput->query,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Embedding model not available',
                'message' => $e->getMessage(),
                'query' => $searchInput->query,
                'type' => 'MODEL_NOT_FOUND',
            ], 503);
        } catch (\Exception $e) {
            $this->logger->error('Semantic search failed', [
                'query' => $searchInput->query ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Semantic search failed',
                'message' => $e->getMessage(),
                'query' => $searchInput->query ?? 'unknown',
            ], 500);
        }
    }

    #[Route('/api/requirements/search/suggest', name: 'api_requirements_search_suggest', methods: ['POST'])]
    public function searchWithSuggestion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['query'])) {
            return $this->json([
                'error' => 'Missing required field: query',
            ], 400);
        }

        $query = $data['query'];
        $limit = $data['limit'] ?? 10;

        try {
            // Generate embedding and search
            $queryEmbedding = $this->embeddingsService->embed($query);
            $results = $this->neo4jConnector->searchSimilarRequirements(
                queryEmbedding: $queryEmbedding,
                queryText: $query,
                limit: $limit
            );

            // If no results found, suggest refining the query
            if (empty($results)) {
                return $this->json([
                    'query' => $query,
                    'results' => [],
                    'count' => 0,
                    'suggestion' => 'No similar requirements found. Try refining your query or check if requirements exist in the database.',
                ]);
            }

            return $this->json([
                'query' => $query,
                'results' => $results,
                'count' => count($results),
                'limit' => $limit,
            ]);
        } catch (EmbeddingModelNotAvailableException $e) {
            return $this->json([
                'error' => 'Embedding model not available',
                'message' => $e->getMessage(),
                'query' => $query,
                'type' => 'MODEL_NOT_FOUND',
            ], 503);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Search failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

