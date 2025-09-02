<?php
// src/Service/HttpClientService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpClientService
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function get(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('POST', $url, $options);
    }

    public function put(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('PUT', $url, $options);
    }

    public function delete(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('DELETE', $url, $options);
    }

    public function patch(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('PATCH', $url, $options);
    }

    public function head(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('HEAD', $url, $options);
    }

    public function options(string $url, array $options = []): ResponseInterface
    {
        return $this->client->request('OPTIONS', $url, $options);
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $url, $options);
    }

    /**
     * Get supported HTTP methods
     */
    public function getSupportedMethods(): array
    {
        return ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
    }
}
