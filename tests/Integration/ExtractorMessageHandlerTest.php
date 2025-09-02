<?php

namespace App\Tests\Integration;

use App\Message\ExtractorMessage;
use App\MessageHandler\ExtractorMessageHandler;
use App\Service\Connector\TikaConnector;
use App\Tests\Mock\MockHttpClientService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExtractorMessageHandlerTest extends KernelTestCase
{
    private string $testDir;
    private string $testFile;

    protected function setUp(): void
    {
        self::bootKernel();
        
        // Create test directory structure that matches the original implementation
        $this->testDir = dirname(__DIR__, 2) . '/public/storage';
        $testSubDir = $this->testDir . '/test';
        
        if (!is_dir($testSubDir)) {
            mkdir($testSubDir, 0777, true);
        }
        
        $this->testFile = $testSubDir . '/Prozessorientierter_Bericht_-_David_Nagelschmidt.pdf';
        
        if (!is_file($this->testFile)) {
            file_put_contents($this->testFile, 'Mock PDF content for testing');
        }
    }

    protected function tearDown(): void
    {
        // Don't clean up - let the original file stay for other tests
    }

    /**
     * 🟢 POSITIVE: Test successful message handling with existing file
     */
    public function testMessageHandler_SuccessfulProcessing(): void
    {
        // Mock Tika response
        $tikaResponse = [
            [
                'X-TIKA:content' => 'This is extracted content from PDF document. It contains important information about the project.'
            ]
        ];

        $mockHttpClient = new MockHttpClientService([
            new MockResponse(json_encode($tikaResponse), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $tikaConnector = new TikaConnector($mockHttpClient);
        
        $handler = new ExtractorMessageHandler(
            new Finder(),
            $tikaConnector,
            __DIR__ . '/../../config/prompts/extraction.yaml'
        );

        $message = new ExtractorMessage('test');
        
        // Should not throw exception
        $result = $handler($message);
        $this->assertEquals(0, $result);
    }

    /**
     * 🔴 NEGATIVE: Test message handling with non-existent directory
     */
    public function testMessageHandler_InvalidDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path does not exist');

        $mockHttpClient = new MockHttpClientService();
        $tikaConnector = new TikaConnector($mockHttpClient);
        
        $handler = new ExtractorMessageHandler(
            new Finder(),
            $tikaConnector,
            __DIR__ . '/../../config/prompts/extraction.yaml'
        );

        $message = new ExtractorMessage('nonexistent_directory');
        $handler($message);
    }

    /**
     * 🔴 NEGATIVE: Test Tika connection failure
     */
    public function testMessageHandler_TikaConnectionFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to analyze document with Tika');

        $mockHttpClient = new MockHttpClientService([
            new MockResponse('Connection refused', [
                'http_code' => 503,
                'response_headers' => ['Content-Type' => 'text/plain']
            ])
        ]);

        $tikaConnector = new TikaConnector($mockHttpClient);
        
        $handler = new ExtractorMessageHandler(
            new Finder(),
            $tikaConnector,
            __DIR__ . '/../../config/prompts/extraction.yaml'
        );

        $message = new ExtractorMessage('test');
        $handler($message);
    }

    /**
     * 🔴 NEGATIVE: Test invalid Tika response format
     */
    public function testMessageHandler_InvalidTikaResponse(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON content from Tika');

        $mockHttpClient = new MockHttpClientService([
            new MockResponse('Invalid JSON{', [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $tikaConnector = new TikaConnector($mockHttpClient);
        
        $handler = new ExtractorMessageHandler(
            new Finder(),
            $tikaConnector,
            __DIR__ . '/../../config/prompts/extraction.yaml'
        );

        $message = new ExtractorMessage('test');
        $handler($message);
    }

    /**
     * 🟢 POSITIVE: Test prompt rendering integration
     */
    public function testMessageHandler_PromptRendering(): void
    {
        // Mock Tika response with meaningful content
        $tikaResponse = [
            [
                'X-TIKA:content' => 'Test document content for prompt rendering'
            ]
        ];

        $mockHttpClient = new MockHttpClientService([
            new MockResponse(json_encode($tikaResponse), [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json']
            ])
        ]);

        $tikaConnector = new TikaConnector($mockHttpClient);
        
        $handler = new ExtractorMessageHandler(
            new Finder(),
            $tikaConnector,
            __DIR__ . '/../../config/prompts/extraction.yaml'
        );

        $message = new ExtractorMessage('test');
        
        // Should complete without errors
        $result = $handler($message);
        $this->assertEquals(0, $result);
    }
}