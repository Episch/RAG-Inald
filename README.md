# 🧠 RAGinald - Software Requirements Extraction

Eine **production-ready RAG-Pipeline** für **Software Requirements Extraction** basierend auf **Symfony**, **API Platform**, **Ollama LLM**, und **Neo4j** für intelligente Dokumentenverarbeitung und semantische Suche.

## 🎯 **Überblick**

Diese Anwendung extrahiert automatisch Software-Requirements aus Dokumenten und strukturiert sie nach **Schema.org Standards** (`SoftwareApplication` + `SoftwareRequirements`):

- 📄 **Intelligente Dokumenten-Extraktion** (Format Router + Native Parser für PDF/Excel/Word/Markdown)
- 🤖 **LLM-basierte Requirements-Analyse** (Ollama mit JSON Format)
- 📊 **Schema.org DTO Mapping** (SoftwareApplication/Requirements)
- 🔢 **Vektorisierung** (Ollama Embeddings)
- 🗄️ **Graph-Datenbank Speicherung** (Neo4j)
- 🔍 **Semantische Suche** in Requirements
- ⚡ **Asynchrone Verarbeitung** (Symfony Messenger + Redis Queue)
- 🔐 **JWT Authentication** (API Platform Security)
- 🚦 **Rate Limiting** (konfigurierbar per ENV)

---

## 🚀 **Quick Start**

### 🐳 **Option 1: Docker (Empfohlen)**

```bash
# 1. Services starten (Tika, Neo4j, Ollama)
docker-compose up -d

# 2. LLM-Modelle installieren (einmalig)
docker exec raginald_ollama ollama pull llama3.2
docker exec raginald_ollama ollama pull nomic-embed-text

# 3. JWT Keys generieren (einmalig)
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair

# 4. Database Setup
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Neo4j Indexes erstellen
php bin/console app:neo4j:init

# 6. API testen
curl http://localhost:8000/api/health
```

**Zugangsdaten:**
- **Neo4j Browser**: http://localhost:7474 (neo4j / password)
- **API**: http://localhost:8000/api
- **API Docs**: http://localhost:8000/api/docs

### ⚙️ **Option 2: Lokale Installation (WSL2/Linux)**

```bash
# 1. Dependencies installieren (bereits erledigt)
composer install

# 2. Services manuell starten
docker run -d -p 9998:9998 apache/tika              # Tika
docker run -d -p 7474:7474 -p 7687:7687 \           # Neo4j
  -e NEO4J_AUTH=neo4j/password neo4j:5.15
ollama serve                                         # Ollama (lokal)

# 3. Modelle installieren
ollama pull llama3.2
ollama pull nomic-embed-text

# 4. JWT Keys generieren
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair

# 5. Database Setup
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 6. Redis starten (für Message Queue)
docker run -d -p 6379:6379 redis:alpine

# 7. Message Worker starten (in separater Shell)
php bin/console messenger:consume async -vv

# 7. Development Server starten
symfony serve -d
# oder
php -S 0.0.0.0:8000 -t public
```

---

## ⚙️ **Konfiguration**

### **Environment Variablen**

Die wichtigsten Konfigurationsoptionen können über die `.env` Datei gesteuert werden:

#### **Rate Limiting**

```bash
# Rate Limiting aktivieren/deaktivieren
RATE_LIMIT_ENABLED=true          # true = aktiv, false = deaktiviert

# Anzahl erlaubter Requests
RATE_LIMIT_REQUESTS=3            # z.B. 3 Requests

# Zeitfenster für Rate Limit
RATE_LIMIT_INTERVAL="1 minute"   # z.B. "1 minute", "60 seconds", "5 minutes"
```

**Beispiele:**
- **Development**: `RATE_LIMIT_ENABLED=false` (deaktiviert)
- **Production**: `RATE_LIMIT_ENABLED=true`, `RATE_LIMIT_REQUESTS=100`, `RATE_LIMIT_INTERVAL="1 hour"`
- **Strict**: `RATE_LIMIT_ENABLED=true`, `RATE_LIMIT_REQUESTS=3`, `RATE_LIMIT_INTERVAL="1 minute"`

#### **Service URLs**

```bash
# LLM Service (Ollama)
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
OLLAMA_EMBEDDING_MODEL=nomic-embed-text

# Document Extractor (Tika)
DOCUMENT_EXTRACTOR_URL=http://localhost:9998

# Graph Database (Neo4j)
NEO4J_URL=bolt://localhost:7687
NEO4J_USER=neo4j
NEO4J_PASSWORD=password

# Message Queue (Redis)
REDIS_URL=redis://localhost:6379

# JWT Authentication
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase_here
```

**💡 Tipp**: Für Development kann Rate Limiting deaktiviert werden:
```bash
RATE_LIMIT_ENABLED=false
```

---

## 🔗 **API Endpunkte**

### **Übersicht**

| Kategorie | Endpunkt | Methode | Auth | Beschreibung |
|-----------|----------|---------|------|--------------|
| **Authentication** | `/api/login` | POST | ❌ | JWT Token erhalten |
| | `/api/token/refresh` | POST | ❌ | Token erneuern |
| | `/api/token/revoke` | POST | ❌ | Token widerrufen |
| | `/api/token/revoke-all` | POST | ✅ Admin | Alle Tokens widerrufen |
| **Requirements** | `/api/requirements/extract` | POST | ✅ | Dokument extrahieren |
| | `/api/requirements/search` | POST | ✅ | Semantische Suche |
| | `/api/requirements/jobs` | GET | ✅ | Jobs auflisten |
| | `/api/requirements/jobs/{id}` | GET | ✅ | Job-Status abrufen |
| **System** | `/api/health` | GET | ❌ | Health Check |
| | `/api/models` | GET | ❌ | Verfügbare LLM-Modelle |
| | `/api/docs` | GET | ❌ | OpenAPI Dokumentation |

### **1. Authentication**

```bash
# Login
curl -X POST https://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "password"
  }'

# Response:
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "abc123def456...",
  "user": {
    "email": "admin@example.com",
    "roles": ["ROLE_ADMIN", "ROLE_USER"]
  }
}
```

### **2. Requirements Extraction**

```bash
# Dokument extrahieren (Async)
curl -X POST https://localhost:8000/api/requirements/extract \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "projectName": "E-Commerce Platform",
    "serverPath": "/path/to/requirements.xlsx",
    "llmModel": "llama3.2",
    "temperature": 0.7,
    "async": true
  }'

# Response:
{
  "id": "019ab7a9-e7f2-7240-8fe4-8f9be45cc957",
  "status": "processing",
  "projectName": "E-Commerce Platform",
  "documentPath": "/path/to/requirements.xlsx",
  "createdAt": "2025-11-24T20:59:16+00:00",
  "metadata": {
    "llmModel": "llama3.2",
    "temperature": 0.7,
    "async": true
  }
}

# Job-Status prüfen
curl -X GET https://localhost:8000/api/requirements/jobs/{id} \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### **3. Semantic Search**

```bash
# Requirements durchsuchen
curl -X POST https://localhost:8000/api/requirements/search \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Show me all authentication and security requirements",
    "limit": 10,
    "minSimilarity": 0.7
  }'

# Response:
{
  "query": "authentication requirements",
  "results": [
    {
      "requirement": {
        "identifier": "REQ-001",
        "name": "User Authentication",
        "description": "System shall support email/password login",
        "requirementType": "functional",
        "priority": "must"
      },
      "similarity": 0.87
    }
  ],
  "count": 5,
  "duration_seconds": 0.234
}
```

### **4. System Status**

```bash
# Health Check (ohne Auth)
curl https://localhost:8000/api/health

# Verfügbare LLM-Modelle (ohne Auth)
curl https://localhost:8000/api/models
```

---

## 🏗️ **Architektur**

### **Requirements Extraction Pipeline**

```mermaid
graph TB
    A[Document Upload] --> B[Format Detection]
    B --> C{Format Router}
    C -->|PDF| D1[PDF Parser]
    C -->|Excel| D2[Excel Parser]
    C -->|Word| D3[Word Parser]
    C -->|Markdown| D4[Markdown Parser]
    C -->|Fallback| D5[Tika Universal]
    D1 & D2 & D3 & D4 & D5 --> E[Text Extraction]
    E --> F[LLM Analysis mit JSON]
    F --> G[Schema.org DTO Mapping]
    G --> H[Ollama Embeddings]
    H --> I[Neo4j Graph Storage]
    I --> J[Semantic Search Ready]
    
    K[Redis Queue] --> L[Async Processing]
    M[JWT Auth + Rate Limit] --> N[API Platform]
```

### **Document Extraction Router**

Intelligente Format-Erkennung und Parser-Selection:

1. **Format Detection** → MIME-Type via `symfony/mime`
2. **Parser Selection** → Best parser by priority
3. **Native Parsers** → PDF, Excel, Word, Markdown
4. **Fallback** → Apache Tika für alle anderen Formate

**Unterstützte Formate:**
- ✅ **PDF** (`smalot/pdfparser`)
- ✅ **Excel** (XLSX, XLS, CSV, ODS via `phpoffice/phpspreadsheet`)
- ✅ **Word** (DOCX, DOC, RTF, ODT via `phpoffice/phpword`)
- ✅ **Markdown** (Native PHP)
- ✅ **Plain Text** (TXT, HTML, XML, JSON)
- ✅ **Images** (OCR via Tesseract - optional)
- ✅ **Alle anderen** (Apache Tika Fallback)

### **JSON Format für LLM-Kommunikation**

Die Anwendung nutzt **strukturiertes JSON** für zuverlässige LLM-Kommunikation:

- ✅ **Standardisiert**: Universelles, weit verbreitetes Format
- ✅ **Zuverlässig**: Native PHP 8.3+ Unterstützung
- ✅ **Type-safe**: Starke Typisierung mit DTOs
- ✅ **Kompatibel**: Keine Drittanbieter-Abhängigkeiten

**Beispiel:**

```json
{
  "requirements": [
    {
      "identifier": "REQ-001",
      "name": "User Authentication",
      "description": "System shall support email/password login",
      "requirementType": "functional",
      "priority": "must",
      "tags": ["auth", "security"]
    },
    {
      "identifier": "REQ-002",
      "name": "Response Time",
      "description": "API responses within 200ms",
      "requirementType": "performance",
      "priority": "should",
      "tags": ["performance"]
    }
  ]
}
```

---

## 📊 **Schema.org DTOs**

### **SoftwareApplication**

```php
$application = new SoftwareApplicationDTO(
    name: 'My Software',
    description: 'Project description',
    requirements: [/* array of SoftwareRequirementsDTO */]
);
```

### **SoftwareRequirements**

```php
$requirement = new SoftwareRequirementsDTO(
    identifier: 'REQ-001',
    name: 'User Login',
    description: 'System shall allow users to log in',
    requirementType: 'functional', // functional, non-functional, technical, business, security, performance, usability
    priority: 'must' // must, should, could, wont (MoSCoW)
);
```

---

## ⚡ **Performance Features**

### **Optimierte LLM-Kommunikation**

- **Strukturiertes JSON**: Zuverlässiges, standardisiertes Format
- **Type-safe DTOs**: Schema.org konforme Datenstrukturen
- **Effiziente Prompts**: Klare Anweisungen für LLM-Antworten

### **Asynchrone Verarbeitung**

- **Message Queue** für schwere Operationen
- **Background Workers** via Symfony Messenger
- **Graceful Error Recovery**

### **Embeddings & Semantic Search**

- **Ollama Embeddings**: nomic-embed-text (768 dim), mxbai-embed-large (1024 dim)
- **Cosine Similarity** für semantische Suche
- **Neo4j Vector Storage** mit GDS Plugin

---

## 🛡️ **Security Features**

### **JWT Authentication**

- API Platform Security Integration
- Token-basierte Authentifizierung
- Role-based Access Control (RBAC)

### **Input Validation**

- Symfony Validator Constraints
- Path Traversal Protection
- Schema.org DTO Validation

---

## 📊 **IREB Graph-Struktur**

Das System nutzt die **IREB-Standards** (International Requirements Engineering Board) für Requirements Management in Neo4j.

### Node-Typ: SoftwareRequirement

**Labels**: `SoftwareRequirement`

**Core Properties** (immer vorhanden):
- `identifier`: Eindeutige ID (FR-001, SEC-002, etc.)
- `name`: Kurzer Titel
- `description`: Detaillierte Beschreibung
- `requirementType`: functional | security | performance | business | usability | non-functional
- `priority`: must | should | could | wont (MoSCoW)
- `status`: draft | proposed | approved | implemented | verified | rejected | obsolete
- `category`: Fachliche Kategorie (nie leer!)
- `tags`: Array von Keywords

**IREB Properties** (erweitert):
- `rationale`: WARUM existiert dieses Requirement?
- `acceptanceCriteria`: WIE wird es getestet?
- `verificationMethod`: test | inspection | analysis | demonstration | review
- `validationCriteria`: WIE wird validiert, dass es das richtige Problem löst?
- `source`: Herkunft (document, stakeholder, interview, etc.)
- `stakeholder`: Verantwortlicher Stakeholder
- `author`: Ersteller/Autor
- `involvedStakeholders`: Array aller Beteiligten
- `constraints`: Randbedingungen/Einschränkungen (Array)
- `assumptions`: Annahmen (Array)
- `dependencies`: Object mit `dependsOn`, `conflicts`, `extends` Arrays
- `risks`: Zugehörige Risiken (Array)
- `riskLevel`: low | medium | high | critical | none
- `estimatedEffort`: Geschätzter Aufwand
- `actualEffort`: Tatsächlicher Aufwand
- `traceabilityTo`: Rückverfolgbarkeit zu Business-Zielen
- `traceabilityFrom`: Vorwärts-Verfolgbarkeit zu Implementierungen
- `version`: Versionsnummer (z.B. "1.0")
- `embedding`: Vektor für semantische Suche (768 Dimensionen)
- `createdAt`: Zeitstempel Erstellung
- `updatedAt`: Zeitstempel letzte Änderung

### Relations (IREB-Graph)

**HAS_REQUIREMENT**: `SoftwareApplication → SoftwareRequirement`
- Verbindet Anwendungen mit ihren Requirements

**DEPENDS_ON**: `SoftwareRequirement → SoftwareRequirement`
- Ein Requirement hängt von einem anderen ab
- Properties: `type` (technical/functional/logical), `strength` (mandatory/optional)

**CONFLICTS_WITH**: `SoftwareRequirement → SoftwareRequirement`
- Ein Requirement steht im Konflikt mit einem anderen
- Properties: `severity` (high/medium/low), `resolved` (boolean), `reason`

**EXTENDS**: `SoftwareRequirement → SoftwareRequirement`
- Ein Requirement erweitert ein anderes
- Properties: `extensionType` (optional/alternative/enhancement)

**RELATED_TO**: `SoftwareRequirement → SoftwareRequirement`
- Generische Beziehung für alle anderen Verbindungen

### Graph-Queries (Beispiele)

**Dependency-Chain finden:**
```cypher
MATCH path = (r:SoftwareRequirement {identifier: 'FR-001'})-[:DEPENDS_ON*]->(dep)
RETURN path
```

**Konflikte identifizieren:**
```cypher
MATCH (r1:SoftwareRequirement)-[c:CONFLICTS_WITH {resolved: false}]->(r2)
RETURN r1.identifier, r2.identifier, c.severity, c.reason
ORDER BY c.severity DESC
```

**Kritische Requirements ohne Verifizierung:**
```cypher
MATCH (r:SoftwareRequirement {priority: 'must', status: 'approved'})
WHERE r.verificationMethod IS NULL
RETURN r.identifier, r.name
```

**Risk-Level Übersicht:**
```cypher
MATCH (r:SoftwareRequirement)
WHERE r.riskLevel <> 'none'
RETURN r.riskLevel, count(r) as count, collect(r.identifier) as requirements
ORDER BY count DESC
```

---

## 💡 **Best Practices**

### **1. Project Name Consistency**

**WICHTIG:** Der `projectName` wird für UPSERT-Logik verwendet. Verwende für ein Projekt **immer den gleichen Namen**, um Duplikate zu vermeiden:

✅ **Richtig:**
```bash
# Erste Extraktion
curl -X POST /api/requirements/extract -d '{"projectName": "BikeShop", ...}'

# Spätere Extraktionen (gleicher Name!)
curl -X POST /api/requirements/extract -d '{"projectName": "BikeShop", ...}'
```

❌ **Falsch:**
```bash
curl -X POST /api/requirements/extract -d '{"projectName": "BikeShop", ...}'
curl -X POST /api/requirements/extract -d '{"projectName": "Bike Shop", ...}'  # Anderer Name → neue Application!
```

**Hinweis:** Namen werden case-insensitive gematcht ("BikeShop" = "bikeshop" = "BIKESHOP").

---

### **2. Große Dokumente & LLM Limits**

Bei sehr großen Dokumenten (50+ Requirements) kann es sein, dass nicht alle Requirements in einem Durchlauf extrahiert werden:

**Symptome:**
- Log zeigt `requirements_count: 0` oder sehr wenige Requirements
- Warnung: "LLM response may be truncated"

**Lösung:**
1. **Größeres Modell verwenden** (mehr Token-Kapazität):
   ```bash
   docker exec raginald_ollama ollama pull llama3:70b  # Größeres Modell
   ```
2. **Dokument aufteilen**: Splitte große Dokumente in kleinere Kapitel
3. **Mehrfach extrahieren**: Führe die Extraktion mehrmals aus (UPSERT verhindert Duplikate)

**Konfiguration:**
```env
# Höheres Token-Limit (aber modell-abhängig!)
# Standard: 32768 Tokens für Response
```

---

### **3. Halluzination vermeiden**

Der LLM wurde instruiert, **NUR** Requirements zu extrahieren, die im Dokument stehen:

✅ **Gut**: Dokument enthält 8 Requirements → LLM extrahiert 8
❌ **Halluzination**: Dokument enthält 8 Requirements → LLM extrahiert 20 (erfundene)

**Aktueller Schutz:**
- Prompt betont: "ONLY extract EXPLICITLY stated requirements"
- Keine Annahmen über typische/übliche Requirements
- Strikte Bindung an Dokumenteninhalt

**Wenn Halluzination auftritt:**
- Prüfe Log: `LLM generation completed` → Vergleiche `document_length` mit `response_length`
- Senke `temperature` Parameter (0.1-0.3 statt 0.7)
- Verwende spezifischeren Prompt oder kleineres, präziseres Modell

---

## 🐛 **Troubleshooting**

### **LLM gibt keine Requirements zurück**

```bash
# 1. Modell prüfen
docker exec raginald_ollama ollama list

# 2. Modell installieren
docker exec raginald_ollama ollama pull llama3.2

# 3. Service-Status prüfen
curl http://localhost:8000/api/health
```

### **Message Queue läuft nicht**

```bash
# Redis Status prüfen
redis-cli ping  # Should return: PONG

# Worker-Status prüfen
php bin/console messenger:stats

# Failed messages anzeigen
php bin/console messenger:failed:show

# Worker manuell starten
php bin/console messenger:consume async -vv

# Redis Queue Cleanup (bei Problemen mit Consumer Groups)
php bin/console app:messenger:cleanup
```

### **Neo4j Connection Failed**

```bash
# Container prüfen
docker ps | grep neo4j

# Logs anzeigen
docker logs raginald_neo4j

# Browser öffnen
open http://localhost:7474
```

---

## 📚 **Technologie-Stack**

### **Core**
- **Framework**: Symfony 7.2 LTS
- **API**: API Platform 4.0
- **LLM**: Ollama (llama3.2, nomic-embed-text)
- **Format**: JSON (Strukturierte LLM-Kommunikation)
- **Graph DB**: Neo4j 5.15
- **Cache/Queue**: Redis (Refresh Tokens + Message Queue)

### **Document Processing**
- **Format Detection**: Symfony MIME Component
- **PDF Parser**: smalot/pdfparser
- **Excel Parser**: phpoffice/phpspreadsheet
- **Word Parser**: phpoffice/phpword
- **Fallback**: Apache Tika

### **Security & Performance**
- **Auth**: Lexik JWT Authentication
- **Rate Limiting**: Symfony Rate Limiter (konfigurierbar)
- **Queue**: Symfony Messenger + Redis Streams

---

## 🤝 **Contributing**

Das System folgt **Symfony Best Practices**, **API Platform Patterns**, und **Schema.org Standards**:

1. **Interface-basierte Services**
2. **State Processors/Providers** (API Platform)
3. **Message Handlers** für Async Logic
4. **DTO-basierte Validierung**
5. **JSON Format** für LLM-Kommunikation

---

## 📈 **Roadmap**

- [ ] Doctrine Entity Persistence für Jobs
- [ ] Redis Cache Layer für Production
- [ ] GraphQL API Support
- [ ] Real-time WebSocket Updates
- [ ] Multiple Document Formats (CSV, Excel, etc.)
- [ ] Advanced Neo4j Queries (Cypher DSL)
- [ ] CI/CD Pipeline (GitHub Actions)

---

**Eine vollständig optimierte, production-ready RAG-Pipeline für Software Requirements Extraction! 🚀**

*Entwickelt mit Symfony 7.2 LTS, API Platform 4.0, und Schema.org Standards.*

