<?php

/**
 * Beispiel-Script für Requirements-Extraktion
 * 
 * Dieses Script zeigt verschiedene Verwendungsszenarien der Requirements-Pipeline.
 * 
 * Ausführung:
 *   php examples/requirements_extraction_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Message\RequirementsMessage;
use App\Service\RequirementsExtractionService;
use Symfony\Component\Dotenv\Dotenv;

// Bootstrap Symfony
(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== Requirements-Extraktion Beispiele ===\n\n";

// ============================================================================
// Beispiel 1: Synchrone Extraktion einer einzelnen Datei
// ============================================================================

echo "Beispiel 1: Einzelne Datei synchron verarbeiten\n";
echo "------------------------------------------------\n";

$extractionService = $container->get(RequirementsExtractionService::class);

try {
    $filePath = __DIR__ . '/../public/storage/test/sample-requirements.pdf';
    
    if (!file_exists($filePath)) {
        echo "⚠️  Beispiel-Datei nicht gefunden: {$filePath}\n";
        echo "   Bitte erstellen Sie eine Test-PDF mit Requirements.\n\n";
    } else {
        echo "📄 Verarbeite: " . basename($filePath) . "\n";
        
        $requirementsGraph = $extractionService->extractFromDocuments(
            filePaths: [$filePath],
            model: 'llama3.2',
            importToNeo4j: false // Nicht direkt importieren in diesem Beispiel
        );
        
        echo "✅ Erfolgreich extrahiert!\n";
        echo "   - Requirements: " . count($requirementsGraph->requirements) . "\n";
        echo "   - Roles: " . count($requirementsGraph->roles) . "\n";
        echo "   - Environments: " . count($requirementsGraph->environments) . "\n";
        echo "   - Relationships: " . count($requirementsGraph->relationships) . "\n\n";
        
        // Zeige erstes Requirement
        if (!empty($requirementsGraph->requirements)) {
            $firstReq = $requirementsGraph->requirements[0];
            $reqData = is_array($firstReq) ? $firstReq : $firstReq->toArray();
            
            echo "📋 Beispiel-Requirement:\n";
            echo "   ID: {$reqData['id']}\n";
            echo "   Name: {$reqData['name']}\n";
            echo "   Type: {$reqData['type']}\n";
            echo "   Priority: {$reqData['priority']}\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Fehler: {$e->getMessage()}\n\n";
}

// ============================================================================
// Beispiel 2: Asynchrone Verarbeitung mehrerer Dateien
// ============================================================================

echo "Beispiel 2: Mehrere Dateien asynchron verarbeiten\n";
echo "--------------------------------------------------\n";

$messageBus = $container->get('messenger.default_bus');

try {
    $filePaths = [
        __DIR__ . '/../public/storage/test/requirements-v1.pdf',
        __DIR__ . '/../public/storage/test/requirements-v2.pdf',
    ];
    
    // Filter existierende Dateien
    $existingFiles = array_filter($filePaths, 'file_exists');
    
    if (empty($existingFiles)) {
        echo "⚠️  Keine Test-Dateien gefunden.\n";
        echo "   Erstellen Sie Test-PDFs in: public/storage/test/\n\n";
    } else {
        echo "📦 Sende " . count($existingFiles) . " Dateien in die Queue...\n";
        
        $message = new RequirementsMessage(
            filePaths: $existingFiles,
            model: 'llama3.2',
            importToNeo4j: true,
            saveAsFile: true,
            requestId: uniqid('example_')
        );
        
        $messageBus->dispatch($message);
        
        echo "✅ Message dispatched! Request-ID: {$message->getRequestId()}\n";
        echo "   Starte Worker mit: php bin/console messenger:consume async -vv\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Fehler: {$e->getMessage()}\n\n";
}

// ============================================================================
// Beispiel 3: Neo4j-Connector IRREB-Methoden
// ============================================================================

echo "Beispiel 3: Neo4j IRREB-Relationships erstellen\n";
echo "------------------------------------------------\n";

use App\Service\Connector\Neo4JConnector;

try {
    $neo4j = $container->get(Neo4JConnector::class);
    
    echo "📊 Teste Neo4j-Verbindung...\n";
    $status = $neo4j->getServiceInfo();
    
    if ($status['healthy']) {
        echo "✅ Neo4j verbunden! Version: {$status['version']}\n\n";
        
        // Beispiel-Daten (würden normalerweise aus extrahierten Requirements kommen)
        echo "🔗 Erstelle Beispiel-Relationships...\n";
        
        // OWNED_BY
        try {
            $neo4j->createOwnedByRelationship('REQ-001', 'ROLE-001');
            echo "   ✓ OWNED_BY: REQ-001 → ROLE-001\n";
        } catch (\Exception $e) {
            echo "   ⚠️  OWNED_BY bereits vorhanden oder Nodes fehlen\n";
        }
        
        // APPLIES_TO
        try {
            $neo4j->createAppliesToRelationship('REQ-001', 'ENV-001');
            echo "   ✓ APPLIES_TO: REQ-001 → ENV-001\n";
        } catch (\Exception $e) {
            echo "   ⚠️  APPLIES_TO bereits vorhanden oder Nodes fehlen\n";
        }
        
        // Indizes erstellen
        echo "\n📑 Erstelle Neo4j-Indizes für Performance...\n";
        $indexResults = $neo4j->createIrrebIndexes();
        echo "   ✓ " . count($indexResults) . " Indizes erstellt/aktualisiert\n\n";
        
        // Requirements mit Relationships abrufen
        echo "🔍 Lade Requirements mit Beziehungen...\n";
        try {
            $requirements = $neo4j->findRequirementsWithRelationships(limit: 10);
            $count = count($requirements['data'] ?? []);
            echo "   ✓ {$count} Requirements gefunden\n\n";
        } catch (\Exception $e) {
            echo "   ⚠️  Noch keine Requirements in der Datenbank\n\n";
        }
        
    } else {
        echo "❌ Neo4j nicht erreichbar!\n";
        echo "   URL: " . ($_ENV['NEO4J_RAG_DATABASE'] ?? 'nicht konfiguriert') . "\n";
        echo "   Fehler: " . ($status['error'] ?? 'Unbekannt') . "\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Neo4j-Fehler: {$e->getMessage()}\n\n";
}

// ============================================================================
// Beispiel 4: DTO-Verwendung
// ============================================================================

echo "Beispiel 4: DTOs manuell erstellen und verwenden\n";
echo "------------------------------------------------\n";

use App\Dto\Requirements\RequirementDto;
use App\Dto\Requirements\RoleDto;
use App\Dto\Requirements\RequirementsGraphDto;

try {
    // Requirement erstellen
    $requirement = new RequirementDto(
        id: 'REQ-EXAMPLE-001',
        name: 'User Authentication',
        description: 'System must provide secure user authentication',
        type: 'functional',
        priority: 'critical',
        status: 'approved',
        source: 'Security Requirements Document',
        rationale: 'Protect user data and system access',
        acceptanceCriteria: 'Users can login with username and password, MFA supported'
    );
    
    // Role erstellen
    $role = new RoleDto(
        id: 'ROLE-EXAMPLE-001',
        name: 'Security Officer',
        description: 'Responsible for security requirements',
        responsibilities: ['Define security policies', 'Review security requirements'],
        level: 'manager'
    );
    
    // Graph erstellen
    $graph = new RequirementsGraphDto(
        requirements: [$requirement],
        roles: [$role],
        relationships: [
            [
                'type' => 'OWNED_BY',
                'source' => 'REQ-EXAMPLE-001',
                'target' => 'ROLE-EXAMPLE-001'
            ]
        ]
    );
    
    echo "✅ DTOs erstellt:\n";
    echo "   - " . count($graph->requirements) . " Requirements\n";
    echo "   - " . count($graph->roles) . " Roles\n";
    echo "   - " . count($graph->relationships) . " Relationships\n\n";
    
    // Als Array exportieren
    $graphArray = $graph->toArray();
    echo "📄 Graph als Array:\n";
    echo json_encode($graphArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
} catch (\Exception $e) {
    echo "❌ Fehler: {$e->getMessage()}\n\n";
}

// ============================================================================
// Zusammenfassung
// ============================================================================

echo "=== Zusammenfassung ===\n\n";
echo "Die Requirements-Pipeline bietet:\n";
echo "  ✓ Automatische Text-Extraktion aus PDF/Excel (Tika)\n";
echo "  ✓ KI-basierte Requirements-Analyse (Ollama)\n";
echo "  ✓ IRREB + schema.org konforme Struktur\n";
echo "  ✓ Neo4j-Integration mit Beziehungen\n";
echo "  ✓ Asynchrone Verarbeitung über Message Queue\n";
echo "  ✓ CLI-Command für einfache Bedienung\n\n";

echo "Nächste Schritte:\n";
echo "  1. Command ausführen: php bin/console app:process-requirements --help\n";
echo "  2. Test-PDF hochladen: public/storage/test/\n";
echo "  3. Worker starten: php bin/console messenger:consume async\n";
echo "  4. Neo4j Browser öffnen: http://localhost:7474\n\n";

echo "Dokumentation: docs/REQUIREMENTS_PIPELINE.md\n\n";

$kernel->shutdown();

