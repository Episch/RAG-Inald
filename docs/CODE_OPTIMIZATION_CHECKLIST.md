# 🔧 Code-Optimierungs-Checkliste - Symfony 7.3 / PHP 8.3

## 📊 **Analyse-Status**
Basierend auf Codebase-Analyse vom **$(date)**

---

## 🎯 **1. Sprachkonsistenz (Deutsch → Englisch)**

### **❌ Gefundene Probleme:**
- **Deutsche Kommentare** in `TikaConnector.php`:
  ```php
  // ❌ SCHLECHT
  $textContent = preg_replace('/\s+/', ' ', $textContent); // Mehrfach-Whitespaces
  $textContent = preg_replace('/\n{2,}/', "\n", $textContent); // Mehrfach-Zeilenumbrüche
  $textContent = preg_replace('/^\s*(Seite|Page)\s*\d+.*$/mi', '', $textContent);
  
  // ✅ BESSER
  $textContent = preg_replace('/\s+/', ' ', $textContent); // Multiple whitespaces
  $textContent = preg_replace('/\n{2,}/', "\n", $textContent); // Multiple line breaks
  $textContent = preg_replace('/^\s*(Page)\s*\d+.*$/mi', '', $textContent);
  ```

- **Deutsche Begriffe** in Kommentaren:
  ```php
  // ❌ "Escaped JSON einfügen, falls notwendig"
  // ✅ "Insert escaped JSON if necessary"
  ```

### **🔧 Lösungsplan:**
- [ ] **Alle deutschen Kommentare** → Englische Kommentare
- [ ] **Deutsche Regex-Patterns** → Englische Patterns  
- [ ] **Variable/Method-Namen** → Nur englische Begriffe
- [ ] **Error-Messages** → Englisch (außer User-facing)

---

## 🏗️ **2. ApiPlatform Attribute-Konsistenz**

### **❌ Gefundene Probleme:**
- **Inkonsistente ApiResource-Nutzung:**
  - `AdminConfigEnvController` → ✅ Hat `#[ApiResource]`
  - `LlmController` → ❌ Fehlt `#[ApiResource]`
  - `IndexingController` → ❌ Fehlt `#[ApiResource]`
  - `ExtractionController` → ❌ Fehlt `#[ApiResource]` (aber DTOs haben es)

### **🔧 Lösungsplan:**
- [ ] **Alle Controller** → Einheitliche `#[ApiResource]` Definition
- [ ] **OpenAPI-Dokumentation** → Vollständige Swagger-Integration
- [ ] **Security-Annotations** → Konsistente `security:` Parameter
- [ ] **Response-Gruppen** → Einheitliche `normalizationContext`

**Template:**
```php
#[ApiResource(
    shortName: 'ControllerName',
    operations: [
        new Post(
            uriTemplate: '/endpoint',
            controller: self::class,
            description: 'English description with detailed explanation.',
            normalizationContext: ['groups' => ['api:read']],
            denormalizationContext: ['groups' => ['api:write']],
            security: "is_granted('PUBLIC_ACCESS')" // or 'ROLE_ADMIN'
        )
    ]
)]
```

---

## 🏛️ **3. Constructor-Pattern Vereinheitlichung**

### **❌ Gefundene Probleme:**
- **Mixed Constructor Patterns:**
  ```php
  // ❌ INKONSISTENT - StatusController (alte Art)
  private TikaConnector $tikaConnector;
  public function __construct(TikaConnector $tikaConnector) {
      $this->tikaConnector = $tikaConnector;
  }
  
  // ✅ MODERN - LlmController (Constructor Property Promotion)
  public function __construct(
      private MessageBusInterface $bus,
      private LlmConnector $llmConnector
  ) {}
  ```

### **🔧 Lösungsplan:**
- [ ] **Alle Controller** → Constructor Property Promotion (PHP 8.3 Standard)
- [ ] **Alle Services** → Constructor Property Promotion
- [ ] **Readonly Properties** → Wo immer möglich `private readonly`
- [ ] **Type Hints** → Vollständige Typisierung

**Standard-Template:**
```php
public function __construct(
    private readonly ServiceInterface $service,
    private readonly LoggerInterface $logger,
    private readonly SerializerInterface $serializer
) {}
```

---

## 🎨 **4. Kommentar-Stil Vereinheitlichung**

### **❌ Gefundene Probleme:**
- **Inkonsistente Kommentar-Stile:**
  ```php
  // ❌ GEMISCHT
  // 🚀 Performance: Cache responses       (mit Emoji)
  // Generate unique request ID            (ohne Emoji)
  /** @var LlmPrompt $data */              (PHPDoc)
  # {{tika_json}}                          (Hash-Kommentar)
  ```

### **🔧 Lösungsplan:**
- [ ] **Einheitlicher Stil** → `//` für inline, `/** */` für Blocks
- [ ] **Keine Emojis** in Code-Kommentaren (nur in Markdown-Docs)
- [ ] **PHPDoc-Standards** → Vollständige `@param`, `@return`, `@throws`
- [ ] **Kategorien-Prefixes** → `TODO:`, `FIXME:`, `NOTE:`

**Standard:**
```php
/**
 * Process large prompts with chunking strategy for better performance.
 * 
 * @param LlmMessage $message The message containing the prompt to process
 * @param array $options LLM processing options
 * @param int $tokenCount Total token count for logging
 * 
 * @return array Combined results from all chunks
 * @throws \RuntimeException If chunk processing fails
 */
private function processLargePrompt(LlmMessage $message, array $options, int $tokenCount): array
```

---

## 🧹 **5. Code-Organisation & Struktur**

### **❌ Gefundene Probleme:**
- **Inconsistent Method-Ordering** in Klassen
- **Magic Numbers** ohne Konstanten (4000 Token-Limit)
- **Hardcoded Strings** statt Konstanten
- **Missing Return Types** in manchen Methoden

### **🔧 Lösungsplan:**
- [ ] **Class-Member-Ordering:**
  1. Constants
  2. Properties  
  3. Constructor
  4. Public methods
  5. Private methods

- [ ] **Konstanten definieren:**
  ```php
  class LlmMessageHandler 
  {
      private const MAX_TOKENS_FOR_SYNC = 4000;
      private const CHUNK_SIZE_TOKENS = 800;
      private const DEFAULT_TIMEOUT = 300;
  }
  ```

- [ ] **Return Types** → Überall explizit
- [ ] **Nullable Types** → Korrekte `?Type` Syntax

---

## 📝 **6. DTO & Validation Consistency**

### **❌ Gefundene Probleme:**
- **Inconsistent Validation-Attribute:**
  ```php
  // ❌ INKONSISTENT
  #[Assert\NotBlank(message: 'Path is required')]           // Englisch
  #[Assert\Length(max: 100)]                               // Ohne Message
  ```

### **🔧 Lösungsplan:**
- [ ] **Alle Validation-Messages** → Englisch
- [ ] **Standardisierte Messages** → `"{field} is required"`
- [ ] **Konsistente Groups** → `['api:read']`, `['api:write']`  
- [ ] **OpenAPI-Integration** → Vollständige Schema-Definitionen

---

## 🔒 **7. Error-Handling Vereinheitlichung**

### **❌ Gefundene Probleme:**
- **Verschiedene Exception-Handling Patterns**
- **Inkonsistente Error-Response-Formate**
- **Mixed Logging-Levels**

### **🔧 Lösungsplan:**
- [ ] **Standardisierte Exception-Klassen:**
  ```php
  namespace App\Exception;
  
  class ValidationException extends \InvalidArgumentException {}
  class ServiceUnavailableException extends \RuntimeException {}
  class TokenLimitExceededException extends \RuntimeException {}
  ```

- [ ] **Einheitliches Error-Response-Format:**
  ```json
  {
      "error": {
          "type": "validation_error",
          "message": "Request validation failed",
          "details": {...},
          "request_id": "req_123456",
          "timestamp": "2024-01-16T15:30:25Z"
      }
  }
  ```

- [ ] **Logging-Level-Standards:**
  - `debug()` → Development details
  - `info()` → Business events  
  - `warning()` → Recoverable issues
  - `error()` → System problems

---

## ⚡ **8. Performance-Optimierungen**

### **🔧 Lösungsplan:**
- [ ] **Service-Container-Optimierung:**
  ```yaml
  # services.yaml
  services:
      _defaults:
          autowire: true
          autoconfigure: true
          bind:
              $tokenLimit: '%env(int:TOKEN_LIMIT)%'
              $cacheTimeout: '%env(int:CACHE_TIMEOUT)%'
  ```

- [ ] **Caching-Layer:**
  ```php
  #[Cache(maxage: 3600, public: true)]
  public function getStatus(): JsonResponse
  ```

- [ ] **Database-Optimierung:**
  - Doctrine Query-Optimierung
  - Index-Definitionen
  - Connection-Pooling

---

## 🧪 **9. Testing-Standards**

### **🔧 Lösungsplan:**
- [ ] **PHPUnit 11** → Neueste Version nutzen
- [ ] **Test-Kategorien:**
  ```php
  #[TestDox('Should validate input and return proper response')]
  #[Group('integration')]
  public function testLlmControllerWithValidInput(): void
  ```

- [ ] **Mock-Standards** → Konsistente MockBuilder-Nutzung
- [ ] **Data-Providers** → Für parametrisierte Tests
- [ ] **Coverage-Ziel** → Mindestens 80% Code Coverage

---

## 🔧 **10. Development-Tools Integration**

### **🔧 Lösungsplan:**
- [ ] **PHP-CS-Fixer-Config:**
  ```php
  // .php-cs-fixer.php
  return (new PhpCsFixer\Config())
      ->setRiskyAllowed(true)
      ->setRules([
          '@Symfony' => true,
          '@PHP83Migration' => true,
          'declare_strict_types' => true,
      ]);
  ```

- [ ] **PHPStan-Config:**
  ```neon
  # phpstan.neon
  parameters:
      level: 8
      paths: [src]
      symfony:
          console_application_loader: bin/console
  ```

- [ ] **GitHub Actions** → Automated Code Quality Checks
- [ ] **Pre-Commit-Hooks** → Code-Style vor Commits

---

## 📋 **Prioritäten-Matrix**

| Priorität | Bereich | Aufwand | Impact |
|-----------|---------|---------|---------|
| **🔴 HOCH** | Sprachkonsistenz (Deutsch→Englisch) | Mittel | Hoch |
| **🔴 HOCH** | ApiPlatform-Attribute vervollständigen | Niedrig | Hoch |
| **🟡 MITTEL** | Constructor-Pattern vereinheitlichen | Niedrig | Mittel |
| **🟡 MITTEL** | Kommentar-Stil standardisieren | Mittel | Mittel |
| **🟢 NIEDRIG** | Performance-Optimierungen | Hoch | Niedrig |
| **🟢 NIEDRIG** | Testing-Standards etablieren | Hoch | Niedrig |

---

## 🚀 **Umsetzungsplan (Reihenfolge)**

### **Phase 1: Kritische Konsistenz (1-2 Tage)**
1. Deutsche Kommentare → Englische Kommentare
2. Alle Controller → `#[ApiResource]` hinzufügen
3. Constructor Property Promotion überall

### **Phase 2: Code-Qualität (2-3 Tage)**
4. PHPDoc-Standards implementieren
5. Konstanten für Magic Numbers definieren
6. Error-Handling vereinheitlichen

### **Phase 3: Performance & Tools (3-5 Tage)**  
7. Caching-Layer implementieren
8. Development-Tools konfigurieren
9. Testing-Standards etablieren

---

**🎯 Ziel: Einheitliche, wartbare, englischsprachige Symfony 7.3 Codebase nach modernen PHP 8.3 Standards!**
