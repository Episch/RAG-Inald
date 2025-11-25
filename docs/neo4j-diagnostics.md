# Neo4j Diagnostics Queries

## Check for unlabeled or orphaned nodes

```cypher
// 1. Find ALL nodes (including unlabeled)
MATCH (n)
RETURN labels(n) as labels, count(n) as count
ORDER BY count DESC;

// 2. Find nodes WITHOUT any label
MATCH (n)
WHERE size(labels(n)) = 0
RETURN n
LIMIT 100;

// 3. Find all relationships (even orphaned)
MATCH ()-[r]->()
RETURN type(r) as rel_type, count(r) as count;

// 4. Complete database overview
CALL db.schema.visualization();

// 5. Find nodes with unexpected labels
MATCH (n)
WHERE NOT n:SoftwareApplication AND NOT n:SoftwareRequirement
RETURN labels(n) as unexpected_labels, count(n) as count;
```

## Cleanup for unlabeled nodes

```cypher
// DELETE all nodes without labels
MATCH (n)
WHERE size(labels(n)) = 0
DETACH DELETE n
RETURN count(n) as deleted_unlabeled_nodes;

// DELETE all remaining nodes (if any)
MATCH (n)
DETACH DELETE n
RETURN count(n) as deleted_all_nodes;
```

## Force schema refresh

```cypher
// Clear constraints
CALL db.constraints();

// Clear indexes
CALL db.indexes();

// Verify empty database
MATCH (n)
RETURN count(n) as total_nodes;
```

