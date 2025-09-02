<?php

namespace App\Tests\Mock;

use App\Service\HttpClientService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MockHttpClientService extends HttpClientService
{
    private array $mockedResponses = [];
    private int $callIndex = 0;

    public function __construct(array $mockedResponses = [])
    {
        $this->mockedResponses = $mockedResponses;
        $mockClient = new MockHttpClient($this->getResponseCallback());
        parent::__construct($mockClient);
    }

    private function getResponseCallback(): callable
    {
        return function (string $method, string $url, array $options) {
            $response = $this->mockedResponses[$this->callIndex] ?? new MockResponse('', ['http_code' => 404]);
            $this->callIndex++;
            return $response;
        };
    }

    public function addMockResponse(MockResponse $response): void
    {
        $this->mockedResponses[] = $response;
    }

    public function resetCallIndex(): void
    {
        $this->callIndex = 0;
    }
}
