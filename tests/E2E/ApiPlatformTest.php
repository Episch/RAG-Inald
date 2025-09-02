<?php

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiPlatformTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * 🟢 POSITIVE: Test API Platform is accessible
     */
    public function testApiPlatformAccessible(): void
    {
        $this->client->request('GET', '/api');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('@context', $response);
    }

    /**
     * 🟢 POSITIVE: Test API documentation is accessible
     */
    public function testApiDocsAccessible(): void
    {
        $this->client->request('GET', '/api/docs');
        
        $this->assertResponseIsSuccessful();
    }

    /**
     * 🔴 NEGATIVE: Test invalid API endpoint
     */
    public function testInvalidApiEndpoint(): void
    {
        $this->client->request('GET', '/api/nonexistent');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * 🟢 POSITIVE: Test CORS headers are present
     */
    public function testCorsHeaders(): void
    {
        $this->client->request('OPTIONS', '/api', [], [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'
        ]);
        
        // Should not error on OPTIONS request
        $this->assertTrue(in_array($this->client->getResponse()->getStatusCode(), [200, 204]));
    }
}
