# 🤖 LLM Integration - Ollama

Vollständige Dokumentation der Ollama-LLM-Integration für die Requirements-Pipeline.

## 📋 Übersicht

Der `LlmConnector` integriert **Ollama** (lokales LLM) in die Symfony-Pipeline für:
- Requirements-Extraktion
- Text-Generation
- Chat-Completion
- Strukturierte JSON-Outputs

## 🏗️ Architektur

```
Symfony Application
   │
   ├─> LlmConnector Service
   │   └─> HTTP Client
   │       └─> Ollama Docker (:11434)
   │           └─> llama3.2 Model
   │
   ├─> TokenChunker
   │   └─> Token Counting
   │   └─> Chunking Strategy
   │
   └─> ToonFormatterService
       └─> Prompt Optimization
```

## ⚙️ Konfiguration

### Environment Variables

```.env
# Ollama URL
LMM_URL=http://ollama:11434

# Default Model
DEFAULT_LLM_MODEL=llama3.2
```

### Docker Setup

```bash
# Ollama Container starten
docker run -d -p 11434:11434 ollama/ollama:latest

# Modell herunterladen
docker exec ollama ollama pull llama3.2

# Verfügbare Modelle
docker exec ollama ollama list
```

## 🔧 LlmConnector API

### Text Generation

```php
use App\Service\Connector\LlmConnector;

// Generate Text
$response = $llmConnector->generateText(
    prompt: 'Extract requirements from: ...',
    model: 'llama3.2',
    options: [
        'temperature' => 0.3,
        'num_predict' => 2048
    ]
);

$json = json_decode($response->getContent(), true);
$text = $json['response'];
```

### Chat Completion

```php
// Multi-turn Chat
$response = $llmConnector->chatCompletion(
    messages: [
        ['role' => 'system', 'content' => 'You are a requirements engineer...'],
        ['role' => 'user', 'content' => 'Extract requirements from...']
    ],
    model: 'llama3.2',
    options: ['temperature' => 0.3]
);
```

### Spezialisierte Kategorisierung

```php
// Optimiert für RAG-Pipeline
$response = $llmConnector->promptForCategorization(
    prompt: 'Document text...',
    model: 'llama3.2'
);
```

## 📊 Token-Management

### Token-Counting

```php
use App\Service\TokenChunker;

// Count Tokens
$tokenCount = $tokenChunker->countTokens($text, 'llama3.2');

// Check if Chunking needed
if ($tokenCount > SystemConstants::TOKEN_SYNC_LIMIT) {
    // Use chunking strategy
}
```

### Automatisches Chunking

Große Prompts werden automatisch gechunked:

```php
// Chunk Text
$chunks = $tokenChunker->chunk($largeText, 'llama3.2');

// Process each chunk
foreach ($chunks as $chunk) {
    $response = $llmConnector->generateText($chunk, 'llama3.2');
    // Merge results...
}
```

## 🎯 TOON-Format Prompts

Die Pipeline nutzt **TOON-Format** für optimierte Prompts:

```php
// System Prompt mit TOON
$systemPrompt = <<<PROMPT
Du bist ein Requirements-Experte.

Antworte im TOON-Format:

```toon
requirements[N]{id,name,type,priority}:
  REQ-001,Login,functional,high
  REQ-002,Logout,functional,medium
```

Verwende KEINE zusätzlichen Erklärungen.
PROMPT;

$response = $llmConnector->chatCompletion([
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $document]
], 'llama3.2');
```

**Siehe:** [TOON Format Details](../features/toon-format.md)

## 📡 HTTP API

### POST /api/llm/generate

```bash
curl -X POST http://localhost/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Extract requirements from...",
    "model": "llama3.2",
    "temperature": 0.3,
    "async": false
  }'
```

**Response:**
```json
{
  "success": true,
  "response": "...",
  "model": "llama3.2",
  "processing_time": "5.23s"
}
```

### Asynchrone Verarbeitung

```bash
curl -X POST http://localhost/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Large document...",
    "async": true
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "LLM request queued",
  "request_id": "llm_12345",
  "estimated_time": "30 seconds"
}
```

## 🔄 Message Queue Integration

### LlmMessage

```php
use App\Message\LlmMessage;

$message = new LlmMessage(
    prompt: $text,
    model: 'llama3.2',
    temperature: 0.3,
    maxTokens: 2048,
    requestId: uniqid('llm_'),
    type: 'generate',
    saveAsFile: true
);

$this->messageBus->dispatch($message);
```

### LlmMessageHandler

Verarbeitet Messages asynchron:
- Token-Counting
- Chunking (falls nötig)
- LLM-Request
- Output-Speicherung

```bash
# Worker starten
php bin/console messenger:consume async -vv
```

## 📈 Performance-Optimierung

### 1. Temperature Settings

```php
// Strukturierte Outputs (Requirements)
'temperature' => 0.2  // Sehr konsistent

// Kreative Texte
'temperature' => 0.7  // Mehr Varianz
```

### 2. Token-Limits

```php
'num_predict' => 2048   // Standard
'num_predict' => 4096   // Große Outputs
'num_predict' => 8192   // Sehr große Outputs
```

### 3. Timeout-Management

```php
// LlmConnector.php
'timeout' => 300  // 5 Minuten für lange Generierungen
```

## 🔍 Health Checks

### Status prüfen

```bash
# System-Status
php bin/console app:status

# LLM-spezifisch
curl http://localhost:11434/api/version
```

### Verfügbare Modelle

```php
$models = $llmConnector->getModels();
$modelNames = array_map(fn($m) => $m['name'], $models);
```

## 🐛 Debugging

### LLM-Logs

```bash
# Symfony Logs
tail -f var/log/dev.log | grep LLM

# Ollama Logs
docker logs ollama
```

### Debug-Endpoint

```bash
# Prüfe verfügbare Endpoints
curl http://localhost/api/debug/llm/endpoints
```

**Output:**
```json
{
  "version": {"available": true, "status": 200},
  "tags": {"available": true, "status": 200},
  "generate": {"available": true, "status": 400},
  "chat": {"available": true, "status": 400}
}
```

## 🧪 Testing

### Unit-Tests

```php
// Mock LLM Connector
$mock = $this->createMock(LlmConnector::class);
$mock->expects($this->once())
    ->method('generateText')
    ->willReturn($mockResponse);
```

### Integration-Tests

```bash
# Test mit echtem LLM
php bin/console app:process-requirements tests/fixtures/sample.pdf
```

## 🔐 Sicherheit

### Input Validation

```php
// Prompt-Länge limitieren
if (strlen($prompt) > 100000) {
    throw new \InvalidArgumentException('Prompt too long');
}

// Model-Whitelist
$allowedModels = ['llama3.2', 'llama3.2:7b', 'mistral'];
if (!in_array($model, $allowedModels)) {
    throw new \InvalidArgumentException('Invalid model');
}
```

### Rate Limiting (optional)

```php
// In Controller
use Symfony\Component\RateLimiter\RateLimiterFactory;

$limiter = $this->limiterFactory->create($request->getClientIp());
if (!$limiter->consume(1)->isAccepted()) {
    throw new TooManyRequestsHttpException();
}
```

## 💰 Cost Estimation

Ollama ist **kostenlos** (lokal), aber für Vergleich mit Cloud-LLMs:

| Metric | Ollama | OpenAI GPT-4 |
|--------|--------|--------------|
| Input (1K tokens) | $0.00 | $0.03 |
| Output (1K tokens) | $0.00 | $0.06 |
| Latency | ~2-5s | ~1-3s |
| Privacy | ✅ Lokal | ❌ Cloud |

## 📚 Weiterführende Dokumentation

- [Requirements Pipeline](requirements-pipeline.md) - Vollständige Pipeline
- [TOON Format](../features/toon-format.md) - Token-Optimierung
- [Ollama Debug](../troubleshooting/ollama-debug.md) - Fehlersuche

---

**Siehe auch:**
- [Quick Start](../getting-started/quickstart.md)
- [System Overview](overview.md)
- [Testing](../development/testing.md)

