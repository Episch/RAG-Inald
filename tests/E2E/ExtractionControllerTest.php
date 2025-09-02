<?php

namespace App\Tests\E2E;

use App\Tests\Mock\MockHttpClientService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class ExtractionControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Reset messenger transport after each test
        $transport = static::getContainer()->get('messenger.transport.async');
        if ($transport instanceof InMemoryTransport) {
            $transport->reset();
        }
    }

    /**
     * 🟢 POSITIVE: Test successful document extraction request
     */
    public function testExtractionEndpoint_SuccessfulRequest(): void
    {
        // Create test data
        $requestData = ['path' => 'test'];
        
        // Make POST request to extraction endpoint
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        // Assertions
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED); // 202 Accepted
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('sent_path', $response);
        $this->assertEquals('queued', $response['status']);
        $this->assertEquals('test', $response['sent_path']);
        
        // Verify message was queued
        $transport = static::getContainer()->get('messenger.transport.async');
        if ($transport instanceof InMemoryTransport) {
            $messages = $transport->getSent();
            $this->assertCount(1, $messages);
        }
    }

    /**
     * 🟢 POSITIVE: Test extraction request with valid directory path
     */
    public function testExtractionEndpoint_ValidDirectoryPath(): void
    {
        $requestData = ['path' => 'test/subdirectory'];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('queued', $response['status']);
        $this->assertEquals('test/subdirectory', $response['sent_path']);
    }

    /**
     * 🔴 NEGATIVE: Test extraction request with empty path
     */
    public function testExtractionEndpoint_EmptyPath(): void
    {
        $requestData = ['path' => ''];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        // Should return validation error (422 or 400)
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $response);
        
        // Check that validation failed for path field
        $violations = $response['violations'];
        $pathViolation = array_filter($violations, fn($v) => $v['propertyPath'] === 'path');
        $this->assertNotEmpty($pathViolation);
    }

    /**
     * 🔴 NEGATIVE: Test extraction request with path traversal attempt
     */
    public function testExtractionEndpoint_PathTraversalAttempt(): void
    {
        $requestData = ['path' => '../../../etc/passwd'];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        // Should return validation error due to invalid characters
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $response);
    }

    /**
     * 🔴 NEGATIVE: Test extraction request with invalid characters in path
     */
    public function testExtractionEndpoint_InvalidCharactersInPath(): void
    {
        $requestData = ['path' => 'test<script>alert("xss")</script>'];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $response);
        
        // Verify the validation message mentions invalid characters
        $violations = $response['violations'];
        $pathViolation = current(array_filter($violations, fn($v) => $v['propertyPath'] === 'path'));
        $this->assertStringContainsString('invalid characters', $pathViolation['message']);
    }

    /**
     * 🔴 NEGATIVE: Test extraction request with missing path field
     */
    public function testExtractionEndpoint_MissingPathField(): void
    {
        $requestData = []; // No path field
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $response);
        
        // Should have violation for required path field
        $violations = $response['violations'];
        $pathViolation = array_filter($violations, fn($v) => $v['propertyPath'] === 'path');
        $this->assertNotEmpty($pathViolation);
    }

    /**
     * 🔴 NEGATIVE: Test extraction request with invalid JSON
     */
    public function testExtractionEndpoint_InvalidJson(): void
    {
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json {');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('detail', $response);
        $this->assertStringContainsString('Syntax error', $response['detail']);
    }

    /**
     * 🔴 NEGATIVE: Test extraction request without Content-Type header
     */
    public function testExtractionEndpoint_MissingContentType(): void
    {
        $requestData = ['path' => 'test'];
        
        $this->client->request('POST', '/api/extraction', [], [], [], json_encode($requestData));
        
        // Should return 415 Unsupported Media Type or similar
        $this->assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    /**
     * 🟢 POSITIVE: Test extraction endpoint with queue count information
     */
    public function testExtractionEndpoint_QueueCountIncluded(): void
    {
        $requestData = ['path' => 'test'];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        // Should include queue_count (might be null if transport doesn't support it)
        $this->assertArrayHasKey('queue_count', $response);
    }

    /**
     * 🟢 POSITIVE: Test extraction endpoint response format
     */
    public function testExtractionEndpoint_ResponseFormat(): void
    {
        $requestData = ['path' => 'test'];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $content = $this->client->getResponse()->getContent();
        $this->assertJson($content);
        
        $response = json_decode($content, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        
        // Check required fields
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('sent_path', $response);
        $this->assertEquals('queued', $response['status']);
        $this->assertEquals('test', $response['sent_path']);
    }

    /**
     * 🟢 POSITIVE: Test multiple extraction requests are properly queued
     */
    public function testExtractionEndpoint_MultipleRequests(): void
    {
        $paths = ['test', 'test/dir1', 'test/dir2'];
        
        foreach ($paths as $path) {
            $requestData = ['path' => $path];
            
            $this->client->request('POST', '/api/extraction', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode($requestData));
            
            $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
            
            $response = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals('queued', $response['status']);
            $this->assertEquals($path, $response['sent_path']);
        }
        
        // Verify all messages were queued
        $transport = static::getContainer()->get('messenger.transport.async');
        if ($transport instanceof InMemoryTransport) {
            $messages = $transport->getSent();
            $this->assertCount(3, $messages);
        }
    }

    /**
     * 🔴 NEGATIVE: Test extraction request with very long path
     */
    public function testExtractionEndpoint_PathTooLong(): void
    {
        $longPath = str_repeat('a', 1000); // Very long path
        $requestData = ['path' => $longPath];
        
        $this->client->request('POST', '/api/extraction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));
        
        // Might be rejected by validation or server limits
        $this->assertThat(
            $this->client->getResponse()->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNPROCESSABLE_ENTITY),
                $this->equalTo(Response::HTTP_REQUEST_ENTITY_TOO_LARGE),
                $this->equalTo(Response::HTTP_BAD_REQUEST)
            )
        );
    }
}
