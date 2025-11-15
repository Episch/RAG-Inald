<?php

namespace App\Tests\Service;

use App\Service\ToonFormatterService;
use App\Dto\Requirements\RequirementsGraphDto;
use App\Dto\Requirements\RequirementDto;
use App\Dto\Requirements\RoleDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für ToonFormatterService
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
            requirements: [$req1, $req2]
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert
        $this->assertStringContainsString('requirements[2]', $toon);
        $this->assertStringContainsString('REQ-001', $toon);
        $this->assertStringContainsString('REQ-002', $toon);
    }

    public function testEncodeWithRoles(): void
    {
        // Arrange
        $role = new RoleDto(
            id: 'ROLE-001',
            name: 'Product Owner',
            description: 'Manages product backlog',
            level: 'manager'
        );

        $graph = new RequirementsGraphDto(
            requirements: [],
            roles: [$role]
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert
        $this->assertStringContainsString('roles[1]', $toon);
        $this->assertStringContainsString('ROLE-001', $toon);
        $this->assertStringContainsString('Product Owner', $toon);
    }

    public function testEncodeWithRelationships(): void
    {
        // Arrange
        $graph = new RequirementsGraphDto(
            requirements: [],
            roles: [],
            environments: [],
            businesses: [],
            infrastructures: [],
            softwareApplications: [],
            relationships: [
                [
                    'type' => 'OWNED_BY',
                    'source' => 'REQ-001',
                    'target' => 'ROLE-001'
                ]
            ]
        );

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert
        $this->assertStringContainsString('relationships[1]', $toon);
        $this->assertStringContainsString('OWNED_BY', $toon);
        $this->assertStringContainsString('REQ-001', $toon);
        $this->assertStringContainsString('ROLE-001', $toon);
    }

    public function testEncodeEscapesCommas(): void
    {
        // Arrange
        $requirement = new RequirementDto(
            id: 'REQ-001',
            name: 'Login, Logout, and Registration',
            description: 'Multiple functions',
            type: 'functional',
            priority: 'high',
            status: 'approved',
            source: 'Doc'
        );

        $graph = new RequirementsGraphDto(requirements: [$requirement]);

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($graph);

        // Assert - Values with commas should be quoted
        $this->assertStringContainsString('"Login, Logout, and Registration"', $toon);
    }

    public function testDecodeSimpleToon(): void
    {
        // Arrange
        $toonInput = <<<TOON
requirements[2]{id,name,type,priority,status}:
  REQ-001,User Login,functional,high,approved
  REQ-002,User Logout,functional,medium,draft

roles[1]{id,name,level}:
  ROLE-001,Product Owner,manager

relationships[1]{type,source,target}:
  OWNED_BY,REQ-001,ROLE-001
TOON;

        // Act
        $result = $this->formatter->decode($toonInput);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('requirements', $result);
        $this->assertArrayHasKey('roles', $result);
        $this->assertArrayHasKey('relationships', $result);
        
        $this->assertCount(2, $result['requirements']);
        $this->assertEquals('REQ-001', $result['requirements'][0]['id']);
        $this->assertEquals('User Login', $result['requirements'][0]['name']);
        
        $this->assertCount(1, $result['roles']);
        $this->assertEquals('ROLE-001', $result['roles'][0]['id']);
        
        $this->assertCount(1, $result['relationships']);
        $this->assertEquals('OWNED_BY', $result['relationships'][0]['type']);
    }

    public function testDecodeWithQuotedValues(): void
    {
        // Arrange
        $toonInput = <<<TOON
requirements[1]{id,name,description}:
  REQ-001,"Login, Logout","Users can login, logout, and register"
TOON;

        // Act
        $result = $this->formatter->decode($toonInput);

        // Assert
        $this->assertEquals('Login, Logout', $result['requirements'][0]['name']);
        $this->assertEquals('Users can login, logout, and register', $result['requirements'][0]['description']);
    }

    public function testDecodeEmptyTable(): void
    {
        // Arrange
        $toonInput = "requirements[0]:";

        // Act
        $result = $this->formatter->decode($toonInput);

        // Assert
        $this->assertArrayHasKey('requirements', $result);
        $this->assertEmpty($result['requirements']);
    }

    public function testRoundTripEncodeDecode(): void
    {
        // Arrange
        $originalGraph = new RequirementsGraphDto(
            requirements: [
                new RequirementDto(
                    id: 'REQ-001',
                    name: 'Login',
                    description: 'User login',
                    type: 'functional',
                    priority: 'high',
                    status: 'approved',
                    source: 'Doc1'
                )
            ],
            roles: [
                new RoleDto(
                    id: 'ROLE-001',
                    name: 'Admin',
                    level: 'executive'
                )
            ],
            relationships: [
                ['type' => 'OWNED_BY', 'source' => 'REQ-001', 'target' => 'ROLE-001']
            ]
        );

        // Act - Encode to TOON
        $toon = $this->formatter->encodeRequirementsGraph($originalGraph);
        
        // Act - Decode back
        $decoded = $this->formatter->decode($toon);

        // Assert - Check data integrity
        $this->assertCount(1, $decoded['requirements']);
        $this->assertEquals('REQ-001', $decoded['requirements'][0]['id']);
        $this->assertEquals('Login', $decoded['requirements'][0]['name']);
        
        $this->assertCount(1, $decoded['roles']);
        $this->assertEquals('ROLE-001', $decoded['roles'][0]['id']);
        
        $this->assertCount(1, $decoded['relationships']);
        $this->assertEquals('OWNED_BY', $decoded['relationships'][0]['type']);
    }

    public function testGenerateExampleForPrompt(): void
    {
        // Act
        $example = $this->formatter->generateExampleForPrompt();

        // Assert
        $this->assertIsString($example);
        $this->assertStringContainsString('requirements[', $example);
        $this->assertStringContainsString('roles[', $example);
        $this->assertStringContainsString('relationships[', $example);
        $this->assertStringContainsString('REQ-001', $example);
        $this->assertStringContainsString('ROLE-001', $example);
    }

    public function testDecodeHandlesEscapedQuotes(): void
    {
        // Arrange
        $toonInput = <<<TOON
requirements[1]{id,name,description}:
  REQ-001,Test,"Description with ""quoted"" text"
TOON;

        // Act
        $result = $this->formatter->decode($toonInput);

        // Assert
        $this->assertEquals('Description with "quoted" text', $result['requirements'][0]['description']);
    }

    public function testEncodeEmptyGraph(): void
    {
        // Arrange
        $emptyGraph = new RequirementsGraphDto();

        // Act
        $toon = $this->formatter->encodeRequirementsGraph($emptyGraph);

        // Assert
        $this->assertEmpty($toon);
    }

    public function testDecodeHandlesNumericValues(): void
    {
        // Arrange
        $toonInput = <<<TOON
requirements[1]{id,priority,count}:
  REQ-001,5,42
TOON;

        // Act
        $result = $this->formatter->decode($toonInput);

        // Assert
        $this->assertSame(5, $result['requirements'][0]['priority']);
        $this->assertSame(42, $result['requirements'][0]['count']);
    }

    public function testDecodeHandlesBooleanValues(): void
    {
        // Arrange
        $toonInput = <<<TOON
requirements[1]{id,active,archived}:
  REQ-001,true,false
TOON;

        // Act
        $result = $this->formatter->decode($toonInput);

        // Assert
        $this->assertTrue($result['requirements'][0]['active']);
        $this->assertFalse($result['requirements'][0]['archived']);
    }
}

