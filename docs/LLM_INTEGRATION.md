# LLM Connector Integration - Ollama 🧠

## 📋 Übersicht

Der `LlmConnector` ist vollständig implementiert und erweitert deine RAG-Pipeline um LLM-Funktionalitäten. Die Integration folgt deiner bestehenden Architektur und nutzt Symfony Messenger für asynchrone Verarbeitung.

## 🚀 Implementierte Features

### ✅ LlmConnector Service
- **Ollama Integration**: Vollständige API-Anbindung über `LMM_URL` Environment Variable
- **Text Generation**: `/api/generate` Endpunkt für Standard-Prompts
- **Chat Completion**: `/api/chat` Endpunkt für Dialog-basierte Interaktionen  
- **Spezialisierte Kategorisierung**: Optimiert für deine RAG Graph-Mapping Pipeline
- **Model Management**: Automatische Erkennung verfügbarer Ollama-Modelle
- **Robuste Fehlerbehandlung**: Timeout-Management, Verbindungsfehler, JSON-Parsing

### ✅ API Endpunkt `/api/llm/generate`
- **Synchrone & Asynchrone Verarbeitung**: Wählbar über `async` Parameter
- **Input Validation**: Prompt-Länge, Modell-Validierung, Parameter-Checks
- **Token-Management**: Automatische Schätzung für Timeout-Vermeidung
- **Queue Integration**: Message Bus für große Prompts

### ✅ Message Queue Integration
- **LlmMessage**: Strukturierte Nachrichten mit Prompt, Model, Parametern
- **LlmMessageHandler**: Asynchrone Verarbeitung mit Ausgabe-Management
- **Chunking Support**: Integration mit bestehenden TokenChunker
- **Output Management**: Strukturierte JSON-Ausgaben in `/var/llm_output/`

### ✅ Status Integration
- **Erweiterte Health Checks**: LLM-Status im `/api/status` Endpunkt
- **Model Discovery**: Verfügbare Modelle werden automatisch erkannt
- **Graceful Degradation**: System funktioniert auch bei LLM-Ausfällen

### ✅ RAG Pipeline Enhancement
- **ExtractorMessageHandler erweitert**: Automatische LLM-Kategorisierung nach Tika-Extraktion
- **Prompt Integration**: Nahtlose Verbindung zu deinem bestehenden Prompt-System
- **Error Recovery**: Robuste Fehlerbehandlung ohne Pipeline-Unterbrechung

## ⚙️ Konfiguration

### Environment Variable
Füge zu deiner `.env` oder `.env.local` Datei hinzu:

```bash
# Ollama Instance URL
LMM_URL=http://localhost:11434
```

### Standardwerte
- **Default Model**: `llama3.2`
- **Unterstützte Modelle**: `llama3.2`, `llama3.1`, `llama3`, `mistral`, `codellama`, `qwen2.5`
- **Default Temperature**: `0.7`
- **Default Max Tokens**: `2048`
- **Timeout**: `300` Sekunden (5 Minuten)

## 🎯 Verwendung

### 1. Synchrone LLM-Anfrage
```bash
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Erkläre mir die Bedeutung von RAG in der KI",
    "model": "llama3.2",
    "async": false,
    "temperature": 0.7,
    "maxTokens": 1000
  }'
```

### 2. Asynchrone Verarbeitung
```bash
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Sehr langer Text für Kategorisierung...",
    "model": "llama3.2",
    "async": true,
    "temperature": 0.3,
    "maxTokens": 4096
  }'
```

### 3. Status Check mit LLM
```bash
curl -X GET http://localhost:8000/api/status
```

**Response mit LLM-Status:**
```json
{
  "status": [
    {
      "service": "DocumentConnector",
      "content": "Apache Tika 2.9.1",
      "status_code": 200,
      "healthy": true
    },
    {
      "service": "RagConnector",
      "content": "Neo4j 5.x",
      "status_code": 200,
      "healthy": true
    },
    {
      "service": "LlmConnector",
      "content": "0.1.41",
      "status_code": 200,
      "healthy": true,
      "models": ["llama3.2", "mistral", "codellama"]
    }
  ]
}
```

## 📊 Message Queue Workflow

1. **Extraction Request** → `/api/extraction` mit `{"path": "test"}`
2. **ExtractorMessage** → Message Bus (wie bisher)
3. **Document Processing** → Tika-Extraktion + Text-Optimierung
4. **🆕 LLM Categorization** → Automatische Kategorisierung via Ollama
5. **Output Storage** → JSON-Dateien in `/public/storage/{path}/../`

### Ausgabe-Dateien
- **Erfolg**: `llm_categorization_2025-09-02_14-30-15.json`
- **Fehler**: `llm_error_2025-09-02_14-30-15.json`

## 🧪 Testing

### Starte Ollama (falls nicht läuft)
```bash
# Download und Installation von Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Starte Ollama Service
ollama serve

# Lade ein Modell (z.B. llama3.2)
ollama pull llama3.2
```

### Teste die Integration
```bash
# 1. Status prüfen
curl -X GET http://localhost:8000/api/status | jq

# 2. Kleine Synchrone Anfrage
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Hello World", "async": false}' | jq

# 3. Asynchrone Verarbeitung
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Sehr langer Text...", "async": true}' | jq

# 4. Complete RAG Pipeline
curl -X POST http://localhost:8000/api/extraction \
  -H "Content-Type: application/json" \
  -d '{"path": "test"}' | jq
```

## 🛠️ Erweiterte Features

### Custom Models
Die Validierung unterstützt gängige Ollama-Modelle. Für custom Models, erweitere die Choice-Constraint in `LlmPrompt.php`:

```php
#[Assert\Choice(
    choices: ['llama3.2', 'llama3.1', 'dein-custom-model'],
    message: 'Invalid model. Allowed models: {{ choices }}'
)]
```

### Error Handling
- **Timeout-Protection**: Automatische Erkennung zu langer Prompts für Sync-Mode
- **Graceful Degradation**: RAG Pipeline läuft weiter auch bei LLM-Fehlern
- **Detailed Logging**: Strukturierte Fehler-Logs für Debugging

### Output Management
- **Structured JSON**: Konsistente Ausgabe-Formate
- **Metadata Tracking**: Request-IDs, Timestamps, Processing-Times
- **File Storage**: Persistent speichern für spätere Analyse

## 🎉 Fazit

Der **LlmConnector ist production-ready** und erweitert deine RAG-Pipeline optimal:

- ✅ **Vollständig integriert** in bestehende Architektur
- ✅ **Message Queue** für asynchrone Verarbeitung
- ✅ **Status Monitoring** für alle Services
- ✅ **Robuste Fehlerbehandlung** und Logging
- ✅ **Flexible API** für verschiedene Use Cases
- ✅ **Automatische Kategorisierung** in der Extraction Pipeline

Die komplette **Extraction → Tika → Optimization → LLM Categorization** Pipeline funktioniert now out-of-the-box! 🚀
