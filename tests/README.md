# 🧪 Test Suite Documentation

## Overview

Comprehensive test suite for the RAG (Retrieval Augmented Generation) document processing API with positive and negative test scenarios.

## Test Structure

```
tests/
├── E2E/                    # End-to-End tests
│   ├── StatusControllerTest.php
│   └── ExtractionControllerTest.php
├── Integration/            # Integration tests
│   └── ExtractorMessageHandlerTest.php
├── Unit/                   # Unit tests
│   └── Service/
│       ├── PromptRendererTest.php
│       └── TokenChunkerTest.php
├── Mock/                   # Mock services
│   └── MockHttpClientService.php
├── bootstrap.php          # Test bootstrap
└── README.md              # This file
```

## Test Categories

### 🟢 Positive Test Scenarios
- ✅ Successful API requests with valid data
- ✅ Proper response formats and status codes
- ✅ Service availability checks
- ✅ Message queue processing
- ✅ File processing with allowed extensions
- ✅ Template rendering with various data types

### 🔴 Negative Test Scenarios
- ❌ Invalid input validation (empty paths, invalid characters)
- ❌ Security tests (path traversal attempts)
- ❌ Service unavailability simulation
- ❌ Malformed JSON responses
- ❌ Missing required fields
- ❌ Authentication/authorization failures
- ❌ Edge cases (very long paths, special characters)

## Running Tests

### All Tests
```bash
php bin/phpunit
```

### Specific Test Suites
```bash
# E2E Tests only
php bin/phpunit tests/E2E/

# Integration Tests only
php bin/phpunit tests/Integration/

# Unit Tests only
php bin/phpunit tests/Unit/
```

### Individual Test Files
```bash
# Status Controller E2E Tests
php bin/phpunit tests/E2E/StatusControllerTest.php

# Extraction Controller E2E Tests
php bin/phpunit tests/E2E/ExtractionControllerTest.php

# Message Handler Integration Tests
php bin/phpunit tests/Integration/ExtractorMessageHandlerTest.php
```

### With Coverage (if enabled)
```bash
php bin/phpunit --coverage-html var/coverage/
```

### Verbose Output
```bash
php bin/phpunit --verbose
php bin/phpunit --debug
```

## Test Configuration

### Environment Variables
Tests use mock services with the following environment variables:
- `DOCUMENT_EXTRACTOR_URL`: `http://mock-tika:9998`
- `NEO4J_RAG_DATABASE`: `http://mock-neo4j:7474`

### Test Database
Tests use in-memory transport for Messenger to avoid external dependencies.

## API Endpoints Tested

### 1. `/status` (GET)
**Purpose**: Check health status of external services (Tika, Neo4j)

**Positive Tests**:
- ✅ All services healthy
- ✅ Proper response format with service details

**Negative Tests**:
- ❌ Tika service down (503)
- ❌ Neo4j service down (503)
- ❌ Invalid JSON from Neo4j
- ❌ Empty responses
- ❌ Both services down

### 2. `/extraction` (POST)
**Purpose**: Queue document extraction jobs

**Positive Tests**:
- ✅ Valid extraction request with allowed path
- ✅ Queue count information included
- ✅ Multiple requests properly queued
- ✅ Subdirectory paths

**Negative Tests**:
- ❌ Empty path field
- ❌ Path traversal attempts (`../../../`)
- ❌ Invalid characters in path (`<script>`)
- ❌ Missing path field entirely
- ❌ Invalid JSON payload
- ❌ Missing Content-Type header
- ❌ Disallowed directories
- ❌ Extremely long paths

## Security Test Coverage

### 🛡️ Path Traversal Prevention
```php
// Tests that these attacks are blocked:
"../../../etc/passwd"
"./../../config/database.yml"
"test/../../../sensitive_file"
```

### 🛡️ Input Validation
```php
// Tests validation of:
- Special characters: `<script>alert("xss")</script>`
- SQL injection patterns
- Unicode edge cases
- Empty/null values
```

### 🛡️ Directory Whitelisting
```php
// Tests that only allowed directories work:
✅ "test"           // Allowed
✅ "uploads"        // Allowed  
❌ "forbidden"      // Blocked
❌ "system"         // Blocked
```

## Mock Services

### MockHttpClientService
Simulates external API responses for:
- **Tika Server**: Document extraction responses
- **Neo4j Database**: Status and version information
- **Connection Failures**: Network/service errors

### Usage Example
```php
$mockClient = new MockHttpClientService([
    new MockResponse('{"version": "2.9.0"}', ['http_code' => 200]),
    new MockResponse('Service Unavailable', ['http_code' => 503])
]);
```

## Test Data Management

### Temporary Files
Integration tests create temporary test files and directories:
```php
$testDir = sys_get_temp_dir() . '/rag_test_' . uniqid();
$testFile = $testDir . '/test/document.pdf';
```

### Cleanup
All tests clean up created files/directories in `tearDown()` methods.

## Continuous Integration

### Required Dependencies
```bash
composer install --dev
```

### CI Commands
```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Run test suite
php bin/phpunit --log-junit var/test-results.xml

# Generate coverage (requires xdebug)
php bin/phpunit --coverage-clover var/coverage.xml
```

## Test Metrics

- **Total Test Methods**: 35+
- **E2E Test Coverage**: 2 controllers, 15+ scenarios
- **Integration Tests**: Message handling, file processing
- **Unit Tests**: Service classes, utility functions
- **Security Tests**: Path traversal, input validation
- **Performance Tests**: Multiple requests, large payloads

## Adding New Tests

### E2E Test Template
```php
public function testEndpoint_PositiveScenario(): void
{
    $this->client->request('GET', '/endpoint');
    $this->assertResponseIsSuccessful();
    // Add assertions
}

public function testEndpoint_NegativeScenario(): void
{
    $this->client->request('GET', '/invalid-endpoint');
    $this->assertResponseStatusCodeSame(404);
    // Add assertions
}
```

### Integration Test Template
```php
public function testService_SuccessfulOperation(): void
{
    $service = $this->getContainer()->get(ServiceClass::class);
    $result = $service->process($validInput);
    $this->assertEquals($expectedOutput, $result);
}
```

## Troubleshooting

### Common Issues
1. **Messenger Transport**: Ensure `framework.test.yaml` configures in-memory transport
2. **Mock Responses**: Reset mock call indices between tests
3. **Temporary Files**: Check cleanup in `tearDown()` methods
4. **Environment Variables**: Verify test-specific ENV vars are set

### Debug Mode
```bash
php bin/phpunit --debug --verbose tests/E2E/StatusControllerTest.php::testMethod
```
