# 🏗️ System Overview

## Architektur-Übersicht

Das Backend ist ein **Symfony 7.3** basiertes System für **Requirements-Extraktion** aus Dokumenten mit **LLM-Integration** und **Graph-Datenbank-Speicherung**.

## High-Level Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                    Symfony Backend (PHP 8.2+)               │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Commands   │  │ Controllers  │  │   Messages   │      │
│  │   (CLI)      │  │   (HTTP)     │  │   (Async)    │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
│         │                  │                  │              │
│         └──────────────────┼──────────────────┘              │
│                            │                                 │
│                   ┌────────▼────────┐                        │
│                   │    Services     │                        │
│                   │  - Requirements │                        │
│                   │  - TOON Format  │                        │
│                   │  - Token Chunker│                        │
│                   └────────┬────────┘                        │
│                            │                                 │
│         ┌──────────────────┼──────────────────┐             │
│         │                  │                  │             │
│   ┌─────▼─────┐      ┌────▼────┐      ┌─────▼─────┐       │
│   │   Tika    │      │ Ollama  │      │  Neo4j    │       │
│   │ Connector │      │Connector│      │ Connector │       │
│   └─────┬─────┘      └────┬────┘      └─────┬─────┘       │
│         │                  │                  │             │
└─────────┼──────────────────┼──────────────────┼─────────────┘
          │                  │                  │
     ┌────▼────┐        ┌────▼────┐       ┌────▼────┐
     │  Tika   │        │ Ollama  │       │  Neo4j  │
     │ Docker  │        │ Docker  │       │ Docker  │
     │  :9998  │        │ :11434  │       │  :7687  │
     └─────────┘        └─────────┘       └─────────┘
```

## Komponenten

### 1. **Symfony Application**

#### Controllers
- `ExtractionController` - Document-Upload und Extraktion
- `IndexingController` - Neo4j-Indexierung
- `LlmController` - LLM-Requests
- `StatusController` - System-Status

#### Commands
- `app:process-requirements` - Requirements-Extraktion CLI
- `messenger:consume` - Queue-Worker

#### Services
- `RequirementsExtractionService` - Haupt-Service
- `ToonFormatterService` - TOON-Format Encoder/Decoder
- `TokenChunker` - Token-Counting und Chunking

### 2. **External Services (Docker)**

#### Apache Tika (:9998)
- **Zweck:** Text-Extraktion aus PDF/Excel
- **Input:** Binary files
- **Output:** Plain text / JSON

#### Ollama (:11434)
- **Zweck:** Local LLM (llama3.2)
- **Input:** Prompts (TOON-Format)
- **Output:** Structured JSON

#### Neo4j (:7474, :7687)
- **Zweck:** Graph-Datenbank
- **Schema:** IRREB + schema.org
- **Relationships:** OWNED_BY, APPLIES_TO, SUPPORTS, etc.

#### Redis (:6379)
- **Zweck:** Message Queue
- **Transport:** Symfony Messenger

## Datenfluss

### Requirements-Extraktion Pipeline

```
1. Upload/File
   │
   ├─> Tika Connector
   │   └─> Text Extraction
   │
   ├─> Token Chunker
   │   └─> Count Tokens
   │   └─> Split if needed
   │
   ├─> TOON Formatter
   │   └─> Generate Prompt
   │
   ├─> LLM Connector
   │   └─> Ollama (llama3.2)
   │   └─> Structured Output (TOON)
   │
   ├─> TOON Decoder
   │   └─> Parse Response
   │
   └─> Neo4j Connector
       └─> Import Nodes
       └─> Create Relationships
```

### Asynchrone Verarbeitung

```
HTTP Request
   │
   ├─> Controller
   │   └─> Dispatch Message
   │
   ├─> Redis Queue
   │
   └─> Worker (messenger:consume)
       └─> MessageHandler
           └─> Service Logic
```

## Entities & Schema

### IRREB Entities (Neo4j)

```cypher
// Requirements
(:Requirement {
  id, name, description,
  type, priority, status,
  source, rationale
})

// Roles
(:Role {
  id, name, description,
  level, department
})

// Environments
(:Environment {
  id, name, type,
  description, location
})

// Business
(:Business {
  id, name, goal,
  objective, kpis
})

// Infrastructure
(:Infrastructure {
  id, name, type,
  description, provider
})

// Software (schema.org)
(:SoftwareApplication {
  id, name, version,
  operatingSystem, category
})
```

### IRREB Relationships

```cypher
(:Requirement)-[:OWNED_BY]->(:Role)
(:Requirement)-[:APPLIES_TO]->(:Environment)
(:Requirement)-[:SUPPORTS]->(:Business)
(:Requirement)-[:DEPENDS_ON]->(:Infrastructure)
(:Requirement)-[:USES_SOFTWARE]->(:SoftwareApplication)
```

## Technologie-Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | Symfony | 7.3 |
| Language | PHP | 8.2+ |
| Queue | Redis | Latest |
| Database | Neo4j | Latest |
| LLM | Ollama (llama3.2) | Latest |
| Extractor | Apache Tika | 3.x |
| Testing | PHPUnit | 11.0 |

## Design Patterns

### 1. **Service Layer Pattern**
Services kapseln Business-Logik:
- `RequirementsExtractionService`
- `ToonFormatterService`
- `TokenChunker`

### 2. **Connector Pattern**
Connector abstrahieren externe Services:
- `TikaConnector`
- `LlmConnector`
- `Neo4JConnector`

### 3. **Message Queue Pattern**
Asynchrone Verarbeitung via Symfony Messenger:
- `RequirementsMessage`
- `RequirementsMessageHandler`

### 4. **DTO Pattern**
Data Transfer Objects für Type Safety:
- `RequirementDto`
- `RoleDto`
- `RequirementsGraphDto`

## Performance-Optimierungen

### 1. **Token-Chunking**
- Automatisch für große Dokumente (>4000 Tokens)
- Overlap zwischen Chunks
- Deduplizierung nach Merge

### 2. **TOON Format**
- 30-40% Token-Ersparnis vs. JSON
- Bessere LLM-Performance
- Fallback zu JSON

### 3. **Caching**
- Status-Cache (60s TTL)
- Config-Cache (300s TTL)
- Redis für Queue

### 4. **Asynchronous Processing**
- Message Queue für lange Tasks
- Worker-Prozesse skalierbar
- Non-blocking HTTP Requests

## Sicherheit

### Input Validation
- File-Type-Checking
- Path-Traversal-Prevention
- Size-Limits

### Environment Variables
- Sensitive Data in .env
- No hardcoded credentials
- Docker secrets support

### API Security
- CORS Configuration
- Rate Limiting (optional)
- Authentication (optional)

## Monitoring & Logging

### Logs
```bash
var/log/dev.log    # Development
var/log/prod.log   # Production
```

### Metrics
- Token-Counts per Request
- Processing Times
- Queue Depth
- Error Rates

### Health Checks
```bash
php bin/console app:status
```

## Skalierung

### Horizontal Scaling
- Multiple Worker-Prozesse
- Load-Balanced Web-Servers
- Redis Sentinel

### Vertical Scaling
- PHP OPcache
- Increased Memory Limits
- Faster Storage

## Deployment

### Docker Compose (Dev)
```bash
docker-compose up -d
```

### Production
- Kubernetes (optional)
- Docker Swarm (optional)
- Bare Metal + Supervisor

## Nächste Schritte

- [📋 Requirements Pipeline Details](requirements-pipeline.md)
- [🤖 LLM Integration](llm-integration.md)
- [⚡ TOON Format](../features/toon-format.md)

---

**Weitere Informationen:**
- [Getting Started](../getting-started/quickstart.md)
- [Development Guide](../development/)
- [Troubleshooting](../troubleshooting/)

