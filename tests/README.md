# 🧪 Testing Documentation

## Quick Links

- **[Quick Start](QUICKSTART_TESTS.md)** - Tests in 5 Minuten ausführen
- **[Detailed Guide](../docs/development/testing.md)** - Vollständige Test-Dokumentation

## Test ausführen

### Windows
```cmd
run-tests.bat
```

### Linux / macOS
```bash
php bin/phpunit
```

## Test-Struktur

```
tests/
├── Service/          # Service-Tests
├── Command/          # CLI-Command-Tests
├── Dto/              # DTO-Tests
├── README.md         # Diese Datei
└── QUICKSTART_TESTS.md  # Quick-Start-Guide
```

## Weitere Informationen

Siehe [Development Testing Guide](../docs/development/testing.md) für:
- Ausführliche Test-Dokumentation
- Coverage-Reports
- Test-Patterns
- CI/CD Integration

