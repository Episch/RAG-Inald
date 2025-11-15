# Tests - Quick Start

## 🚀 Tests schnell ausführen

### Windows

```cmd
# Alle Tests
run-tests.bat

# Nur Service-Tests
run-tests.bat service

# Nur TOON-Formatter Tests
run-tests.bat toon

# Mit Coverage
run-tests.bat coverage
```

### Linux / macOS

```bash
# Alle Tests
./bin/run-tests.sh

# Oder direkt mit PHPUnit
php bin/phpunit
```

## 📋 Verfügbare Tests

### 1. ToonFormatterServiceTest
**Testet:** TOON Encoding/Decoding

```bash
php bin/phpunit tests/Service/ToonFormatterServiceTest.php
```

**Was wird getestet:**
- ✅ Encoding von Requirements zu TOON
- ✅ Decoding von TOON zurück zu Arrays
- ✅ Escaping von Sonderzeichen
- ✅ Round-Trip (Encode → Decode → gleiche Daten)
- ✅ Numerische und Boolean Werte

### 2. RequirementsExtractionServiceTest
**Testet:** Haupt-Extraktions-Service

```bash
php bin/phpunit tests/Service/RequirementsExtractionServiceTest.php
```

**Was wird getestet:**
- ✅ Extraktion aus Dokumenten
- ✅ Token-Chunking für große Dokumente
- ✅ Token-Statistiken
- ✅ Neo4j-Import
- ✅ Fehlerbehandlung

### 3. RequirementDtoTest
**Testet:** DTO-Klassen

```bash
php bin/phpunit tests/Dto/Requirements/RequirementDtoTest.php
```

**Was wird getestet:**
- ✅ DTO Creation
- ✅ toArray() / fromArray()
- ✅ Default-Werte
- ✅ Optionale Felder

## 🎯 Test-Output verstehen

### Erfolgreicher Test
```
PHPUnit 11.0.0 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.0
Configuration: phpunit.xml.dist

...........                                                       11 / 11 (100%)

Time: 00:00.234, Memory: 10.00 MB

OK (11 tests, 45 assertions)
```

### Fehlgeschlagener Test
```
F

Time: 00:00.123, Memory: 8.00 MB

There was 1 failure:

1) App\Tests\Service\ToonFormatterServiceTest::testEncodeSimpleRequirementsGraph
Failed asserting that 'actual' contains "expected".

/path/to/test.php:42

FAILURES!
Tests: 11, Assertions: 44, Failures: 1.
```

## 🔍 Debugging

### Einzelnen Test debuggen

```bash
php bin/phpunit --filter testEncodeSimpleRequirementsGraph
```

### Mit detaillierter Ausgabe

```bash
php bin/phpunit -v
php bin/phpunit -vv
php bin/phpunit --debug
```

### Bei erstem Fehler stoppen

```bash
php bin/phpunit --stop-on-failure
```

## 📊 Coverage

### Coverage-Report generieren

```bash
# Windows
run-tests.bat coverage

# Linux/macOS
./bin/run-tests.sh coverage

# Oder direkt
set XDEBUG_MODE=coverage
php bin/phpunit --coverage-html coverage/
```

**Report öffnen:** `coverage/index.html`

## ⚙️ Konfiguration

Tests werden durch `phpunit.xml.dist` konfiguriert:

```xml
<phpunit>
    <testsuites>
        <testsuite name="Requirements Pipeline">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## 🐛 Probleme lösen

### Problem: "Class not found"

```bash
composer dump-autoload
```

### Problem: Tests laufen nicht

```bash
# Prüfe PHP Version
php -v  # Sollte >= 8.2 sein

# Prüfe PHPUnit
php bin/phpunit --version
```

### Problem: "No tests executed"

```bash
# Prüfe ob Testdateien existieren
dir tests /s /b
```

## 📚 Test-Struktur

```
tests/
├── Service/
│   ├── ToonFormatterServiceTest.php          ← TOON Tests
│   └── RequirementsExtractionServiceTest.php ← Main Service
├── Command/
│   └── ProcessRequirementsCommandTest.php    ← CLI Tests
├── Dto/
│   └── Requirements/
│       └── RequirementDtoTest.php            ← DTO Tests
└── README_TESTS.md                           ← Dokumentation
```

## 🎓 Test schreiben

### Neuen Test erstellen

```php
<?php
namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testSomething(): void
    {
        // Arrange - Vorbereitung
        $service = new MyService();
        
        // Act - Aktion
        $result = $service->doSomething();
        
        // Assert - Prüfung
        $this->assertEquals('expected', $result);
    }
}
```

### Test ausführen

```bash
php bin/phpunit tests/Service/MyServiceTest.php
```

## 📈 Best Practices

1. **AAA-Pattern:** Arrange → Act → Assert
2. **Ein Test = Ein Konzept**
3. **Aussagekräftige Namen:** `testEncodeSimpleRequirementsGraph`
4. **Mocks für externe Services**
5. **Cleanup nach Tests**

## 🚀 CI/CD Integration

Die Tests sind bereit für Continuous Integration:

### GitHub Actions

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: php-actions/composer@v6
      - run: php bin/phpunit
```

## 📞 Support

Bei Problemen:
1. Prüfe `tests/README_TESTS.md`
2. Schaue dir bestehende Tests an
3. Führe Tests mit `-vv` aus für mehr Details

