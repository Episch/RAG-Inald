<?php

namespace App\Tests\Service;

use App\Service\ToonFormatterService;
use App\Dto\Requirements\RequirementsGraphDto;
use App\Dto\Requirements\RequirementDto;
use App\Dto\Requirements\RoleDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für ToonFormatterService (mit helgesverre/toon-php)
 * 
 * Testet TOON-Encoding und -Decoding für Requirements-Daten
 */
class ToonFormatterServiceTest extends TestCase
{
    private ToonFormatterService $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ToonFormatterService();
    }

    public function testEncodeSimpleRequirementsGraph(): void
    {
        // Arrange
        $requirement = new RequirementDto(
            id: 'REQ-001',
            name: 'User Login',
            description: 'Users must be able to login',
            type: 'functional',
            priority: 'high',
            status: 'approved',
            source: 'Requirements Doc'
        );

        $graph = new RequirementsGraphDto(
            requirements: [$requirement],
            roles: [],
            environments: [],
            businesses: [],
            infrastructures: [],
            softwareApplications: [],
            relationships: []
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert
        $this->assertStringContainsString('requirements[1]', $toon);
        $this->assertStringContainsString('REQ-001', $toon);
        $this->assertStringContainsString('User Login', $toon);
        $this->assertStringContainsString('functional', $toon);
        $this->assertStringContainsString('high', $toon);
    }

    public function testEncodeMultipleRequirements(): void
    {
        // Arrange
        $req1 = new RequirementDto(
            id: 'REQ-001',
            name: 'Login',
            description: 'User login',
            type: 'functional',
            priority: 'high',
            status: 'approved',
            source: 'Doc1'
        );

        $req2 = new RequirementDto(
            id: 'REQ-002',
            name: 'Logout',
            description: 'User logout',
            type: 'functional',
            priority: 'medium',
            status: 'draft',
            source: 'Doc1'
        );

        $graph = new RequirementsGraphDto(
            requirements: [$req1, $req2],
            roles: [],
            environments: [],
            businesses: [],
            infrastructures: [],
            softwareApplications: [],
            relationships: []
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert
        $this->assertStringContainsString('requirements[2]', $toon);
        $this->assertStringContainsString('REQ-001', $toon);
        $this->assertStringContainsString('REQ-002', $toon);
    }

    public function testEncodeWithRolesAndRelationships(): void
    {
        // Arrange
        $requirement = new RequirementDto(
            id: 'REQ-001',
            name: 'Security Requirement',
            type: 'non-functional',
            priority: 'critical'
        );

        $role = new RoleDto(
            id: 'ROLE-001',
            name: 'Security Officer',
            level: 'manager'
        );

        $graph = new RequirementsGraphDto(
            requirements: [$requirement],
            roles: [$role],
            environments: [],
            businesses: [],
            infrastructures: [],
            softwareApplications: [],
            relationships: [
                ['type' => 'OWNED_BY', 'source' => 'REQ-001', 'target' => 'ROLE-001']
            ]
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert
        $this->assertStringContainsString('requirements[1]', $toon);
        $this->assertStringContainsString('roles[1]', $toon);
        $this->assertStringContainsString('relationships[1]', $toon);
        $this->assertStringContainsString('OWNED_BY', $toon);
    }

    public function testDecodeSimpleToon(): void
    {
        // Arrange
        $toonString = <<<TOON
requirements[1]{id,name,type}:
  REQ-001,Test Requirement,functional
TOON;

        // Act
        $decoded = $this->formatter->decode($toonString);

        // Assert
        $this->assertArrayHasKey('requirements', $decoded);
        $this->assertCount(1, $decoded['requirements']);
        $this->assertEquals('REQ-001', $decoded['requirements'][0]['id']);
        $this->assertEquals('Test Requirement', $decoded['requirements'][0]['name']);
    }

    public function testEncodeAndDecodeRoundtrip(): void
    {
        // Arrange
        $data = [
            'requirements' => [
                ['id' => 'REQ-001', 'name' => 'Login', 'type' => 'functional'],
                ['id' => 'REQ-002', 'name' => 'Logout', 'type' => 'functional']
            ],
            'roles' => [
                ['id' => 'ROLE-001', 'name' => 'Admin', 'level' => 'senior']
            ]
        ];

        // Act
        $encoded = $this->formatter->encode($data);
        $decoded = $this->formatter->decode($encoded);

        // Assert
        $this->assertArrayHasKey('requirements', $decoded);
        $this->assertCount(2, $decoded['requirements']);
        $this->assertEquals('REQ-001', $decoded['requirements'][0]['id']);
    }

    public function testGenerateExampleForPrompt(): void
    {
        // Act
        $example = $this->formatter->generateExampleForPrompt();

        // Assert
        $this->assertIsString($example);
        $this->assertStringContainsString('requirements[', $example);
        $this->assertStringContainsString('roles[', $example);
        $this->assertStringContainsString('REQ-001', $example);
    }

    public function testCompareWithJson(): void
    {
        // Arrange
        $data = [
            'requirements' => [
                ['id' => 'REQ-001', 'name' => 'Test', 'type' => 'functional'],
                ['id' => 'REQ-002', 'name' => 'Test2', 'type' => 'non-functional']
            ]
        ];

        // Act
        $comparison = $this->formatter->compareWithJson($data);

        // Assert
        $this->assertArrayHasKey('toon', $comparison);
        $this->assertArrayHasKey('json', $comparison);
        $this->assertArrayHasKey('savings', $comparison);
        $this->assertArrayHasKey('savings_percent', $comparison);
        
        // TOON sollte weniger Tokens nutzen als JSON
        $this->assertLessThan($comparison['json'], $comparison['toon']);
    }

    public function testEstimateTokens(): void
    {
        // Arrange
        $data = [
            'requirements' => [
                ['id' => 'REQ-001', 'name' => 'Test Requirement']
            ]
        ];

        // Act
        $tokens = $this->formatter->estimateTokens($data);

        // Assert
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEncodeReadable(): void
    {
        // Arrange
        $data = [
            'requirements' => [
                ['id' => 'REQ-001', 'name' => 'Test']
            ]
        ];

        // Act
        $readable = $this->formatter->encodeReadable($data);

        // Assert
        $this->assertIsString($readable);
        $this->assertStringContainsString('requirements', $readable);
    }

    public function testHandleEmptyGraph(): void
    {
        // Arrange
        $graph = new RequirementsGraphDto(
            requirements: [],
            roles: [],
            environments: [],
            businesses: [],
            infrastructures: [],
            softwareApplications: [],
            relationships: []
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert - Sollte leeren oder minimalen TOON-String zurückgeben
        $this->assertIsString($toon);
    }

    public function testHandleSpecialCharacters(): void
    {
        // Arrange
        $requirement = new RequirementDto(
            id: 'REQ-001',
            name: 'Test with "quotes" and, commas',
            description: 'Description with\nnewlines'
        );

        $graph = new RequirementsGraphDto(
            requirements: [$requirement],
            roles: [],
            environments: [],
            businesses: [],
            infrastructures: [],
            softwareApplications: [],
            relationships: []
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert - helgesverre/toon-php sollte das korrekt escapen
        $this->assertIsString($toon);
        $this->assertStringContainsString('REQ-001', $toon);
    }

    public function testDecodeHandlesInvalidToon(): void
    {
        // Arrange - ungültiges TOON
        $invalidToon = "this is not valid toon format";

        // Act & Assert - sollte nicht werfen, da lenient decode als Fallback
        $result = $this->formatter->decode($invalidToon);
        $this->assertIsArray($result);
    }
}
