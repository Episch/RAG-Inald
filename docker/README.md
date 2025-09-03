# 🐳 Docker Setup für RAG Backend

## 🎯 Überblick

Dieses Docker-Setup spiegelt die manuelle Linux-Installation als Container-Umgebung wieder und enthält alle erforderlichen Services:

- **PHP/Symfony App** - Hauptanwendung mit API Platform
- **MySQL** - Primäre Datenbank 
- **Redis** - Caching und Message Queue
- **Neo4j** - Graph-Datenbank für RAG-Daten
- **Apache Tika** - Dokumenten-Extraktion  
- **Ollama** - LLM-Server für Text-Generierung
- **Nginx** - Web-Server (optional)

## 🚀 Schnellstart

### 1. **Environment-Datei erstellen**
```bash
# Kopiere die Vorlage
cp docker/env.template .env

# Optional: Anpassungen vornehmen
nano .env
```

### 2. **Container starten**
```bash
# Alle Services starten
docker-compose up -d

# Mit Logs verfolgen
docker-compose up -d && docker-compose logs -f
```

### 3. **LLM-Modelle installieren (einmalig)**
```bash
# Ollama-Modelle herunterladen
docker-compose --profile setup run --rm ollama-setup

# Oder manuell einzelne Modelle:
docker-compose exec ollama ollama pull llama3.2
docker-compose exec ollama ollama pull mistral
```

### 4. **Services testen**
```bash
# API-Endpunkt testen
curl http://localhost:8000/api/status

# Neo4j Browser öffnen
# http://localhost:7474 (neo4j/neo4j)

# Swagger-Dokumentation
# http://localhost:8000/api/docs
```

## 📋 Service-Ports

| Service | Port | URL | Beschreibung |
|---------|------|-----|--------------|
| **Symfony App** | 8000 | http://localhost:8000 | Haupt-API |
| **MySQL** | 3306 | localhost:3306 | Datenbank |
| **Redis** | 6379 | localhost:6379 | Cache/Queue |
| **Neo4j HTTP** | 7474 | http://localhost:7474 | Graph-DB Browser |
| **Neo4j Bolt** | 7687 | bolt://localhost:7687 | Graph-DB Protocol |
| **Apache Tika** | 9998 | http://localhost:9998 | Dokument-Extraktion |
| **Ollama** | 11434 | http://localhost:11434 | LLM-Server |
| **Nginx** | 80 | http://localhost | Web-Server (Profile) |

## 🛠️ Docker-Profile

### **Standard (alle Services)**
```bash
docker-compose up -d
```

### **Mit Nginx Web-Server**
```bash
docker-compose --profile nginx up -d
```

### **Nur Setup (Modelle herunterladen)**
```bash
docker-compose --profile setup run --rm ollama-setup
```

## 📁 Volume-Struktur

```
Projektroot/
├── public/storage/     → Dokument-Storage (Container: /var/www/html/public/storage)
├── var/               → Symfony Cache/Logs (Container: /var/www/html/var)
└── docker-volumes/    → Docker-managed volumes
    ├── mysql_data/
    ├── redis_data/  
    ├── neo4j_data/
    └── ollama_data/
```

## 🔧 Development-Commands

### **Container-Management**
```bash
# Status anzeigen
docker-compose ps

# Logs anzeigen
docker-compose logs -f [service-name]

# In Container einsteigen
docker-compose exec app bash
docker-compose exec neo4j cypher-shell

# Services neustarten
docker-compose restart [service-name]

# Services stoppen
docker-compose down

# Volumes löschen (ACHTUNG: Datenverlust!)
docker-compose down -v
```

### **Symfony-Befehle**
```bash
# Cache leeren
docker-compose exec app php bin/console cache:clear

# Migration ausführen
docker-compose exec app php bin/console doctrine:migrations:migrate

# Message Consumer manuell starten
docker-compose exec app php bin/console messenger:consume async -vv

# Debugging
docker-compose exec app php bin/console debug:config
docker-compose exec app php bin/console debug:router
```

### **Service-Tests**
```bash
# MySQL Verbindung testen
docker-compose exec db mysql -u root -ppassword -e "SHOW DATABASES;"

# Redis testen
docker-compose exec redis redis-cli ping

# Neo4j testen
docker-compose exec neo4j cypher-shell -u neo4j -p neo4j "MATCH (n) RETURN count(n);"

# Tika testen
curl -H "Accept: application/json" http://localhost:9998/tika

# Ollama testen
curl http://localhost:11434/api/version
docker-compose exec ollama ollama list
```

## 🔍 Troubleshooting

### **Service startet nicht**
```bash
# Logs überprüfen
docker-compose logs [service-name]

# Container-Status prüfen
docker-compose ps

# Einzelnen Service neustarten
docker-compose restart [service-name]
```

### **Port-Konflikte**
```bash
# Ports anpassen in docker-compose.yml
# Beispiel: "8080:8000" statt "8000:8000"

# Aktive Ports prüfen
netstat -tulpn | grep :8000
```

### **Permission-Probleme**
```bash
# Ordner-Rechte setzen
sudo chown -R $(id -u):$(id -g) public/storage var/

# In Container reparieren
docker-compose exec app chown -R www-data:www-data /var/www/html/var
```

### **Ollama-Modelle fehlen**
```bash
# Modelle manuell herunterladen
docker-compose exec ollama ollama pull llama3.2
docker-compose exec ollama ollama pull mistral
docker-compose exec ollama ollama pull qwen2.5

# Verfügbare Modelle prüfen
docker-compose exec ollama ollama list
```

### **Neo4j Authentifizierung**
```bash
# Standard: neo4j/neo4j
# Passwort ändern über Browser: http://localhost:7474
# Oder via Cypher:
docker-compose exec neo4j cypher-shell -u neo4j -p neo4j "ALTER CURRENT USER SET PASSWORD FROM 'neo4j' TO 'newpassword';"
```

## ⚡ Performance-Tipps

### **Für Entwicklung**
```yaml
# In docker-compose.yml für app service:
environment:
  - APP_ENV=dev
  - APP_DEBUG=1
volumes:
  - .:/var/www/html:cached  # macOS Performance
```

### **Für Produktion**
```yaml
# In docker-compose.yml:
environment:
  - APP_ENV=prod
  - APP_DEBUG=0
# Nginx-Profile verwenden
# Separaten Cache-Volume mounten
```

### **Memory-Limits**
```yaml
# Für große LLM-Modelle:
deploy:
  resources:
    limits:
      memory: 4G
    reservations:
      memory: 2G
```

## 🔐 Sicherheit

### **Produktions-Checklist**
- [ ] Standard-Passwörter ändern (Neo4j, MySQL)
- [ ] APP_SECRET generieren  
- [ ] CORS-Einstellungen anpassen
- [ ] Nginx-Profile für HTTPS konfigurieren
- [ ] Sensitive Environment-Variablen über Docker Secrets
- [ ] Log-Rotation aktivieren

### **Development vs Production**
```bash
# Development
docker-compose up -d

# Production (separates Compose-File empfohlen)
docker-compose -f docker-compose.prod.yml up -d
```

## 📊 Monitoring

### **Health-Checks**
```bash
# Alle Services
curl http://localhost:8000/api/status

# Einzelne Services  
curl http://localhost:9998/tika         # Tika
curl http://localhost:11434/api/version # Ollama
curl http://localhost:7474/db/neo4j/    # Neo4j
```

### **Resource-Monitoring**
```bash
# Container-Resources
docker stats

# Spezifischer Container
docker stats rag_backend_app
```

---

**🐳 Diese Docker-Umgebung bietet eine vollständige, produktionsreife RAG-Backend-Infrastruktur mit allen erforderlichen Services!**
