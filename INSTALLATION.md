# 📦 Installation Guide

## Voraussetzungen

- **PHP**: 8.2+
- **Composer**: 2.x
- **Docker**: 20.x+ (für Services)
- **WSL2**: (wenn unter Windows)

## 🚀 Automatische Installation (Empfohlen)

```bash
# Setup-Script ausführen
chmod +x setup.sh
./setup.sh
```

Das Script führt automatisch aus:
1. ✅ Composer Dependencies installieren
2. ✅ JWT Keys generieren
3. ✅ Database Setup
4. ✅ Docker Services starten (Tika, Neo4j, Ollama)
5. ✅ LLM-Modelle herunterladen
6. ✅ Neo4j initialisieren
7. ✅ Services testen

## 🛠️ Manuelle Installation

### 1. Composer Dependencies

```bash
composer install
```

### 2. Umgebungsvariablen

```bash
# .env.local erstellen
cp .env.local.example .env.local

# Werte anpassen (optional)
nano .env.local
```

### 3. JWT Keys generieren

```bash
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair

# Passphrase: Default oder eigene wählen
```

### 4. Database Setup

```bash
# Datenbank erstellen
php bin/console doctrine:database:create

# Migrations ausführen
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Docker Services starten

```bash
# Nur externe Services (Tika, Neo4j, Ollama)
docker-compose up -d tika neo4j ollama

# Services prüfen
docker-compose ps
```

### 6. LLM-Modelle installieren

```bash
# LLama 3.2 (für Requirements Extraction)
docker exec raginald_ollama ollama pull llama3.2

# Nomic Embed Text (für Embeddings)
docker exec raginald_ollama ollama pull nomic-embed-text

# Optional: Alternatives Modell
docker exec raginald_ollama ollama pull mistral
```

### 7. Neo4j initialisieren

```bash
# Indexes und Constraints erstellen
php bin/console app:neo4j:init
```

### 8. Services testen

```bash
# Pipeline-Test
php bin/console app:test:extraction

# API Health Check
curl http://localhost:8000/api/health
```

## 🎯 Development Server starten

```bash
# Option 1: Symfony CLI (empfohlen)
symfony serve -d

# Option 2: PHP Built-in Server
php -S 0.0.0.0:8000 -t public

# Option 3: Docker (Full Stack)
docker-compose --profile full up -d
```

## 📨 Message Worker starten

```bash
# Development (mit Debug-Output)
php bin/console messenger:consume async -vv

# Production (als Daemon)
php bin/console messenger:consume async --time-limit=3600 --memory-limit=512M --env=prod

# Als Cronjob (Production)
# */5 * * * * cd /path/to/project && php bin/console messenger:consume async --time-limit=300 --memory-limit=512M --env=prod > /dev/null 2>&1
```

## 🧪 Erste API-Anfrage

```bash
# 1. Login (JWT Token erhalten)
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | jq -r '.token')

# 2. Requirements extrahieren
curl -X POST http://localhost:8000/api/requirements/extract \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "documentPath": "/path/to/your/requirements.pdf",
    "projectName": "Test Project",
    "extractionOptions": {
      "async": true
    }
  }'
```

## 🐛 Troubleshooting

### JWT Keys Error

```bash
# Keys fehlen?
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair
```

### Doctrine Driver Error (SQLite)

```bash
# PHP SQLite Extension prüfen
php -m | grep sqlite

# Installieren (Ubuntu/WSL2)
sudo apt-get install php8.2-sqlite3

# PHP neu laden
sudo service php8.2-fpm restart
```

### Docker Services nicht erreichbar

```bash
# Container-Status prüfen
docker-compose ps

# Logs anzeigen
docker-compose logs tika
docker-compose logs neo4j
docker-compose logs ollama

# Neu starten
docker-compose restart
```

### Ollama Modelle fehlen

```bash
# Verfügbare Modelle anzeigen
docker exec raginald_ollama ollama list

# Modell erneut herunterladen
docker exec raginald_ollama ollama pull llama3.2
```

## 🎉 Fertig!

Die Installation ist abgeschlossen. Öffne:

- **API**: http://localhost:8000/api
- **API Docs**: http://localhost:8000/api/docs
- **Neo4j Browser**: http://localhost:7474 (neo4j / password)

