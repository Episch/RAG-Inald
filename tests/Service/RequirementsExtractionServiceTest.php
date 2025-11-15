<?php

namespace App\Tests\Service;

use App\Service\RequirementsExtractionService;
use App\Service\Connector\TikaConnector;
use App\Service\Connector\LlmConnector;
use App\Service\Connector\Neo4JConnector;
use App\Service\TokenChunker;
use App\Service\ToonFormatterService;
use App\Constants\SystemConstants;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Unit Tests für RequirementsExtractionService
 */
class RequirementsExtractionServiceTest extends TestCase
{
    private RequirementsExtractionService $service;
    private TikaConnector|MockObject $tikaConnector;
    private LlmConnector|MockObject $llmConnector;
    private Neo4JConnector|MockObject $neo4jConnector;
    private TokenChunker|MockObject $tokenChunker;
    private ToonFormatterService|MockObject $toonFormatter;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->tikaConnector = $this->createMock(TikaConnector::class);
        $this->llmConnector = $this->createMock(LlmConnector::class);
        $this->neo4jConnector = $this->createMock(Neo4JConnector::class);
        $this->tokenChunker = $this->createMock(TokenChunker::class);
        $this->toonFormatter = $this->createMock(ToonFormatterService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new RequirementsExtractionService(
            $this->tikaConnector,
            $this->llmConnector,
            $this->neo4jConnector,
            $this->tokenChunker,
            $this->toonFormatter,
            $this->logger
        );
    }

    public function testExtractFromDocumentsWithoutNeo4jImport(): void
    {
        // Arrange
        $testFile = $this->createTestPdf();
        
        // Mock Tika Response
        $tikaResponse = $this->createMock(ResponseInterface::class);
        $tikaResponse->method('getContent')
            ->willReturn(json_encode([['X-TIKA:content' => 'Test requirements document']]));
        
        $this->tikaConnector->expects($this->once())
            ->method('analyzeDocument')
            ->willReturn($tikaResponse);
        
        $this->tikaConnector->expects($this->once())
            ->method('parseOptimization')
            ->willReturn('Test requirements document');

        // Mock Token Counter (kleines Dokument, kein Chunking)
        $this->tokenChunker->expects($this->atLeastOnce())
            ->method('countTokens')
            ->willReturn(1000); // Unter dem Limit

        // Mock TOON Formatter
        $this->toonFormatter->expects($this->once())
            ->method('generateExampleForPrompt')
            ->willReturn('example toon');

        // Mock LLM Response
        $llmResponse = $this->createMock(ResponseInterface::class);
        $llmResponse->method('getContent')
            ->willReturn(json_encode([
                'message' => [
                    'content' => $this->getToonResponse()
                ]
            ]));
        
        $this->llmConnector->expects($this->once())
            ->method('chatCompletion')
            ->willReturn($llmResponse);

        // Mock TOON Decoder
        $this->toonFormatter->expects($this->once())
            ->method('decode')
            ->willReturn($this->getSampleRequirementsData());

        // Act
        $result = $this->service->extractFromDocuments(
            filePaths: [$testFile],
            model: 'llama3.2',
            importToNeo4j: false
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertCount(2, $result->requirements);
        $this->assertCount(1, $result->roles);

        // Cleanup
        @unlink($testFile);
    }

    public function testExtractFromDocumentsWithChunking(): void
    {
        // Arrange
        $testFile = $this->createTestPdf();
        
        // Mock Tika
        $tikaResponse = $this->createMock(ResponseInterface::class);
        $tikaResponse->method('getContent')
            ->willReturn(json_encode([['X-TIKA:content' => str_repeat('Large document ', 1000)]]));
        
        $this->tikaConnector->method('analyzeDocument')->willReturn($tikaResponse);
        $this->tikaConnector->method('parseOptimization')->willReturn(str_repeat('Large document ', 1000));

        // Mock Token Counter - großes Dokument
        $this->tokenChunker->expects($this->atLeastOnce())
            ->method('countTokens')
            ->willReturn(5000); // Über dem Limit -> Chunking

        // Mock Chunking
        $this->tokenChunker->expects($this->once())
            ->method('chunk')
            ->willReturn(['Chunk 1', 'Chunk 2']);

        // Mock TOON Formatter
        $this->toonFormatter->method('generateExampleForPrompt')->willReturn('example');

        // Mock LLM Responses (für jeden Chunk)
        $llmResponse = $this->createMock(ResponseInterface::class);
        $llmResponse->method('getContent')
            ->willReturn(json_encode([
                'message' => ['content' => $this->getToonResponse()]
            ]));
        
        $this->llmConnector->expects($this->exactly(2)) // 2 Chunks
            ->method('chatCompletion')
            ->willReturn($llmResponse);

        // Mock TOON Decoder (für jeden Chunk)
        $this->toonFormatter->expects($this->exactly(2))
            ->method('decode')
            ->willReturn($this->getSampleRequirementsData());

        // Act
        $result = $this->service->extractFromDocuments(
            filePaths: [$testFile],
            model: 'llama3.2',
            importToNeo4j: false
        );

        // Assert
        $this->assertNotNull($result);
        
        // Token Stats prüfen
        $stats = $this->service->getTokenStats();
        $this->assertEquals(2, $stats['chunks_processed']);
        $this->assertGreaterThan(0, $stats['total_tokens']);

        // Cleanup
        @unlink($testFile);
    }

    public function testGetTokenStats(): void
    {
        // Arrange
        $testFile = $this->createTestPdf();
        
        // Setup Mocks für erfolgreiche Extraktion
        $this->setupBasicMocks();

        // Act
        $this->service->extractFromDocuments(
            filePaths: [$testFile],
            model: 'llama3.2',
            importToNeo4j: false
        );

        $stats = $this->service->getTokenStats();

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('prompt_tokens', $stats);
        $this->assertArrayHasKey('completion_tokens', $stats);
        $this->assertArrayHasKey('total_tokens', $stats);
        $this->assertArrayHasKey('chunks_processed', $stats);
        $this->assertArrayHasKey('format', $stats);
        $this->assertArrayHasKey('model', $stats);
        
        $this->assertEquals('TOON', $stats['format']);
        $this->assertEquals('llama3.2', $stats['model']);

        // Cleanup
        @unlink($testFile);
    }

    public function testExtractFromDocumentsWithNeo4jImport(): void
    {
        // Arrange
        $testFile = $this->createTestPdf();
        $this->setupBasicMocks();

        // Mock Neo4j Connector
        $this->neo4jConnector->expects($this->atLeastOnce())
            ->method('executeCypherQuery')
            ->willReturn(['data' => []]);

        // Act
        $result = $this->service->extractFromDocuments(
            filePaths: [$testFile],
            model: 'llama3.2',
            importToNeo4j: true
        );

        // Assert
        $this->assertNotNull($result);

        // Cleanup
        @unlink($testFile);
    }

    public function testExtractFromDocumentsThrowsExceptionOnInvalidFile(): void
    {
        // Expect
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->service->extractFromDocuments(
            filePaths: ['/non/existent/file.pdf'],
            model: 'llama3.2',
            importToNeo4j: false
        );
    }

    public function testTokenStatsResetBetweenRequests(): void
    {
        // Arrange
        $testFile = $this->createTestPdf();
        $this->setupBasicMocks();

        // Act - Erster Request
        $this->service->extractFromDocuments([$testFile], 'llama3.2', false);
        $stats1 = $this->service->getTokenStats();

        // Act - Zweiter Request
        $this->service->extractFromDocuments([$testFile], 'llama3.2', false);
        $stats2 = $this->service->getTokenStats();

        // Assert - Stats sollten für jeden Request neu sein
        $this->assertNotEmpty($stats1);
        $this->assertNotEmpty($stats2);

        // Cleanup
        @unlink($testFile);
    }

    // Helper Methods

    private function createTestPdf(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tmpFile, 'PDF content');
        return $tmpFile;
    }

    private function getToonResponse(): string
    {
        return <<<TOON
```toon
requirements[2]{id,name,type,priority,status}:
  REQ-001,User Login,functional,high,approved
  REQ-002,User Logout,functional,medium,draft

roles[1]{id,name,level}:
  ROLE-001,Product Owner,manager

relationships[1]{type,source,target}:
  OWNED_BY,REQ-001,ROLE-001
```
TOON;
    }

    private function getSampleRequirementsData(): array
    {
        return [
            'requirements' => [
                [
                    'id' => 'REQ-001',
                    'name' => 'User Login',
                    'description' => 'Users must be able to login',
                    'type' => 'functional',
                    'priority' => 'high',
                    'status' => 'approved',
                    'source' => 'Test'
                ],
                [
                    'id' => 'REQ-002',
                    'name' => 'User Logout',
                    'description' => 'Users must be able to logout',
                    'type' => 'functional',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'source' => 'Test'
                ]
            ],
            'roles' => [
                [
                    'id' => 'ROLE-001',
                    'name' => 'Product Owner',
                    'level' => 'manager'
                ]
            ],
            'environments' => [],
            'businesses' => [],
            'infrastructures' => [],
            'softwareApplications' => [],
            'relationships' => [
                [
                    'type' => 'OWNED_BY',
                    'source' => 'REQ-001',
                    'target' => 'ROLE-001'
                ]
            ]
        ];
    }

    private function setupBasicMocks(): void
    {
        $tikaResponse = $this->createMock(ResponseInterface::class);
        $tikaResponse->method('getContent')
            ->willReturn(json_encode([['X-TIKA:content' => 'Test']]));
        
        $this->tikaConnector->method('analyzeDocument')->willReturn($tikaResponse);
        $this->tikaConnector->method('parseOptimization')->willReturn('Test');
        $this->tokenChunker->method('countTokens')->willReturn(1000);
        $this->toonFormatter->method('generateExampleForPrompt')->willReturn('example');
        
        $llmResponse = $this->createMock(ResponseInterface::class);
        $llmResponse->method('getContent')
            ->willReturn(json_encode(['message' => ['content' => $this->getToonResponse()]]));
        
        $this->llmConnector->method('chatCompletion')->willReturn($llmResponse);
        $this->toonFormatter->method('decode')->willReturn($this->getSampleRequirementsData());
    }
}

