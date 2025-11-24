# Document Extraction Architecture

## Überblick

Die Document Extraction Pipeline verwendet einen **Format-Router-Ansatz** für robuste und erweiterbare Dokumentenverarbeitung.

## Architektur-Layers

### 1. **Format Detection Layer**

**Service**: `DocumentFormatDetector`

**Verantwortlichkeiten**:
- MIME-Type Erkennung via:
  - **Primary**: Symfony MIME Component (Magic Bytes)
  - **Fallback**: PHP fileinfo Extension
  - **Last Resort**: File Extension

**Vorteile**:
- ✅ Zuverlässige Erkennung anhand von Magic Bytes
- ✅ Fallback-Mechanismen für Edge Cases
- ✅ Detailliertes Logging

**Beispiel**:
```php
$detector = new DocumentFormatDetector($logger);
$mimeType = $detector->detectMimeType('/path/to/document.pdf');
// Returns: "application/pdf"

$formatName = $detector->getFormatName($mimeType);
// Returns: "PDF Document"
```

---

### 2. **Parser Adapter Layer**

**Interface**: `DocumentParserInterface`

**Verantwortlichkeiten**:
- Einheitliche Schnittstelle für alle Parser
- Format-spezifische Extraktion
- Priority-basiertes Matching

**Implementierungen**:

#### ✅ **TikaDocumentParser** (Universal Fallback)
- **Supports**: Alle Formate
- **Priority**: 0 (niedrigste)
- **Use Case**: Fallback wenn kein spezialisierter Parser verfügbar

#### 🚀 **Zukünftige Parser** (TODO):
- **PdfDocumentParser** (Priority: 100)
  - Library: `smalot/pdfparser` oder `spatie/pdf-to-text`
  - Supports: `application/pdf`
  
- **SpreadsheetDocumentParser** (Priority: 100)
  - Library: `phpoffice/phpspreadsheet`
  - Supports: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, etc.
  
- **WordDocumentParser** (Priority: 100)
  - Library: `phpoffice/phpword`
  - Supports: `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
  
- **MarkdownDocumentParser** (Priority: 150)
  - Native PHP parsing
  - Supports: `text/markdown`
  
- **CsvDocumentParser** (Priority: 150)
  - Native PHP parsing
  - Supports: `text/csv`
  
- **ImageOcrDocumentParser** (Priority: 50)
  - Library: `thiagoalessio/tesseract_ocr`
  - Supports: `image/*`

**Beispiel - Neuen Parser hinzufügen**:

```php
<?php

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;

class PdfDocumentParser implements DocumentParserInterface
{
    public function extractText(string $filePath): string
    {
        // Implementierung mit smalot/pdfparser
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    public function getPriority(): int
    {
        return 100; // Höher als Tika (0)
    }

    public function getName(): string
    {
        return 'PDF Native Parser';
    }
}
```

Dann in `config/services.yaml` registrieren:

```yaml
App\Service\DocumentParser\PdfDocumentParser:
    tags: ['app.document_parser']
```

---

### 3. **Routing Layer**

**Service**: `DocumentExtractionRouter`

**Verantwortlichkeiten**:
- Format-Erkennung orchestrieren
- Passenden Parser finden (nach Priority)
- Fallback-Handling bei Parser-Failures
- Detailliertes Logging

**Workflow**:
```
1. detectMimeType(filePath)
2. Filter parsers by supports(mimeType)
3. Sort by priority (highest first)
4. Try each parser sequentially
5. Return first successful result
6. Log all attempts & failures
```

**Beispiel**:
```php
$router = new DocumentExtractionRouter(
    $formatDetector,
    $logger,
    $parsers // Tagged services
);

$result = $router->extractText('/path/to/document.pdf');
// Returns:
// [
//     'text' => 'Extracted content...',
//     'parser' => 'PDF Native Parser',
//     'mime_type' => 'application/pdf',
//     'format' => 'PDF Document'
// ]
```

---

### 4. **Integration Layer**

**Handler**: `ExtractRequirementsHandler`

**Pipeline**:
```
1. DocumentExtractionRouter::extractText()
   → Format Detection
   → Parser Selection
   → Text Extraction
   
2. OllamaLLMService::generate()
   → Requirements Extraction (TOON Format)
   
3. OllamaEmbeddingsService::embed()
   → Vector Embeddings
   
4. Neo4jConnectorService::storeSoftwareApplication()
   → Graph Storage
```

---

## Vorteile dieser Architektur

### ✅ **Erweiterbarkeit**
- Neue Parser einfach hinzufügen ohne bestehenden Code anzufassen
- Priority-System erlaubt Fallback-Ketten

### ✅ **Robustheit**
- Multi-Layer Format Detection
- Automatic Fallback bei Parser-Failures
- Tika als Universal-Fallback

### ✅ **Wartbarkeit**
- Klare Separation of Concerns
- Jeder Parser ist unabhängig testbar
- Einheitliches Interface

### ✅ **Performance**
- Parser mit höherer Priority werden zuerst versucht
- Native Parser sind schneller als Tika (HTTP Overhead)
- Lazy Loading der Parser

### ✅ **Observability**
- Detailliertes Logging auf jedem Layer
- Parser-Selection wird geloggt
- Timing-Informationen pro Layer

---

## Produktive Empfehlungen

### Phase 1: ✅ **Foundation** (aktuell)
- [x] Format Detection Layer
- [x] Router Infrastructure
- [x] Tika Fallback
- [ ] `composer require symfony/mime` ausführen

### Phase 2: 🚀 **Native Parsers** (nächste Schritte)
```bash
# PDF Parser
composer require smalot/pdfparser

# Office Formats
composer require phpoffice/phpspreadsheet
composer require phpoffice/phpword

# OCR (optional)
composer require thiagoalessio/tesseract_ocr
```

### Phase 3: 🔧 **Optimierungen**
- Async Parser Execution (für große Dateien)
- Caching Layer (für wiederholte Extractions)
- Streaming für sehr große Dokumente
- Health Checks für externe Services

---

## Testing

```bash
# 1. Verschiedene Formate testen
curl -X POST http://localhost:8000/api/requirements/extract \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "projectName": "Test",
    "serverPath": "/path/to/document.pdf",
    "async": true
  }'

# 2. Logs prüfen
tail -f var/log/dev.log | grep "format detected"
tail -f var/log/dev.log | grep "Parser"

# 3. Supported Formats prüfen (TODO: Endpoint erstellen)
curl http://localhost:8000/api/extraction/formats
```

---

## Troubleshooting

### Problem: "Could not detect MIME type"
**Lösung**: 
- Prüfen ob `ext-fileinfo` installiert ist: `php -m | grep fileinfo`
- File Extension prüfen
- Datei könnte korrupt sein

### Problem: "No parser available for MIME type"
**Lösung**:
- Tika-Service prüfen: `curl http://localhost:9998/version`
- Service-Registrierung in `services.yaml` prüfen
- Tagged services prüfen: `php bin/console debug:container --tag=app.document_parser`

### Problem: "All parsers failed"
**Lösung**:
- Detaillierte Logs prüfen (jeder Parser-Fehler wird geloggt)
- Tika-Service erreichbar?
- Datei-Permissions prüfen

---

## Weitere Informationen

- **Symfony MIME**: https://symfony.com/doc/current/components/mime.html
- **PHP fileinfo**: https://www.php.net/manual/en/book.fileinfo.php
- **Tika Documentation**: https://tika.apache.org/

