# 📦 Installation Guide

## Voraussetzungen

- **PHP**: 8.2+
- **Composer**: 2.x
- **Docker**: 20.x+ (für Services)
- **Redis**: 7.x+ (für JWT Refresh Tokens)
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
5. ✅ Redis starten & testen
6. ✅ LLM-Modelle herunterladen
7. ✅ Neo4j initialisieren
8. ✅ Services testen

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

### 5. Redis starten

**Option A: Lokal installiert (empfohlen für Development)**
```bash
# Redis starten (Ubuntu/WSL)
sudo service redis-server start

# Status prüfen
redis-cli ping
# Sollte "PONG" zurückgeben
```

**Option B: Docker**
```bash
# Redis via Docker
docker run -d --name raginald_redis \
  -p 6379:6379 \
  redis:7-alpine

# Status prüfen
docker exec raginald_redis redis-cli ping
```

### 6. Docker Services starten

```bash
# Nur externe Services (Tika, Neo4j, Ollama)
docker-compose up -d tika neo4j ollama

# Services prüfen
docker-compose ps
```

### 7. LLM-Modelle installieren

```bash
# LLama 3.2 (für Requirements Extraction)
docker exec raginald_ollama ollama pull llama3.2

# Nomic Embed Text (für Embeddings)
docker exec raginald_ollama ollama pull nomic-embed-text

# Optional: Alternatives Modell
docker exec raginald_ollama ollama pull mistral
```

### 8. Neo4j initialisieren

```bash
# Indexes und Constraints erstellen
php bin/console app:neo4j:init
```

### 9. Services testen

```bash
# Pipeline-Test
php bin/console app:test:extraction

# API Health Check (sollte alle Services als "up" zeigen)
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

### JWT Authentication Flow

```bash
# 1. Login (JWT Token + Refresh Token erhalten)
RESPONSE=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}')

TOKEN=$(echo $RESPONSE | jq -r '.token')
REFRESH_TOKEN=$(echo $RESPONSE | jq -r '.refresh_token')

echo "Access Token: $TOKEN"
echo "Refresh Token: $REFRESH_TOKEN"

# 2. Token erneuern (wenn Access Token abläuft)
NEW_RESPONSE=$(curl -s -X POST http://localhost:8000/api/token/refresh \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\",\"rotate\":true}")

TOKEN=$(echo $NEW_RESPONSE | jq -r '.token')
REFRESH_TOKEN=$(echo $NEW_RESPONSE | jq -r '.refresh_token')

echo "New Access Token: $TOKEN"
echo "New Refresh Token: $REFRESH_TOKEN"

# 3. Requirements extrahieren
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

### Redis Connection Error

```bash
# Redis läuft nicht?
sudo service redis-server status

# Redis starten
sudo service redis-server start

# Redis Connection testen
redis-cli ping
# Sollte "PONG" zurückgeben

# Falls Redis auf anderem Port läuft
redis-cli -h localhost -p 6379 ping
```

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

