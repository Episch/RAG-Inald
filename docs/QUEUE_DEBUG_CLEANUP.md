# 🧹 Queue Debug Cleanup - Saubere API-Trennung

## 🎯 **Überblick**

Queue-Informationen wurden aus den Standard-Status-Endpunkten entfernt und in einen **dedizierten Debug-Endpunkt** verschoben, um Verwirrung zu vermeiden.

---

## ✅ **Was wurde bereinigt:**

### **1. 📊 `/api/status` (Public Endpoint)**
**Vorher:**
```json
{
  "services": {
    "redis": {
      "healthy": true,
      "message_queue": {
        "status": "online",
        "total_messages": 0,        // ← Verwirrend! Oft 0, obwohl Queues voll
        "failed_messages": 0,
        "queues": []
      }
    }
  }
}
```

**Nachher:**
```json
{
  "services": {
    "redis": {
      "healthy": true,
      "version": "7.2.0",
      "metrics": { /* Memory, Connections, etc. */ },
      "databases": [ /* Key statistics */ ]
    }
  },
  "overall_status": "healthy",
  "queue_debug_endpoint": "/admin/debug/queue"    // ← Hinweis auf Queue-Debug
}
```

### **2. 🔧 `/api/admin/config/status` (Admin Endpoint)**
**Vorher:**
```json
{
  "configuration": { /* ... */ },
  "services": {
    "redis": {
      "message_queue": {
        "status": "online",
        "total_messages": 0,        // ← Verwirrende Queue-Zahlen
        "active_queues": []
      }
    }
  }
}
```

**Nachher:**
```json
{
  "configuration": { /* System configuration */ },
  "timestamp": "2024-01-16T15:30:25+00:00",
  "environment": "dev",
  "debug_endpoints": {
    "queue_analysis": "/admin/debug/queue",       // ← Klare Weiterleitung
    "ollama_diagnostics": "/admin/debug/ollama"
  }
}
```

### **3. 🚨 `/api/admin/debug/ollama` (LLM Debug)**
**Vorher:**
```json
{
  "ollama_diagnostics": { /* ... */ },
  "message_queue_status": {
    "redis_status": { /* Detaillierte Queue-Info */ },
    "queue_summary": { /* Noch mehr Queue-Info */ }
  }
}
```

**Nachher:**
```json
{
  "ollama_diagnostics": { /* LLM-spezifische Diagnostics */ },
  "note": "For detailed queue analysis use /admin/debug/queue"
}
```

---

## 🎯 **Neuer dedizierter Queue-Debug-Endpunkt:**

### **📊 `/api/admin/debug/queue` (Admin Only)**
**Der EINZIGE Ort für Queue-Debugging:**

```bash
curl -u admin:password http://localhost:8000/api/admin/debug/queue
```

**Response:**
```json
{
  "queue_debug_analysis": {
    "queue_stats_service": {
      "service_name": "QueueStatsService",
      "method": "Counter-based (increment/decrement)",
      "counter_value": 15,
      "storage_type": "file",
      "storage_location": "/tmp/llm_queue_count.txt",
      "transport_class": "Symfony\\Component\\Messenger\\Transport\\DoctrineTransport"
    },
    "redis_connector": {
      "service_name": "RedisConnector", 
      "method": "Direct Redis key scanning",
      "redis_healthy": true,
      "total_messages": 0,
      "active_queues": [],
      "queue_types": {}
    },
    "discrepancy_analysis": {
      "queue_stats_count": 15,
      "redis_count": 0,
      "difference": 15,
      "discrepancy_detected": true,
      "likely_causes": [
        "QueueStatsService counter is higher - messages may have been processed but counter not decremented",
        "Messages may be in different Redis transport or database"
      ]
    },
    "recommendations": [
      "Reset counter to match Redis queue count",
      "Check if message handlers are properly decrementing counters",
      "Consider using Redis-based counting instead of file/APCu counters"
    ]
  }
}
```

---

## 🔧 **RedisConnector angepasst:**

### **Nur noch Core-Redis-Informationen:**
```php
// src/Service/Connector/RedisConnector.php
public function getServiceInfo(): array
{
    return [
        'name' => 'Redis',
        'version' => $info['redis_version'],
        'healthy' => true,
        'status_code' => 200,
        'metrics' => [
            'connected_clients' => $info['connected_clients'],
            'used_memory' => $this->formatMemory($info['used_memory']),
            'keyspace_hits' => $info['keyspace_hits'],
            // ...
        ],
        'databases' => $this->getDatabaseInfo($redis),
        'config' => ['host' => $host, 'port' => $port]
        // ❌ message_queue Info ENTFERNT - nur im Debug-Endpunkt!
    ];
}
```

---

## 💡 **Vorteile der Trennung:**

### ✅ **Saubere API-Struktur:**
- **`/api/status`** → Nur Service-Health (Tika, Neo4j, Redis, LLM)
- **`/api/admin/config/status`** → Nur Konfiguration & Environment
- **`/api/admin/debug/queue`** → Spezialisierte Queue-Analyse

### ✅ **Keine Verwirrung mehr:**
- Keine `total_messages: 0` in Status-Endpunkten
- Keine falschen Queue-Counts
- Klare Weiterleitung zum richtigen Endpunkt

### ✅ **Fokussierte Endpoints:**
- Jeder Endpunkt hat einen klaren Zweck
- Queue-Debugging ist isoliert und detailliert
- Monitoring-Endpoints sind performant

---

## 🚀 **Usage Examples:**

### **System Health Check:**
```bash
curl http://localhost:8000/api/status
# ✅ Zeigt nur Service-Status, keine verwirrenden Queue-Zahlen
```

### **Configuration Status:**
```bash
curl -u admin:password http://localhost:8000/api/admin/config/status
# ✅ Zeigt Konfiguration + Hinweis auf Debug-Endpoints
```

### **Queue Debugging:**
```bash
curl -u admin:password http://localhost:8000/api/admin/debug/queue
# ✅ Detaillierte Queue-Analyse mit Diskrepanz-Detection
```

---

## 🎉 **Ergebnis:**

**Saubere API-Struktur mit spezialisierten Endpunkten:**
- ✅ **Keine verwirrenden Queue-Zahlen** in Status-APIs
- ✅ **Dedizierter Queue-Debug-Endpunkt** für detaillierte Analyse
- ✅ **Klare Weiterleitung** zwischen Endpoints
- ✅ **Fokussierte Responsibilities** pro Endpoint

**Die APIs sind jetzt sauberer und weniger verwirrend!** 🎯
