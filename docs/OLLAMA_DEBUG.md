# 🐛 Ollama Integration Debug Guide

## 🔍 Problem Analyse

**Status**: LlmConnector ist funktional ✅ (Status 200, Version 0.11.7)  
**Problem**: `/api/generate` Endpunkt gibt **404 Not Found** zurück  
**Ursache**: Wahrscheinlich **keine Modelle geladen** (`"models": []`)

## 🚨 Sofortmaßnahmen

### 1. **Modell installieren** (Hauptproblem)
```bash
# Prüfe verfügbare Modelle
ollama list

# Falls leer, installiere ein Modell
ollama pull llama3.2

# Oder ein kleineres Modell zum Testen
ollama pull tinyllama
```

### 2. **Ollama Status prüfen**
```bash
# Prüfe ob Ollama läuft
curl http://localhost:11434/api/version

# Prüfe verfügbare Modelle
curl http://localhost:11434/api/tags

# Test direkt gegen Ollama
curl http://localhost:11434/api/generate \
  -H "Content-Type: application/json" \
  -d '{"model": "llama3.2", "prompt": "Hello", "stream": false}'
```

### 3. **Debug-Endpunkte nutzen**
```bash
# Vollständiges Ollama-Debug
curl http://localhost:8000/debug/ollama | jq

# Modell-Test
curl http://localhost:8000/test/llm/models | jq

# Synchroner Generation-Test
curl http://localhost:8000/test/llm/sync | jq
```

## 🔧 Häufige Lösungen

### **Lösung 1: Modell installieren**
```bash
ollama pull llama3.2
# Warte bis Download fertig ist (~4GB)

# Teste danach
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Hello", "async": false}'
```

### **Lösung 2: Ollama neustarten**
```bash
# Stoppe Ollama
pkill ollama

# Starte Ollama neu
ollama serve

# In neuem Terminal: Modell laden
ollama pull llama3.2
```

### **Lösung 3: Kleineres Modell testen**
```bash
# Für schwächere Hardware
ollama pull tinyllama  # nur ~600MB

# Teste mit kleinem Modell
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Hi", "model": "tinyllama", "async": false}'
```

### **Lösung 4: Ollama Update**
```bash
# Update Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Oder manuell
sudo systemctl restart ollama
```

## 📊 Erwartete Responses

### ✅ **Korrekter Status** (nach Modell-Installation)
```json
{
  "status": [
    {
      "service": "LlmConnector",
      "content": "0.11.7", 
      "status_code": 200,
      "healthy": true,
      "models": ["llama3.2", "tinyllama"] // 🎯 NICHT LEER!
    }
  ]
}
```

### ✅ **Erfolgreiche Generation**
```json
{
  "status": "completed",
  "request_id": "llm_66f5c2e...",
  "model": "llama3.2",
  "processing_time_seconds": 3.45,
  "response": "Hello! How can I help you today?",
  "metadata": {
    "total_duration": 3450000000,
    "eval_count": 12
  }
}
```

## 🛠️ Erweiterte Debug-Kommandos

### **Vollständiger Ollama-Check**
```bash
# 1. Service Status
systemctl status ollama

# 2. Installierte Modelle
ollama list

# 3. Ollama Version
ollama --version

# 4. Prozesse
ps aux | grep ollama

# 5. Port-Check
netstat -tlnp | grep 11434

# 6. Logs
journalctl -u ollama -f
```

### **Manual API Tests**
```bash
# Version Check (sollte funktionieren)
curl http://localhost:11434/api/version

# Models Check (sollte Modelle zeigen)
curl http://localhost:11434/api/tags | jq

# Generate Test (braucht geladenes Modell)
curl http://localhost:11434/api/generate \
  -H "Content-Type: application/json" \
  -d '{
    "model": "llama3.2",
    "prompt": "Say hello",
    "stream": false
  }' | jq
```

## 🎯 Wahrscheinlichste Ursachen

1. **90%**: Kein Modell installiert (`ollama list` ist leer)
2. **5%**: Ollama läuft nicht (`curl localhost:11434` fehlschlägt)
3. **3%**: Falsche Ollama-Version oder API-Änderung
4. **2%**: Port/Netzwerk-Problem

## ✅ Erfolgs-Indikatoren

Nach der Reparatur sollten funktionieren:
- ✅ `/api/status` zeigt `"models": ["llama3.2"]` 
- ✅ `/api/llm/generate` gibt 202 (async) oder 200 (sync)
- ✅ `/test/llm/sync` zeigt erfolgreiche Generation
- ✅ Message Worker verarbeitet LlmMessage ohne 404-Fehler

## 🔗 Nützliche Links

- [Ollama Installation](https://ollama.ai/)
- [Ollama Models](https://ollama.ai/library)
- [Ollama API Docs](https://github.com/ollama/ollama/blob/main/docs/api.md)

---

**TL;DR**: Führe `ollama pull llama3.2` aus, dann sollte alles funktionieren! 🚀
