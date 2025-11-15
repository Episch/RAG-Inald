# 📚 Dokumentations-Übersicht

Willkommen zur **Backend-Dokumentation** für die Requirements-Extraktion-Pipeline.

## 🚀 Schnellstart

**Neu hier?** Starte hier:

1. **[Quick Start Guide](getting-started/quickstart.md)** - In 5 Minuten zur laufenden Pipeline
2. **[System Overview](architecture/overview.md)** - Architektur-Überblick
3. **[Requirements Pipeline](architecture/requirements-pipeline.md)** - Pipeline-Details

## 📂 Dokumentations-Struktur

### 🏁 Getting Started
**Für Einsteiger und Quick-Setup**

- [**Quick Start Guide**](getting-started/quickstart.md) - 5-Minuten Setup
- *(In Planung: Installation Guide, Configuration Guide)*

### 🏗️ Architecture
**System-Architektur und Design**

- [**System Overview**](architecture/overview.md) - Gesamtarchitektur
- [**Requirements Pipeline**](architecture/requirements-pipeline.md) - IRREB + schema.org Pipeline
- [**LLM Integration**](architecture/llm-integration.md) - Ollama LLM Details

### ⚡ Features
**API-Dokumentation und Features**

- [**TOON Format**](features/toon-format.md) - Token-optimierte Prompts (30-40% Ersparnis)
- [**File Storage API**](features/file-storage-api.md) - File-Management
- [**Admin Endpoints**](features/admin-endpoints.md) - System-Administration

### 👨‍💻 Development
**Für Entwickler**

- [**Testing Guide**](development/testing.md) - Unit-Tests, Integration-Tests
- [**Code Optimization**](development/code-optimization.md) - Best Practices

### 🐛 Troubleshooting
**Fehlerbehebung und Debugging**

- [**Redis Monitoring**](troubleshooting/redis-monitoring.md) - Queue-Monitoring
- [**Queue Debugging**](troubleshooting/queue-debugging.md) - Message-Queue Debug
- [**Ollama Debug**](troubleshooting/ollama-debug.md) - LLM-Probleme lösen
- [**Indexing Issues**](troubleshooting/indexing-issues.md) - Neo4j-Indexierung

## 🔗 Quick Links

### Häufige Aufgaben

```bash
# System starten
docker-compose up -d

# Requirements extrahieren
php bin/console app:process-requirements path/to/file.pdf

# Tests ausführen
php bin/phpunit

# Status prüfen
php bin/console app:status
```

### Wichtige Endpunkte

| Endpunkt | Beschreibung |
|----------|--------------|
| `/api/status` | System-Health-Check |
| `/api/llm/generate` | LLM-Text-Generierung |
| `/api/extraction` | Dokument-Verarbeitung |

## 📊 Pipeline-Übersicht

```
Documents → Tika → Token Chunker → LLM (TOON) → Neo4j
             ↓         ↓              ↓            ↓
          Plain    Chunks         JSON       Graph DB
          Text
```

**Siehe:** [Requirements Pipeline](architecture/requirements-pipeline.md)

## 🎯 Hauptfeatures

- ✅ **Automatische Requirements-Extraktion** aus PDF/Excel
- ✅ **IRREB + schema.org** konforme Datenstruktur
- ✅ **TOON-Format** für 30-40% Token-Ersparnis
- ✅ **Token-Chunking** für große Dokumente
- ✅ **Neo4j Graph-Import** mit Relationships
- ✅ **Asynchrone Verarbeitung** via Message Queue
- ✅ **Umfassende Tests** (Unit, Integration)

## 💡 Technologie-Stack

| Komponente | Technologie | Version |
|------------|-------------|---------|
| Framework | Symfony | 7.3 |
| Language | PHP | 8.2+ |
| LLM | Ollama (llama3.2) | Latest |
| Extractor | Apache Tika | 3.x |
| Database | Neo4j | Latest |
| Queue | Redis | Latest |
| Testing | PHPUnit | 11.0 |

## 🆘 Hilfe & Support

### Problem-Lösung

1. **Prüfe System-Status:**
   ```bash
   php bin/console app:status
   ```

2. **Konsultiere Troubleshooting:**
   - [Ollama Debug](troubleshooting/ollama-debug.md) - LLM-Probleme
   - [Queue Debugging](troubleshooting/queue-debugging.md) - Queue-Probleme
   - [Redis Monitoring](troubleshooting/redis-monitoring.md) - Redis-Probleme

3. **Logs prüfen:**
   ```bash
   tail -f var/log/dev.log
   ```

### Häufige Probleme

| Problem | Lösung |
|---------|--------|
| LLM gibt 404 | [Ollama Debug Guide](troubleshooting/ollama-debug.md) |
| Queue läuft nicht | [Queue Debugging](troubleshooting/queue-debugging.md) |
| Neo4j-Import fehlschlägt | [Indexing Issues](troubleshooting/indexing-issues.md) |

## 📝 Dokumentations-Update

Die Dokumentation wurde am **2025-11-15** reorganisiert.

**Siehe:** [DOCS_REORGANIZATION.md](../DOCS_REORGANIZATION.md) für Details zur neuen Struktur.

## 🤝 Contributing

Wenn du zur Dokumentation beitragen möchtest:
1. Behalte die bestehende Struktur bei
2. Verwende konsistente Markdown-Formatierung
3. Füge Code-Beispiele hinzu wo sinnvoll
4. Update die README.md in diesem Ordner

## 📚 Weiterführende Links

- [**Haupt-README**](../README.md) - Projekt-Übersicht
- [**Docker Setup**](../docker/README.md) - Docker-Konfiguration
- [**Tests README**](../tests/README.md) - Test-Dokumentation

---

**Viel Erfolg mit der Pipeline!** 🚀

*Bei Fragen oder Problemen: Konsultiere die entsprechende Sektion oder prüfe die Troubleshooting-Guides.*
