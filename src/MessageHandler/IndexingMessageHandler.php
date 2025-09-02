<?php

namespace App\MessageHandler;

use App\Message\IndexingMessage;
use App\Service\Connector\Neo4JConnector;
use App\Service\QueueStatsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class IndexingMessageHandler
{
    private Neo4JConnector $neo4jConnector;
    private QueueStatsService $queueStats;
    private LoggerInterface $logger;

    public function __construct(
        Neo4JConnector $neo4jConnector,
        QueueStatsService $queueStats,
        LoggerInterface $logger
    ) {
        $this->neo4jConnector = $neo4jConnector;
        $this->queueStats = $queueStats;
        $this->logger = $logger;
    }

    public function __invoke(IndexingMessage $message): int
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing graph indexing message', [
            'entity_type' => $message->entityType,
            'operation' => $message->operation,
            'relationship_count' => count($message->relationships),
            'has_indexes' => !empty($message->indexes)
        ]);

        try {
            // Create the main entity node
            $nodeResult = $this->createOrUpdateNode(
                $message->entityType,
                $message->entityData,
                $message->operation
            );
            
            $processedRelationships = 0;
            $processedIndexes = 0;
            
            // Process relationships if any
            if (!empty($message->relationships)) {
                $processedRelationships = $this->processRelationships(
                    $message->entityType,
                    $message->entityData,
                    $message->relationships
                );
            }
            
            // Create indexes if specified
            if (!empty($message->indexes)) {
                $processedIndexes = $this->processIndexes(
                    $message->entityType,
                    $message->indexes
                );
            }
            
            $executionTime = round(microtime(true) - $startTime, 3);
            
            $this->logger->info('Graph indexing completed successfully', [
                'entity_type' => $message->entityType,
                'operation' => $message->operation,
                'relationships_processed' => $processedRelationships,
                'indexes_processed' => $processedIndexes,
                'execution_time' => $executionTime . 's'
            ]);
            
            // Decrement queue counter when message is processed successfully
            $this->queueStats->decrementQueueCounter();
            
            return 0;
            
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 3);
            
            $this->logger->error('Graph indexing failed', [
                'entity_type' => $message->entityType,
                'operation' => $message->operation,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime . 's'
            ]);
            
            // Decrement counter even on failure to keep count accurate
            $this->queueStats->decrementQueueCounter();
            
            throw $e;
        }
    }

    /**
     * Create or update a node in Neo4j
     */
    private function createOrUpdateNode(string $entityType, array $entityData, string $operation): array
    {
        $nodeLabel = ucfirst($entityType);
        
        // Build Cypher query based on operation
        $cypher = match ($operation) {
            'create' => $this->buildCreateQuery($nodeLabel, $entityData),
            'update' => $this->buildUpdateQuery($nodeLabel, $entityData),
            'merge' => $this->buildMergeQuery($nodeLabel, $entityData),
            'delete' => $this->buildDeleteQuery($nodeLabel, $entityData),
            default => $this->buildMergeQuery($nodeLabel, $entityData)
        };
        
        return $this->neo4jConnector->executeCypherQuery($cypher['query'], $cypher['parameters']);
    }

    /**
     * Process relationships for the entity
     */
    private function processRelationships(string $entityType, array $entityData, array $relationships): int
    {
        $processed = 0;
        
        foreach ($relationships as $relationship) {
            try {
                $cypher = $this->buildRelationshipQuery(
                    ucfirst($entityType),
                    $entityData,
                    $relationship
                );
                
                $this->neo4jConnector->executeCypherQuery($cypher['query'], $cypher['parameters']);
                $processed++;
                
            } catch (\Exception $e) {
                $this->logger->warning('Failed to process relationship', [
                    'relationship_type' => $relationship['type'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $processed;
    }

    /**
     * Create indexes for the entity type
     */
    private function processIndexes(string $entityType, array $indexes): int
    {
        $processed = 0;
        $nodeLabel = ucfirst($entityType);
        
        foreach ($indexes as $index) {
            try {
                $indexName = "{$nodeLabel}_{$index['property']}_index";
                $indexType = $index['type'] ?? 'btree';
                
                $cypher = "CREATE INDEX {$indexName} IF NOT EXISTS FOR (n:{$nodeLabel}) ON (n.{$index['property']})";
                
                $this->neo4jConnector->executeCypherQuery($cypher, []);
                $processed++;
                
            } catch (\Exception $e) {
                $this->logger->warning('Failed to create index', [
                    'entity_type' => $entityType,
                    'property' => $index['property'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $processed;
    }

    /**
     * Build CREATE query
     */
    private function buildCreateQuery(string $nodeLabel, array $entityData): array
    {
        $properties = [];
        $parameters = [];
        
        foreach ($entityData as $key => $value) {
            $properties[] = "{$key}: \${$key}";
            $parameters[$key] = $value;
        }
        
        $propertiesStr = implode(', ', $properties);
        $query = "CREATE (n:{$nodeLabel} {{$propertiesStr}}) RETURN n";
        
        return ['query' => $query, 'parameters' => $parameters];
    }

    /**
     * Build MERGE query (most flexible)
     */
    private function buildMergeQuery(string $nodeLabel, array $entityData): array
    {
        // Find primary identifier for matching
        $identifier = $this->findPrimaryIdentifier($entityData);
        
        if (!$identifier) {
            throw new \InvalidArgumentException('No suitable identifier found for MERGE operation');
        }
        
        [$idKey, $idValue] = $identifier;
        
        $setProperties = [];
        $parameters = [$idKey => $idValue];
        
        foreach ($entityData as $key => $value) {
            if ($key !== $idKey) {
                $setProperties[] = "n.{$key} = \${$key}";
                $parameters[$key] = $value;
            }
        }
        
        $setClause = !empty($setProperties) ? 'SET ' . implode(', ', $setProperties) : '';
        $query = "MERGE (n:{$nodeLabel} {{{$idKey}: \${$idKey}}}) {$setClause} RETURN n";
        
        return ['query' => $query, 'parameters' => $parameters];
    }

    /**
     * Build UPDATE query
     */
    private function buildUpdateQuery(string $nodeLabel, array $entityData): array
    {
        $identifier = $this->findPrimaryIdentifier($entityData);
        
        if (!$identifier) {
            throw new \InvalidArgumentException('No suitable identifier found for UPDATE operation');
        }
        
        [$idKey, $idValue] = $identifier;
        
        $setProperties = [];
        $parameters = [$idKey => $idValue];
        
        foreach ($entityData as $key => $value) {
            if ($key !== $idKey) {
                $setProperties[] = "n.{$key} = \${$key}";
                $parameters[$key] = $value;
            }
        }
        
        $setClause = implode(', ', $setProperties);
        $query = "MATCH (n:{$nodeLabel} {{{$idKey}: \${$idKey}}}) SET {$setClause} RETURN n";
        
        return ['query' => $query, 'parameters' => $parameters];
    }

    /**
     * Build DELETE query
     */
    private function buildDeleteQuery(string $nodeLabel, array $entityData): array
    {
        $identifier = $this->findPrimaryIdentifier($entityData);
        
        if (!$identifier) {
            throw new \InvalidArgumentException('No suitable identifier found for DELETE operation');
        }
        
        [$idKey, $idValue] = $identifier;
        
        $query = "MATCH (n:{$nodeLabel} {{{$idKey}: \${$idKey}}}) DELETE n";
        $parameters = [$idKey => $idValue];
        
        return ['query' => $query, 'parameters' => $parameters];
    }

    /**
     * Build relationship query
     */
    private function buildRelationshipQuery(string $sourceLabel, array $sourceData, array $relationship): array
    {
        $sourceIdentifier = $this->findPrimaryIdentifier($sourceData);
        if (!$sourceIdentifier) {
            throw new \InvalidArgumentException('No suitable identifier found for source node');
        }
        
        [$sourceIdKey, $sourceIdValue] = $sourceIdentifier;
        
        $targetData = $relationship['target'];
        $targetIdentifier = $this->findPrimaryIdentifier($targetData);
        if (!$targetIdentifier) {
            throw new \InvalidArgumentException('No suitable identifier found for target node');
        }
        
        [$targetIdKey, $targetIdValue] = $targetIdentifier;
        $targetLabel = $targetData['_label'] ?? 'Node';
        
        $relType = $relationship['type'];
        $relProperties = $relationship['properties'] ?? [];
        
        $relPropsStr = '';
        $parameters = [
            'source_id' => $sourceIdValue,
            'target_id' => $targetIdValue
        ];
        
        if (!empty($relProperties)) {
            $propStrs = [];
            foreach ($relProperties as $key => $value) {
                $propStrs[] = "{$key}: \$rel_{$key}";
                $parameters["rel_{$key}"] = $value;
            }
            $relPropsStr = '{' . implode(', ', $propStrs) . '}';
        }
        
        $query = "
            MATCH (source:{$sourceLabel} {{{$sourceIdKey}: \$source_id}})
            MERGE (target:{$targetLabel} {{{$targetIdKey}: \$target_id}})
            MERGE (source)-[r:{$relType} {$relPropsStr}]->(target)
            RETURN r
        ";
        
        return ['query' => $query, 'parameters' => $parameters];
    }

    /**
     * Find the primary identifier for a node
     */
    private function findPrimaryIdentifier(array $data): ?array
    {
        $preferredKeys = ['id', 'uuid', 'name', 'title', 'email', 'handle'];
        
        foreach ($preferredKeys as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                return [$key, $data[$key]];
            }
        }
        
        // Fallback to any non-empty value
        foreach ($data as $key => $value) {
            if (!empty($value) && !str_starts_with($key, '_')) {
                return [$key, $value];
            }
        }
        
        return null;
    }
}
