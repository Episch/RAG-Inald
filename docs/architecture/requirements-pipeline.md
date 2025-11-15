# 📋 Requirements-Extraktion Pipeline

Vollständige Dokumentation der IRREB + schema.org Requirements-Pipeline mit TOON-Format Integration.

## 📖 Übersicht

Die Requirements-Pipeline extrahiert automatisch strukturierte Requirements aus Dokumenten (PDF/Excel) und speichert sie als IRREB-konformen Graph in Neo4j.

### Pipeline-Flow

```
Documents (PDF/Excel)
   │
   ├─> Apache Tika (Docker :9998)
   │   └─> Text Extraction
   │
   ├─> Token Chunker
   │   └─> Count & Split
   │
   ├─> LLM (Ollama :11434)
   │   └─> TOON-Format Prompt
   │   └─> Structured JSON Output
   │
   └─> Neo4j (Docker :7687)
       └─> IRREB Graph Import
```

## 🎯 IRREB Entities

### 1. Requirement
```yaml
Properties:
  - id: String (unique)
  - name: String
  - description: Text
  - type: functional | non-functional | constraint
  - priority: critical | high | medium | low
  - status: draft | approved | implemented | validated | deprecated
  - source: String
  - rationale: Text (optional)
  - acceptanceCriteria: Text (optional)
```

### 2. Role
```yaml
Properties:
  - id: String (unique)
  - name: String
  - description: Text
  - level: executive | manager | operator | end-user
  - department: String
  - responsibilities: Array<String>
```

### 3. Environment
```yaml
Properties:
  - id: String (unique)
  - name: String
  - type: production | staging | development | test
  - description: Text
  - location: String
  - constraints: Array<String>
```

### 4. Business
```yaml
Properties:
  - id: String (unique)
  - name: String
  - goal: Text
  - objective: Text
  - kpis: Array<String>
```

### 5. Infrastructure
```yaml
Properties:
  - id: String (unique)
  - name: String
  - type: server | network | storage | database | cloud
  - description: Text
  - provider: String
  - capacity: Object
```

### 6. SoftwareApplication (schema.org)
```yaml
Properties:
  - id: String (unique)
  - name: String
  - version: String
  - operatingSystem: String
  - category: String
  - softwareRequirements: Array<String>
```

## 🔗 IRREB Relationships

```cypher
// Requirement belongs to Role
(:Requirement)-[:OWNED_BY]->(:Role)

// Requirement applies to Environment
(:Requirement)-[:APPLIES_TO]->(:Environment)

// Requirement supports Business Goal
(:Requirement)-[:SUPPORTS]->(:Business)

// Requirement depends on Infrastructure
(:Requirement)-[:DEPENDS_ON]->(:Infrastructure)

// Requirement uses Software
(:Requirement)-[:USES_SOFTWARE]->(:SoftwareApplication)
```

## 💻 Verwendung

### CLI Command

```bash
# Einzelne Datei
php bin/console app:process-requirements path/to/requirements.pdf

# Verzeichnis (alle PDFs)
php bin/console app:process-requirements path/to/folder --pattern="*.pdf"

# Mit spezifischem Modell
php bin/console app:process-requirements path/to/file.pdf --model=llama3.2:7b

# Ohne Neo4j-Import (nur JSON)
php bin/console app:process-requirements path/to/file.pdf --no-import

# Asynchron über Queue
php bin/console app:process-requirements path/to/folder --async
```

### Programmatisch

```php
use App\Service\RequirementsExtractionService;

// Inject Service
$requirementsGraph = $this->extractionService->extractFromDocuments(
    filePaths: ['/path/to/doc.pdf'],
    model: 'llama3.2',
    importToNeo4j: true
);

// Token-Statistiken abrufen
$stats = $this->extractionService->getTokenStats();
```

## 📊 Output-Format

### Console

```
⚡ Performance & Token-Statistiken
┌──────────────────────────┬──────────────────────────────┐
│ Modell                   │ llama3.2                     │
│ Format                   │ TOON (Token-optimiert)       │
│ 📥 Input Tokens          │ 3,542                        │
│ 📤 Output Tokens         │ 1,287                        │
│ 💯 Total Tokens          │ 4,829                        │
│ 💰 Ersparnis vs. JSON    │ 1,846 Tokens (~38%)          │
└──────────────────────────┴──────────────────────────────┘

📊 Extraktions-Ergebnisse
┌────────────────────────┬────────┐
│ Requirements           │ 42     │
│ Roles                  │ 8      │
│ Relationships          │ 156    │
└────────────────────────┴────────┘
```

### JSON-Datei

```json
{
  "request_id": "req_ext_12345",
  "timestamp": "2025-11-15T10:00:00Z",
  "graph": {
    "requirements": [...],
    "roles": [...],
    "relationships": [...]
  },
  "statistics": {
    "total_requirements": 42,
    "total_tokens": 4829
  }
}
```

## ⚡ TOON-Format Integration

Die Pipeline nutzt [TOON (Token-Oriented Object Notation)](https://github.com/toon-format/toon) für **30-40% Token-Ersparnis**.

**Siehe:** [TOON Format Documentation](../features/toon-format.md)

## 🔧 Konfiguration

### Environment Variables (.env)

```bash
# Apache Tika
DOCUMENT_EXTRACTOR_URL=http://tika:9998

# Ollama LLM
LMM_URL=http://ollama:11434

# Neo4j
NEO4J_RAG_DATABASE=bolt://neo4j:password@neo4j:7687

# Storage
DOCUMENT_STORAGE_PATH=/var/www/html/public/storage
```

### Token-Limits (SystemConstants.php)

```php
TOKEN_SYNC_LIMIT = 4000;      // Max für synchrone Verarbeitung
TOKEN_CHUNK_SIZE = 800;       // Chunk-Größe
TOKEN_CHUNK_OVERLAP = 100;    // Overlap zwischen Chunks
```

## 📈 Performance

### Token-Chunking

Große Dokumente werden automatisch gechunked:
- **<4000 Tokens:** Direkte Verarbeitung
- **>4000 Tokens:** Automatisches Chunking mit Overlap
- **Deduplizierung:** Nach Merge der Chunks

### TOON vs. JSON

| Requirements | JSON Tokens | TOON Tokens | Ersparnis |
|--------------|-------------|-------------|-----------|
| 10           | 1,200       | 750         | 37%       |
| 50           | 6,500       | 3,900       | 40%       |
| 100          | 13,000      | 7,800       | 40%       |

## 🔍 Neo4j-Queries

### Alle Requirements mit Beziehungen

```cypher
MATCH (req:Requirement)
OPTIONAL MATCH (req)-[:OWNED_BY]->(role:Role)
OPTIONAL MATCH (req)-[:APPLIES_TO]->(env:Environment)
OPTIONAL MATCH (req)-[:SUPPORTS]->(biz:Business)
RETURN req, role, env, biz
LIMIT 100
```

### Requirements nach Priorität

```cypher
MATCH (req:Requirement {priority: 'critical'})
RETURN req
```

### Graph-Visualisierung

```cypher
MATCH (req:Requirement)-[r]-(n)
RETURN req, r, n
LIMIT 50
```

## 🧪 Testing

```bash
# Unit-Tests
php bin/phpunit tests/Service/RequirementsExtractionServiceTest.php

# Integration-Test mit Test-Dokument
php bin/console app:process-requirements tests/fixtures/sample.pdf --no-import
```

## 🐛 Troubleshooting

### Tika nicht erreichbar

```bash
docker ps | grep tika
docker logs tika
docker restart tika
```

### LLM gibt ungültiges JSON zurück

- Prüfe Prompt-Template
- Reduziere Temperature (`--model-temperature=0.2`)
- Prüfe Modell-Version

### Neo4j-Import fehlschlägt

```bash
# Verbindung testen
php bin/console app:status

# Indizes erstellen
php bin/console app:neo4j:create-indexes
```

## 📚 Weiterführende Dokumentation

- [TOON Format](../features/toon-format.md) - Token-Optimierung
- [LLM Integration](llm-integration.md) - Ollama-Details
- [Testing](../development/testing.md) - Test-Suite

---

**Siehe auch:**
- [Quick Start Guide](../getting-started/quickstart.md)
- [System Overview](overview.md)
- [Troubleshooting](../troubleshooting/)

