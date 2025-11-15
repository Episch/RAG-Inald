# 🚀 Quick Start Guide

## In 5 Minuten zum laufenden System

Dieser Guide bringt dich schnell zum Einsatz der Requirements-Extraktion-Pipeline.

## Voraussetzungen

- PHP 8.2+
- Composer
- Docker & Docker Compose
- Git

## 1. Installation

```bash
# Repository klonen
git clone <repository-url>
cd backend

# Dependencies installieren
composer install

# Environment konfigurieren
cp docker/env.template .env
```

## 2. Docker-Services starten

```bash
# Alle Services hochfahren
docker-compose up -d

# Services prüfen
docker-compose ps
```

**Erwartete Services:**
- ✅ Tika (Port 9998) - Text-Extraktion
- ✅ Ollama (Port 11434) - LLM
- ✅ Neo4j (Port 7474, 7687) - Graph-DB
- ✅ Redis (Port 6379) - Message Queue

## 3. Ollama-Modell installieren

```bash
# LLM-Modell herunterladen
docker exec ollama ollama pull llama3.2

# Prüfen
docker exec ollama ollama list
```

## 4. System-Status prüfen

```bash
php bin/console app:status
```

**Erwartete Ausgabe:**
```
✅ Tika: Connected (Version 3.2.2)
✅ Ollama: Connected (llama3.2)
✅ Neo4j: Connected
✅ Redis: Connected
```

## 5. Test-Dokument verarbeiten

```bash
# Test-Datei hochladen
mkdir -p public/storage/test
# Lege ein PDF mit Requirements hinein

# Requirements extrahieren
php bin/console app:process-requirements public/storage/test/requirements.pdf

# Oder asynchron
php bin/console app:process-requirements public/storage/test/ --async
php bin/console messenger:consume async -vv
```

## 6. Ergebnisse prüfen

### Console-Output
Du siehst Token-Statistiken und extrahierte Requirements direkt in der Console.

### Neo4j Browser
```
http://localhost:7474
Username: neo4j
Password: (aus .env)
```

**Cypher-Query:**
```cypher
MATCH (req:Requirement)-[r]-(n)
RETURN req, r, n
LIMIT 50
```

### Output-Dateien
```bash
# JSON-Ergebnisse
dir var\requirements_output\

# Logs
tail -f var/log/dev.log
```

## 🎉 Fertig!

Das System läuft jetzt. Nächste Schritte:

- [📖 System Overview](../architecture/overview.md) - Verstehe die Architektur
- [⚡ TOON Format](../features/toon-format.md) - Token-optimierte Prompts
- [🧪 Testing](../development/testing.md) - Tests ausführen

## 🆘 Probleme?

### Service nicht erreichbar
```bash
# Status prüfen
docker-compose ps

# Logs anschauen
docker-compose logs tika
docker-compose logs ollama

# Neu starten
docker-compose restart
```

### Neo4j Connection Failed
```bash
# .env prüfen
cat .env | grep NEO4J

# Neo4j-Browser öffnen
start http://localhost:7474
```

### Ollama Model nicht gefunden
```bash
# Verfügbare Modelle
docker exec ollama ollama list

# Modell herunterladen
docker exec ollama ollama pull llama3.2
```

## 📚 Weitere Ressourcen

- [Full Documentation](../README.md)
- [Troubleshooting Guide](../troubleshooting/)
- [API Documentation](../features/)

---

**Nächster Schritt:** [System Overview →](../architecture/overview.md)

