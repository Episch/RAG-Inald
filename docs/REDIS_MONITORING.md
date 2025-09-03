# 📊 Redis & Message Queue Monitoring

## 🎯 **Überblick**

Umfassendes Redis-Monitoring mit **Message Queue-Status** für das Symfony 7.3 Projekt. Überwacht Redis-Verbindung, Memory Usage, und **Symfony Messenger Queue-Status** in Echtzeit.

---

## 🛠️ **Neue Services & Endpunkte**

### **1. RedisConnector Service**
**`src/Service/Connector/RedisConnector.php`**

```php
/**
 * Redis connector for cache and message queue monitoring.
 * 
 * Provides functionality to check Redis service health, including
 * connection status, memory usage, and key statistics.
 */
class RedisConnector implements ConnectorInterface
```

**Features:**
- ✅ **Redis Connection Health Check**
- ✅ **Memory Usage Monitoring**
- ✅ **Message Queue Statistics**
- ✅ **Database Key Counts**
- ✅ **Performance Metrics**

---

## 📡 **API Endpunkte mit Redis-Status**

### **1. 🔍 System Status - `/api/status`**
**Public Endpoint**

```bash
curl http://localhost:8000/api/status
```

**Response:**
```json
{
  "services": {
    "tika": { "healthy": true, "version": "3.2.2" },
    "neo4j": { "healthy": true, "version": "5.0" },
    "redis": {
      "name": "Redis",
      "healthy": true,
      "version": "7.2.0",
      "status_code": 200,
      "metrics": {
        "connected_clients": 2,
        "used_memory": "2.1 MB",
        "used_memory_peak": "3.5 MB",
        "total_commands_processed": 1234,
        "keyspace_hits": 890,
        "keyspace_misses": 45,
        "uptime_in_seconds": 86400
      },
      "message_queue": {
        "status": "online",
        "total_messages": 15,
        "failed_messages": 2,
        "processing_messages": 3,
        "queue_count": 4,
        "queues": [
          {
            "name": "async:extraction",
            "type": "regular",
            "messages": 8,
            "redis_type": "list"
          },
          {
            "name": "async:llm",
            "type": "regular", 
            "messages": 5,
            "redis_type": "list"
          },
          {
            "name": "async:indexing",
            "type": "failed",
            "messages": 2,
            "redis_type": "list"
          }
        ]
      }
    },
    "llm": { "healthy": true, "models": ["llama3.2"] }
  },
  "overall_status": "healthy",
  "timestamp": "2024-01-16T15:30:25+00:00"
}
```

---

### **2. 🔧 Admin Config Status - `/api/admin/config/status`**
**Admin Protected**

```bash
curl -u admin:password http://localhost:8000/api/admin/config/status
```

**Response:**
```json
{
  "configuration": { /* ... */ },
  "services": {
    "redis": {
      "name": "Redis",
      "healthy": true,
      "version": "7.2.0",
      "connection": {
        "host": "localhost",
        "port": 6379
      },
      "message_queue": {
        "status": "online",
        "total_messages": 15,
        "failed_messages": 2,
        "processing_messages": 3,
        "queue_count": 4,
        "active_queues": [
          { "name": "async:extraction", "type": "regular", "messages": 8 },
          { "name": "async:llm", "type": "regular", "messages": 5 },
          { "name": "async:indexing", "type": "failed", "messages": 2 }
        ]
      },
      "databases": [
        { "database": 0, "keys": 45, "expires": 12, "avg_ttl": 3600 }
      ],
      "health_checks": {
        "ping": { "status": true, "message": "Ping test", "result": "PONG" },
        "set_get": { "status": true, "message": "Set/Get operations test" }
      }
    }
  },
  "timestamp": "2024-01-16T15:30:25+00:00"
}
```

---

### **3. 🚨 Debug Diagnostics - `/api/admin/debug/ollama`**
**Admin Protected**

```bash
curl -u admin:password http://localhost:8000/api/admin/debug/ollama
```

**Response (erweitert):**
```json
{
  "ollama_diagnostics": { /* LLM Status */ },
  "message_queue_status": {
    "redis_status": {
      "healthy": true,
      "version": "7.2.0",
      "connection": { "host": "localhost", "port": 6379 }
    },
    "queue_summary": {
      "status": "online",
      "total_messages": 15,
      "failed_messages": 2,
      "processing_messages": 3,
      "queue_count": 4
    },
    "active_queues": [
      { "name": "async:extraction", "type": "regular", "messages": 8 },
      { "name": "async:llm", "type": "regular", "messages": 5 },
      { "name": "async:indexing", "type": "failed", "messages": 2 }
    ],
    "memory_usage": "2.1 MB",
    "warnings": [
      "Found 2 failed messages in queue"
    ]
  }
}
```

---

## 📊 **Message Queue Details**

### **Queue Types erkannt:**
- **`regular`** → Normale wartende Messages
- **`retry`** → Messages im Retry-Modus  
- **`failed`** → Fehlgeschlagene Messages
- **`delayed`** → Zeitverzögerte Messages

### **Symfony Messenger Redis Keys:**
```
messenger:async:queue:extraction     → ExtractorMessage
messenger:async:queue:llm           → LlmMessage  
messenger:async:queue:indexing      → IndexingMessage
messenger:async:retry:*             → Retry Messages
messenger:async:failure:*           → Failed Messages
```

---

## ⚠️ **Monitoring & Alerts**

### **Automatische Warnungen:**
```php
// Debug Controller Warnings
if ($queueInfo['failed_messages'] > 0) {
    $debug['warnings'][] = "Found {$queueInfo['failed_messages']} failed messages in queue";
}

if ($queueInfo['total_messages'] > 100) {
    $debug['warnings'][] = "Queue has {$queueInfo['total_messages']} pending messages - consider increasing workers";
}
```

### **Health Check Tests:**
1. **Ping Test** → `redis->ping()` 
2. **Set/Get Test** → Schreib-/Leseoperationen
3. **Connection Test** → Verbindungsstatus

---

## 🔧 **Konfiguration**

### **Environment Variables:**
```bash
# Redis Connection (optional)
REDIS_HOST=localhost
REDIS_PORT=6379
```

### **Service Registration:**
```yaml
# config/services.yaml
App\Service\Connector\RedisConnector:
    public: true
```

---

## 🎯 **Use Cases**

### **1. Production Monitoring**
```bash
# Schneller Queue-Status Check
curl -s http://localhost:8000/api/status | jq '.services.redis.message_queue'
```

### **2. Failed Messages Detection**
```bash
# Failed Messages finden
curl -s -u admin:pass http://localhost:8000/api/admin/config/status | \
  jq '.services.redis.message_queue.failed_messages'
```

### **3. Memory Usage Tracking**
```bash
# Redis Memory Usage
curl -s http://localhost:8000/api/status | \
  jq '.services.redis.metrics.used_memory'
```

### **4. Queue Performance**
```bash
# Alle aktiven Queues
curl -s http://localhost:8000/api/status | \
  jq '.services.redis.message_queue.queues[]'
```

---

## 🚀 **Vorteile**

✅ **Vollständiges Redis Monitoring**  
✅ **Message Queue Transparenz**  
✅ **Proaktive Fehler-Erkennung**  
✅ **Performance Metriken**  
✅ **Admin & Public Endpunkte**  
✅ **Einheitlicher Code-Style**  
✅ **Automatische Warnungen**  

**Redis-Status ist jetzt vollständig in alle Monitoring-Endpunkte integriert!** 🎉
