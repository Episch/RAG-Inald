<?php

namespace App\Tests\E2E;

use App\Service\Connector\Neo4JConnector;
use App\Service\Connector\TikaConnector;
use App\Tests\Mock\MockHttpClientService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

class StatusControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * 🟢 POSITIVE: Test status endpoint structure (without mocking external services)
     */
    public function testStatusEndpoint_BasicStructure(): void
    {
        // Make request without mocking - will show real connection attempts
        $this->client->request('GET', '/api/status');
        
        // Should return 200 even if external services are down
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        // Verify response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertIsArray($response['status']);
        $this->assertCount(2, $response['status']);
        
        // Check each service has required fields
        foreach ($response['status'] as $service) {
            $this->assertArrayHasKey('service', $service);
            $this->assertArrayHasKey('content', $service);
            $this->assertArrayHasKey('status_code', $service);
            $this->assertArrayHasKey('healthy', $service);
            $this->assertIsString($service['service']);
            $this->assertIsString($service['content']);
            $this->assertIsInt($service['status_code']);
            $this->assertIsBool($service['healthy']);
        }
        
        // Verify service names
        $serviceNames = array_column($response['status'], 'service');
        $this->assertContains('DocumentConnector', $serviceNames);
        $this->assertContains('RagConnector', $serviceNames);
    }

    /**
     * 🟢 POSITIVE: Test status endpoint handles service failures gracefully  
     */
    public function testStatusEndpoint_ServiceFailureHandling(): void
    {
        // Test without mocking - external services may be down, that's OK
        $this->client->request('GET', '/api/status');
        
        // Should always return 200 regardless of external service status
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        // Should have proper error handling in response
        $this->assertArrayHasKey('status', $response);
        $this->assertIsArray($response['status']);
        
        foreach ($response['status'] as $service) {
            // All services should have these fields regardless of their health
            $this->assertArrayHasKey('service', $service);
            $this->assertArrayHasKey('status_code', $service); 
            $this->assertArrayHasKey('healthy', $service);
            $this->assertArrayHasKey('content', $service);
            
            // Status code should be reasonable (200 for success, 503 for down services)
            $this->assertTrue(
                in_array($service['status_code'], [200, 503]), 
                "Status code should be 200 or 503, got: " . $service['status_code']
            );
            
            // Healthy should match status code
            if ($service['status_code'] >= 200 && $service['status_code'] < 300) {
                $this->assertTrue($service['healthy'], "Service should be healthy with 2xx status");
            } else {
                $this->assertFalse($service['healthy'], "Service should be unhealthy with non-2xx status");
            }
        }
    }

    /**
     * 🟢 POSITIVE: Test status endpoint JSON format
     */
    public function testStatusEndpoint_JsonFormat(): void
    {
        $this->client->request('GET', '/api/status');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $content = $this->client->getResponse()->getContent();
        $this->assertJson($content);
        
        $response = json_decode($content, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
    }

    /**
     * 🔴 NEGATIVE: Test status endpoint with invalid HTTP method  
     */
    public function testStatusEndpoint_InvalidMethod(): void
    {
        $this->client->request('POST', '/api/status');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * 🔴 NEGATIVE: Test invalid status endpoint path
     */
    public function testStatusEndpoint_InvalidPath(): void
    {
        $this->client->request('GET', '/api/status/invalid');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
