# 📚 Usage Guide

## Grundlegende Nutzung

### 1. Login und JWT Token

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "admin123"
  }'
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

Speichere den Token für weitere Anfragen:

```bash
export TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."
```

### 2. Requirements aus Dokument extrahieren

```bash
curl -X POST http://localhost:8000/api/requirements/extract \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "documentPath": "/path/to/requirements.pdf",
    "projectName": "My Project",
    "extractionOptions": {
      "llmModel": "llama3.2",
      "temperature": 0.7,
      "async": true
    }
  }'
```

**Response:**
```json
{
  "id": "01932c8e-7b4a-7890-a123-456789abcdef",
  "status": "processing",
  "documentPath": "/path/to/requirements.pdf",
  "projectName": "My Project",
  "createdAt": "2025-01-15T10:30:00+00:00",
  "metadata": {}
}
```

### 3. Job-Status abfragen

```bash
JOB_ID="01932c8e-7b4a-7890-a123-456789abcdef"

curl -X GET "http://localhost:8000/api/requirements/jobs/$JOB_ID" \
  -H "Authorization: Bearer $TOKEN"
```

**Response (während Verarbeitung):**
```json
{
  "id": "01932c8e-7b4a-7890-a123-456789abcdef",
  "status": "processing",
  "documentPath": "/path/to/requirements.pdf",
  "projectName": "My Project",
  "createdAt": "2025-01-15T10:30:00+00:00"
}
```

**Response (abgeschlossen):**
```json
{
  "id": "01932c8e-7b4a-7890-a123-456789abcdef",
  "status": "completed",
  "documentPath": "/path/to/requirements.pdf",
  "projectName": "My Project",
  "result": {
    "name": "My Project",
    "requirements": [
      {
        "identifier": "REQ-001",
        "name": "User Login",
        "description": "System shall allow users to log in",
        "requirementType": "functional",
        "priority": "must",
        "tags": ["authentication", "security"]
      }
    ]
  },
  "neo4jNodeId": "4:abc123:456",
  "createdAt": "2025-01-15T10:30:00+00:00",
  "completedAt": "2025-01-15T10:31:30+00:00"
}
```

## Extraction Options

### LLM Models

Verfügbare Modelle prüfen:

```bash
curl http://localhost:8000/api/models
```

**Empfohlene Modelle:**
- `llama3.2` - Beste Balance zwischen Qualität und Geschwindigkeit
- `mistral` - Schneller, gute Qualität
- `llama3.2:70b` - Höchste Qualität (benötigt mehr RAM/GPU)

### Temperature

Steuert Kreativität der LLM-Antworten:
- `0.0` - Deterministisch, konsistent
- `0.7` - Empfohlen für Requirements (Standard)
- `1.0` - Mehr Varianz

### Async vs Sync

```json
{
  "extractionOptions": {
    "async": true  // Asynchrone Verarbeitung (empfohlen)
  }
}
```

- `async: true` - Job wird in Message Queue verarbeitet, Response sofort
- `async: false` - Synchrone Verarbeitung (nur für Tests)

## TOON Format Beispiele

Die API nutzt intern **TOON Format** für LLM-Kommunikation. Beispiel-Output:

```toon
requirements[5]:
  - identifier: REQ-001
    name: User Authentication
    description: Users shall authenticate using email and password
    requirementType: functional
    priority: must
    category: Security
    tags[2]: auth, login
  - identifier: REQ-002
    name: Response Time
    description: API responses within 200ms
    requirementType: performance
    priority: should
    tags[1]: performance
```

**Token-Ersparnis:**
- JSON: ~450 Tokens
- TOON: ~225 Tokens
- **Ersparnis: 50%** 🚀

## Neo4j Queries

### Requirements abfragen

```cypher
// Alle Requirements eines Projekts
MATCH (app:SoftwareApplication {name: "My Project"})-[:HAS_REQUIREMENT]->(req:Requirement)
RETURN req

// Requirements nach Typ
MATCH (req:Requirement {requirementType: "functional"})
RETURN req.identifier, req.name, req.description

// Requirements nach Priority (MoSCoW)
MATCH (req:Requirement {priority: "must"})
RETURN req
```

### Semantische Suche

```cypher
// Ähnliche Requirements finden (mit Embedding)
MATCH (req:Requirement)
WHERE req.embedding IS NOT NULL
WITH req, gds.similarity.cosine(req.embedding, $queryEmbedding) AS similarity
WHERE similarity > 0.8
RETURN req, similarity
ORDER BY similarity DESC
LIMIT 10
```

## Console Commands

### Neo4j initialisieren

```bash
php bin/console app:neo4j:init
```

### Pipeline testen

```bash
# Mit Testdokument
php bin/console app:test:extraction tests/SampleRequirements.md

# Ohne Dokument (nur Services prüfen)
php bin/console app:test:extraction
```

### Message Queue

```bash
# Worker starten (Development)
php bin/console messenger:consume async -vv

# Queue-Status prüfen
php bin/console messenger:stats

# Failed Messages anzeigen
php bin/console messenger:failed:show

# Failed Message erneut versuchen
php bin/console messenger:failed:retry
```

## API Platform Features

### OpenAPI Documentation

Öffne http://localhost:8000/api/docs für interaktive API-Dokumentation (Swagger UI).

### Pagination

```bash
curl "http://localhost:8000/api/requirements/jobs?page=1&itemsPerPage=30" \
  -H "Authorization: Bearer $TOKEN"
```

### Filtering (zukünftig)

```bash
curl "http://localhost:8000/api/requirements/jobs?status=completed" \
  -H "Authorization: Bearer $TOKEN"
```

## Production Deployment

### Environment Variables

Für Production in `.env.local` oder `.env.prod.local`:

```bash
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL="postgresql://user:pass@localhost/raginald"
MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages
```

### Worker als Systemd Service

```bash
sudo nano /etc/systemd/system/raginald-worker.service
```

```ini
[Unit]
Description=RAGinald Message Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/raginald
ExecStart=/usr/bin/php /var/www/raginald/bin/console messenger:consume async --time-limit=3600 --memory-limit=512M --env=prod
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable raginald-worker
sudo systemctl start raginald-worker
sudo systemctl status raginald-worker
```

## Monitoring

### Logs

```bash
# Application Logs
tail -f var/log/prod.log

# Message Queue Logs
tail -f var/log/messenger.log

# Docker Logs
docker-compose logs -f ollama
docker-compose logs -f neo4j
```

### Health Checks

```bash
# API Health
curl http://localhost:8000/api/health

# Neo4j
curl http://localhost:7474

# Ollama
curl http://localhost:11434/api/version
```

## Troubleshooting

### Job stuck in "processing"

```bash
# Worker läuft?
ps aux | grep messenger:consume

# Queue-Status
php bin/console messenger:stats

# Worker neu starten
pkill -f messenger:consume
php bin/console messenger:consume async -vv
```

### Keine Requirements extrahiert

1. **LLM Logs prüfen**: `docker logs raginald_ollama`
2. **Prompt anpassen** in `ExtractRequirementsHandler`
3. **Anderes Modell** testen: `mistral` statt `llama3.2`

### Neo4j Connection Error

```bash
# Container läuft?
docker ps | grep neo4j

# Credentials prüfen
docker exec -it raginald_neo4j cypher-shell -u neo4j -p password

# Neu starten
docker-compose restart neo4j
```

