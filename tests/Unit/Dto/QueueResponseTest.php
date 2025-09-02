<?php

namespace App\Tests\Unit\Dto;

use App\Dto\QueueResponse;
use PHPUnit\Framework\TestCase;

class QueueResponseTest extends TestCase
{
    public function testCreateLlmResponse(): void
    {
        $response = QueueResponse::createLlmResponse(
            requestId: 'llm_test_123',
            model: 'llama3.2',
            promptLength: 256,
            queueCount: 5,
            estimatedTime: '30 seconds'
        );

        $this->assertEquals('queued', $response->getStatus());
        $this->assertEquals('llm_test_123', $response->getRequestId());
        $this->assertEquals('llm', $response->getOperationType());
        $this->assertEquals(5, $response->getQueueCount());
        $this->assertEquals('30 seconds', $response->getEstimatedProcessingTime());
        $this->assertEquals('/api/llm/result/llm_test_123', $response->getResultAvailableAt());
        
        $requestData = $response->getRequestData();
        $this->assertEquals('llama3.2', $requestData['model']);
        $this->assertEquals(256, $requestData['prompt_tokens']);
        
        $metadata = $response->getMetadata();
        $this->assertEquals('LLM Generation', $metadata['pipeline']);
        $this->assertTrue($metadata['async_processing']);
        $this->assertEquals('Tokens calculated using tiktoken encoder', $metadata['token_info']);
        $this->assertEquals('Non-OpenAI models use GPT-3.5-turbo tokenizer for approximation', $metadata['tokenizer_note']);
    }

    public function testCreateExtractionResponse(): void
    {
        $response = QueueResponse::createExtractionResponse(
            requestId: 'ext_test_456',
            path: 'documents/test.pdf',
            queueCount: 3,
            estimatedTime: '15 seconds'
        );

        $this->assertEquals('queued', $response->getStatus());
        $this->assertEquals('ext_test_456', $response->getRequestId());
        $this->assertEquals('extraction', $response->getOperationType());
        $this->assertEquals(3, $response->getQueueCount());
        $this->assertEquals('15 seconds', $response->getEstimatedProcessingTime());
        $this->assertEquals('/api/extraction/result/ext_test_456', $response->getResultAvailableAt());
        
        $requestData = $response->getRequestData();
        $this->assertEquals('documents/test.pdf', $requestData['path']);
        $this->assertEquals('PDF', $requestData['document_type']);
        
        $metadata = $response->getMetadata();
        $this->assertEquals('Document Extraction → Tika Processing → LLM Categorization', $metadata['pipeline']);
        $this->assertIsArray($metadata['stages']);
        $this->assertTrue($metadata['async_processing']);
    }

    public function testToArray(): void
    {
        $response = QueueResponse::createLlmResponse(
            requestId: 'test_array_123',
            model: 'llama3.2',
            promptLength: 128,
            queueCount: 2
        );

        $array = $response->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('queued', $array['status']);
        $this->assertEquals('test_array_123', $array['requestId']);
        $this->assertEquals('llm', $array['operationType']);
        $this->assertEquals(2, $array['queueCount']);
        $this->assertArrayHasKey('estimatedProcessingTime', $array);
        $this->assertArrayHasKey('resultAvailableAt', $array);
        $this->assertArrayHasKey('requestData', $array);
        $this->assertArrayHasKey('metadata', $array);
        
        // Test that requestData contains token count instead of string length
        $this->assertArrayHasKey('prompt_tokens', $array['requestData']);
        $this->assertEquals(128, $array['requestData']['prompt_tokens']);
    }

    public function testJsonSerializable(): void
    {
        $response = QueueResponse::createExtractionResponse(
            requestId: 'json_test_456',
            path: 'test.pdf',
            queueCount: 1
        );

        // Test that JsonSerializable works correctly
        $jsonString = json_encode($response);
        $this->assertJson($jsonString);
        
        $decodedArray = json_decode($jsonString, true);
        $this->assertEquals('queued', $decodedArray['status']);
        $this->assertEquals('json_test_456', $decodedArray['requestId']);
        $this->assertEquals('extraction', $decodedArray['operationType']);
        $this->assertEquals(1, $decodedArray['queueCount']);
    }

    public function testDocumentTypeGuessing(): void
    {
        $pdfResponse = QueueResponse::createExtractionResponse('test', 'document.pdf');
        $docResponse = QueueResponse::createExtractionResponse('test', 'document.docx');
        $txtResponse = QueueResponse::createExtractionResponse('test', 'document.txt');
        $mdResponse = QueueResponse::createExtractionResponse('test', 'README.md');
        $unknownResponse = QueueResponse::createExtractionResponse('test', 'document.xyz');
        
        $this->assertEquals('PDF', $pdfResponse->getRequestData()['document_type']);
        $this->assertEquals('Word Document', $docResponse->getRequestData()['document_type']);
        $this->assertEquals('Text File', $txtResponse->getRequestData()['document_type']);
        $this->assertEquals('Markdown', $mdResponse->getRequestData()['document_type']);
        $this->assertEquals('Unknown', $unknownResponse->getRequestData()['document_type']);
    }

    public function testFluentInterface(): void
    {
        $response = QueueResponse::createGeneric('test', 'custom')
            ->setQueueCount(10)
            ->setEstimatedProcessingTime('5 minutes')
            ->addMetadata('custom_field', 'custom_value');
        
        $this->assertEquals(10, $response->getQueueCount());
        $this->assertEquals('5 minutes', $response->getEstimatedProcessingTime());
        $this->assertEquals('custom_value', $response->getMetadata()['custom_field']);
    }
}
