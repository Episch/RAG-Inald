<?php

namespace App\Tests\Dto\Requirements;

use App\Dto\Requirements\RequirementDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für RequirementDto
 */
class RequirementDtoTest extends TestCase
{
    public function testCreateRequirementDto(): void
    {
        // Act
        $dto = new RequirementDto(
            id: 'REQ-001',
            name: 'User Authentication',
            description: 'System must authenticate users',
            type: 'functional',
            priority: 'critical',
            status: 'approved',
            source: 'Security Requirements v2.1'
        );

        // Assert
        $this->assertEquals('REQ-001', $dto->id);
        $this->assertEquals('User Authentication', $dto->name);
        $this->assertEquals('functional', $dto->type);
        $this->assertEquals('critical', $dto->priority);
        $this->assertEquals('approved', $dto->status);
    }

    public function testToArray(): void
    {
        // Arrange
        $dto = new RequirementDto(
            id: 'REQ-001',
            name: 'Test',
            description: 'Test desc',
            type: 'functional',
            priority: 'high',
            status: 'draft',
            source: 'Doc'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertEquals('REQ-001', $array['id']);
    }

    public function testFromArray(): void
    {
        // Arrange
        $data = [
            'id' => 'REQ-001',
            'name' => 'Test Requirement',
            'description' => 'Test description',
            'type' => 'functional',
            'priority' => 'high',
            'status' => 'approved',
            'source' => 'Document'
        ];

        // Act
        $dto = RequirementDto::fromArray($data);

        // Assert
        $this->assertInstanceOf(RequirementDto::class, $dto);
        $this->assertEquals('REQ-001', $dto->id);
        $this->assertEquals('Test Requirement', $dto->name);
        $this->assertEquals('functional', $dto->type);
    }

    public function testFromArrayWithMissingFields(): void
    {
        // Arrange
        $data = [
            'name' => 'Test',
            'description' => 'Desc'
        ];

        // Act
        $dto = RequirementDto::fromArray($data);

        // Assert
        $this->assertNotEmpty($dto->id); // Should generate ID
        $this->assertEquals('functional', $dto->type); // Default
        $this->assertEquals('medium', $dto->priority); // Default
        $this->assertEquals('draft', $dto->status); // Default
    }

    public function testWithOptionalFields(): void
    {
        // Act
        $dto = new RequirementDto(
            id: 'REQ-001',
            name: 'Test',
            description: 'Desc',
            type: 'functional',
            priority: 'high',
            status: 'approved',
            source: 'Doc',
            rationale: 'Important for security',
            acceptanceCriteria: 'Must pass all tests',
            dependencies: ['REQ-002', 'REQ-003'],
            tags: ['security', 'authentication']
        );

        // Assert
        $this->assertEquals('Important for security', $dto->rationale);
        $this->assertEquals('Must pass all tests', $dto->acceptanceCriteria);
        $this->assertCount(2, $dto->dependencies);
        $this->assertCount(2, $dto->tags);
    }
}

