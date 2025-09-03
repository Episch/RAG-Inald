# 📁 File Storage API - Erweiterte Funktionalität

## 🎯 Überblick

Die File Storage API erweitert die bestehenden Endpoints um die Möglichkeit, Ergebnisse zwischen den verschiedenen Verarbeitungsschritten zu speichern und zu verwenden:

- **Extraction** → speichert nur Tika-Extraktion und vorbereiteten Prompt (ohne LLM-Verarbeitung)
- **LLM Generate** → kann Extraction-Dateien laden und für Kategorisierung verwenden
- **Indexing** → kann LLM-Dateien verwenden für Neo4j-Indexierung

> 📝 **Wichtig:** Der ExtractorMessageHandler führt keine LLM-Verarbeitung mehr durch. Die LLM-Kategorisierung erfolgt separat im LlmMessageHandler für bessere Trennung der Verantwortlichkeiten.

## 🔧 Erweiterte API-Endpoints

### 1. **📄 Extraction API (erweitert)**
**`POST /api/extraction`**

**Neue Parameter:**
```json
{
  "path": "test/documents",
  "saveAsFile": true,
  "outputFilename": "mein_extract.json"
}
```

**Response enthält jetzt:**
```json
{
  "status": "queued",
  "request_id": "ext_12345",
  "file_id": "ext_67890abc_2024-01-16_14-30-25",
  "estimated_time": "15 seconds"
}
```

### 2. **🤖 LLM Generate API (erweitert)**
**`POST /api/llm/generate`**

**Neue Parameter:**
```json
{
  "prompt": "Analysiere die extrahierten Daten",
  "model": "llama3.2",
  "useExtractionFile": true,
  "extractionFileId": "ext_67890abc_2024-01-16_14-30-25",
  "saveAsFile": true,
  "outputFilename": "llm_analyse.json"
}
```

**Funktionalität:**
- Wenn `useExtractionFile: true`, wird der Inhalt der Extraction-Datei mit dem Prompt kombiniert
- Das LLM erhält sowohl den Benutzer-Prompt als auch die Extraction-Daten

### 3. **📊 Indexing API (erweitert)**
**`POST /api/indexing`**

**Neue Parameter:**
```json
{
  "entityType": "Document",
  "entityData": {"name": "Fallback Document"},
  "useLlmFile": true,
  "llmFileId": "llm_67890xyz_2024-01-16_15-00-45",
  "operation": "merge"
}
```

**Funktionalität:**
- Wenn `useLlmFile: true`, werden die LLM-Daten in eine Neo4j-kompatible Struktur transformiert
- Automatische Bereinigung und Validierung für Neo4j-Kompatibilität

### 4. **📁 File Management API (neu) - 🔐 Admin-geschützt**

> ⚠️ **Wichtig:** Die File Management API ist admin-geschützt und benötigt HTTP Basic Authentication mit `admin:admin123` oder `debug:debug456`

#### **Liste alle Dateien**
**`GET /api/admin/files`**

**Optional mit Filter:**
**`GET /api/admin/files?type=extraction`**

**Response:**
```json
{
  "status": "success",
  "count": 5,
  "files": [
    {
      "file_id": "ext_67890abc_2024-01-16_14-30-25",
      "type": "extraction",
      "created_at": "2024-01-16T14:30:25+00:00",
      "size": 15420
    }
  ],
  "available_types": ["extraction", "llm_response"]
}
```

#### **Hole spezifische Datei**
**`GET /api/admin/files/{fileId}`**

**Response:**
```json
{
  "status": "success",
  "file_id": "ext_67890abc_2024-01-16_14-30-25",
  "data": {
    "file_id": "ext_67890abc_2024-01-16_14-30-25",
    "type": "extraction",
    "input": {...},
    "tika_extraction": "...",
    "llm_response": {...}
  }
}
```

#### **Hole nur den Inhalt**
**`GET /api/admin/files/{fileId}/content`**

**Response:**
```json
{
  "status": "success",
  "file_id": "ext_67890abc_2024-01-16_14-30-25",
  "content": "Hier ist der extrahierte Text..."
}
```

#### **Lösche eine Datei**
**`DELETE /api/admin/files/{fileId}`**

## 🚀 Workflow-Beispiele

### **Beispiel 1: Kompletter Pipeline-Durchlauf**

```bash
# 1. Dokument extrahieren und Prompt vorbereiten (nur Tika + Prompt-Prep)
curl -X POST http://localhost:8000/api/extraction \
  -H "Content-Type: application/json" \
  -d '{
    "path": "test/documents",
    "saveAsFile": true
  }'

# Response: {"file_id": "ext_12345_2024-01-16_14-30-25", ...}
# Datei enthält: tika_extraction + prepared_prompt (ohne LLM-Verarbeitung)

# 2. LLM-Kategorisierung mit Extraction-Daten (separater Schritt!)
curl -X POST http://localhost:8000/api/llm/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Kategorisiere die Entitäten aus dem Dokument",
    "model": "llama3.2",
    "useExtractionFile": true,
    "extractionFileId": "ext_12345_2024-01-16_14-30-25",
    "saveAsFile": true
  }'

# Response: {"file_id": "llm_categorization_67890_2024-01-16_15-00-45", ...}
# LLM verwendet automatisch 'categorize' Modus für Extraction-Files

# 3. Neo4j-Indexierung mit LLM-Kategorieurung
curl -X POST http://localhost:8000/api/indexing \
  -H "Content-Type: application/json" \
  -d '{
    "entityType": "Document",
    "entityData": {"fallback": "data"},
    "useLlmFile": true,
    "llmFileId": "llm_categorization_67890_2024-01-16_15-00-45",
    "operation": "merge"
  }'
```

### **Beispiel 2: Verfügbare Dateien verwalten**

```bash
# Alle Dateien auflisten
curl -u admin:admin123 http://localhost:8000/api/admin/files

# Nur Extraction-Dateien
curl -u admin:admin123 http://localhost:8000/api/admin/files?type=extraction

# Spezifische Datei anzeigen
curl -u admin:admin123 http://localhost:8000/api/admin/files/ext_12345_2024-01-16_14-30-25

# Nur den extrahierten Inhalt
curl -u admin:admin123 http://localhost:8000/api/admin/files/ext_12345_2024-01-16_14-30-25/content

# Datei löschen
curl -u admin:admin123 -X DELETE http://localhost:8000/api/admin/files/ext_12345_2024-01-16_14-30-25
```

## 🔧 Technische Details

### **Dateistrukturen**

#### **Extraction-Datei:**
```json
{
  "file_id": "ext_12345_2024-01-16_14-30-25",
  "type": "extraction",
  "input": {
    "path": "test/documents",
    "extracted_content_length": 15420,
    "prompt_length": 2845,
    "timestamp": "2024-01-16T14:30:25+00:00"
  },
  "tika_extraction": "Raw extracted text...",
  "prepared_prompt": "Template-based prompt ready for LLM...",
  "performance": {
    "tika_time_seconds": 2.45,
    "optimization_time_seconds": 0.12,
    "prompt_time_seconds": 0.08,
    "total_time_seconds": 2.65
  },
  "created_at": "2024-01-16T14:30:25+00:00"
}
```

#### **LLM-Response-Datei:**
```json
{
  "file_id": "llm_67890_2024-01-16_15-00-45",
  "type": "llm_response",
  "request": {
    "prompt": "Combined prompt...",
    "model": "llama3.2",
    "use_extraction_file": true,
    "extraction_file_id": "ext_12345..."
  },
  "response": {...},
  "created_at": "2024-01-16T15:00:45+00:00"
}
```

### **Neo4j-Kompatibilität**

Die LLM-Responses werden automatisch für Neo4j transformiert:

1. **Nested Objects** → flache Struktur mit Unterstrich-Notation
2. **Arrays** → Komma-getrennte Strings
3. **Problematische Zeichen** → bereinigt
4. **Datentypen** → Neo4j-kompatibel (string, int, float, boolean)
5. **Property-Namen** → alphanumerisch + Unterstriche

### **Dateispeicherung**

**Pfade:**
- **Extraction-Dateien:** `public/storage/`
- **LLM-Response-Dateien:** `var/llm_output/`

**FileId-Format:** `{type}_{uniqid}_{timestamp}`

**Beispiele:**
- `ext_67890abc_2024-01-16_14-30-25`
- `llm_12345def_2024-01-16_15-00-45`

## ⚠️ Wichtige Hinweise

1. **Rückwärtskompatibilität:** Alle bestehenden API-Calls funktionieren weiterhin ohne Änderung
2. **Dateigröße:** Große Dateien können die Verarbeitung verlangsamen
3. **Speicherplatz:** Gespeicherte Dateien verbrauchen Festplattenspeicher
4. **Neo4j-Limits:** Property-Werte sind auf 1000 Zeichen begrenzt
5. **File-IDs:** Sind eindeutig und können zur späteren Referenzierung verwendet werden

## 📋 Best Practices

1. **Dateimanagement:** Regelmäßig alte Dateien über die DELETE-API entfernen
2. **Monitoring:** `/api/admin/files` nutzen um verfügbare Dateien zu überwachen (Admin-Auth erforderlich)
3. **Fehlerbehandlung:** File-IDs validieren bevor sie verwendet werden
4. **Pipeline-Design:** 
   - **Neue Trennung:** Extraction (nur Tika+Prompt) → LLM (Kategorisierung) → Indexing
   - **Separation of Concerns:** Jeder Handler hat klare Verantwortlichkeiten
   - **Modulare Verarbeitung:** LLM-Schritt kann übersprungen oder angepasst werden
5. **Performance:** Bei großen Dateien async=true für LLM-Processing verwenden
6. **Sicherheit:** File Management API ist admin-geschützt - nur autorisierte Benutzer können Dateien verwalten
7. **LLM-Kategorisierung:** Wenn `useExtractionFile=true`, wird automatisch 'categorize' Modus verwendet

---

**🔗 Diese Erweiterung ermöglicht es, komplexe Datenverarbeitungs-Pipelines zu erstellen, bei denen jeder Schritt auf den Ergebnissen des vorherigen aufbaut.**
