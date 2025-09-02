# 🧪 Neo4j Indexing API - Test Examples

## 🚀 Quick Start Test JSONs

### 1. **Minimal Person** (Simplest Test)
```json
{
  "entityType": "Person",
  "entityData": {
    "id": "test_person_001",
    "name": "Test User"
  }
}
```

### 2. **Person with Company Relationship**
```json
{
  "entityType": "Person",
  "entityData": {
    "id": "test_person_002", 
    "name": "Test Manager",
    "role": "Manager",
    "email": "manager@company.com"
  },
  "relationships": [
    {
      "type": "WORKS_FOR",
      "target": {
        "id": "test_company_001",
        "_label": "Company"
      },
      "properties": {
        "since": "2023-01-01",
        "position": "Engineering Manager"
      }
    }
  ]
}
```

## 📊 Advanced Examples

### 3. **Complete Document Entity**
```json
{
  "entityType": "Document",
  "entityData": {
    "uuid": "doc_research_2024_001",
    "title": "Graph Database Optimization",
    "authors": ["Dr. Alice Johnson", "Prof. Bob Wilson"],
    "publication_date": "2024-01-15",
    "keywords": ["graph databases", "optimization", "neo4j"]
  },
  "relationships": [
    {
      "type": "AUTHORED_BY",
      "target": {
        "id": "author_alice_johnson",
        "_label": "Author"
      },
      "properties": {
        "role": "lead_author",
        "contribution": 60
      }
    }
  ],
  "indexes": [
    {"property": "title", "type": "text"},
    {"property": "publication_date", "type": "range"}
  ],
  "operation": "create"
}
```

### 4. **Company with Multiple Relationships**
```json
{
  "entityType": "Company",
  "entityData": {
    "id": "company_techcorp_789",
    "name": "TechCorp Solutions",
    "industry": "Software Development",
    "employee_count": 250,
    "website": "https://techcorp-solutions.com"
  },
  "relationships": [
    {
      "type": "HAS_OFFICE",
      "target": {
        "id": "office_sf_main",
        "_label": "Office"
      },
      "properties": {
        "type": "headquarters",
        "address": "123 Tech Street, San Francisco, CA"
      }
    },
    {
      "type": "COMPETES_WITH",
      "target": {
        "id": "company_rival_corp", 
        "_label": "Company"
      },
      "properties": {
        "market_overlap": "high"
      }
    }
  ],
  "indexes": [
    {"property": "name", "type": "text"}
  ],
  "operation": "merge"
}
```

## ⚙️ Operation Examples

### Update Operation
```json
{
  "entityType": "Person",
  "entityData": {
    "id": "person_john_doe_123",
    "position": "Senior Lead Developer",
    "last_promotion": "2024-01-01"
  },
  "operation": "update"
}
```

### Delete Operation  
```json
{
  "entityType": "Person",
  "entityData": {
    "id": "person_to_delete_999"
  },
  "operation": "delete"
}
```

## 📋 Available Operations
- `create` - Create new entity (fails if exists)
- `update` - Update existing entity (fails if not exists)  
- `merge` - Create or update entity (recommended)
- `delete` - Remove entity from graph

## 🏷️ Index Types
- `btree` - Standard B-tree index (default)
- `text` - Full-text search index
- `range` - Range/numeric index for dates/numbers

## 📚 Entity Types Examples
- `Person` - People, employees, authors, users
- `Document` - Papers, reports, files, articles  
- `Company` - Organizations, businesses, institutions
- `Project` - Software projects, research projects
- `Department` - Company departments, divisions
- `Office` - Physical locations, addresses
- `Technology` - Tools, frameworks, languages

## 🧪 Testing Tips

1. **Start Simple**: Use minimal person example first
2. **Add Relationships**: Test with one relationship  
3. **Try Different Operations**: Test create, merge, update
4. **Complex Entities**: Use document/company examples
5. **Check Responses**: All return same queue response structure

## 🔗 Response Format
All indexing requests return:
```json
{
  "status": "queued",
  "requestId": "idx_...",
  "queueCount": 1,
  "estimatedProcessingTime": "8 seconds",
  "operationType": "indexing",
  "requestData": {
    "entity_type": "Person",
    "operation": "merge",
    "entity_count": 1
  },
  "metadata": {
    "pipeline": "Graph Indexing → Neo4j Storage",
    "database": "Neo4j"
  }
}
```
