<?php

namespace App\Service\Connector;

use App\Service\HttpClientService;
use App\Contract\ConnectorInterface;
use App\Exception\LlmException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class LlmConnector implements ConnectorInterface
{
    private HttpClientService $httpClient;
    private string $llmBaseUrl;

    public function __construct(HttpClientService $httpClient)
    {
        $this->httpClient = $httpClient;
        
        // 🔒 Environment variable validation with fallback
        $llmUrl = $_ENV['LMM_URL'] ?? '';
        if (empty($llmUrl)) {
            // 🚨 Fallback to localhost Ollama default - log warning
            $llmUrl = 'http://localhost:11434';
            error_log('⚠️ LMM_URL not set, using default: ' . $llmUrl);
        }
        
        $this->llmBaseUrl = rtrim($llmUrl, '/');
    }

    public function getStatus(): ResponseInterface
    {
        try {
            return $this->httpClient->get($this->llmBaseUrl . '/api/version');
        } catch (TransportExceptionInterface $e) {
            throw LlmException::connectionFailed($this->llmBaseUrl);
        }
    }

    public function getServiceInfo(): array
    {
        try {
            $response = $this->getStatus();
            $content = json_decode($response->getContent(), true);
            $models = $this->getAvailableModelNames();
            
            return [
                'name' => 'Ollama LLM',
                'version' => $content['version'] ?? 'unknown',
                'status_code' => $response->getStatusCode(),
                'healthy' => $response->getStatusCode() === 200,
                'models' => $models,
                'models_count' => count($models)
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Ollama LLM',
                'version' => 'unknown',
                'status_code' => 503,
                'healthy' => false,
                'error' => $e->getMessage(),
                'models' => []
            ];
        }
    }

    /**
     * Get available models from Ollama
     */
    public function getModels(): ResponseInterface
    {
        try {
            return $this->httpClient->get($this->llmBaseUrl . '/api/tags');
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to get models from LLM: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Debug method to check available endpoints
     */
    public function debugAvailableEndpoints(): array
    {
        $endpoints = [
            'version' => ['path' => '/api/version', 'method' => 'GET'],
            'tags' => ['path' => '/api/tags', 'method' => 'GET'], 
            'generate' => ['path' => '/api/generate', 'method' => 'POST'],
            'chat' => ['path' => '/api/chat', 'method' => 'POST'],
            'pull' => ['path' => '/api/pull', 'method' => 'POST'],
            'show' => ['path' => '/api/show', 'method' => 'POST']
        ];
        
        $results = [];
        foreach ($endpoints as $name => $config) {
            try {
                $path = $config['path'];
                $method = $config['method'];
                
                if ($method === 'GET') {
                    $response = $this->httpClient->get($this->llmBaseUrl . $path);
                } else {
                    // For POST endpoints, we'll just check if they respond (might get 400 for missing payload, but that's OK)
                    $response = $this->httpClient->post($this->llmBaseUrl . $path, [
                        'json' => [], // Empty payload for check
                        'timeout' => 5 // Short timeout for quick check
                    ]);
                }
                
                $results[$name] = [
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->getStatusCode(),
                    'available' => true
                ];
            } catch (\Exception $e) {
                $statusCode = 0;
                // Try to extract HTTP status code from the exception message
                if (preg_match('/HTTP\/1\.[01]\s+(\d{3})/', $e->getMessage(), $matches)) {
                    $statusCode = (int)$matches[1];
                }
                
                $results[$name] = [
                    'method' => $config['method'],
                    'path' => $config['path'],
                    'status' => $statusCode ?: ($e->getCode() ?: 500),
                    'available' => in_array($statusCode, [200, 400, 405]), // 400/405 means endpoint exists but bad request
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Generate text using Ollama /api/generate endpoint
     * 
     * @param string $prompt The input prompt
     * @param string $model Model name (default: llama3.2)
     * @param array $options Additional options (temperature, max_tokens, etc.)
     */
    public function generateText(string $prompt, string $model = 'llama3.2', array $options = []): ResponseInterface
    {
        if (empty($prompt)) {
            throw new \InvalidArgumentException('Prompt cannot be empty');
        }

        // 🔧 Check if model exists first, use a fallback if not
        $availableModels = $this->getAvailableModelNames();
        if (!empty($availableModels) && !in_array($model, $availableModels)) {
            $fallbackModel = $availableModels[0] ?? 'llama3.2';
            error_log("⚠️ Model '{$model}' not found. Using fallback: '{$fallbackModel}'. Available models: " . implode(', ', $availableModels));
            $model = $fallbackModel;
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false, // We want complete response, not streaming
            'options' => array_merge([
                'temperature' => 0.7,
                'num_predict' => 2048, // max tokens to generate
            ], $options)
        ];

        error_log("🚀 LLM Request: " . json_encode(['url' => $this->llmBaseUrl . '/api/generate', 'model' => $model, 'prompt_length' => strlen($prompt)]));

        try {
            $response = $this->httpClient->post($this->llmBaseUrl . '/api/generate', [
                'json' => $payload,
                'timeout' => 300, // 5 minutes timeout for LLM generation
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
            
            error_log("✅ LLM Response: " . $response->getStatusCode());
            return $response;
            
        } catch (TransportExceptionInterface $e) {
            // Enhanced error with more context
            $errorMsg = "Failed to generate text with LLM - URL: {$this->llmBaseUrl}/api/generate, Model: {$model}, Error: " . $e->getMessage();
            error_log("❌ " . $errorMsg);
            throw new \RuntimeException($errorMsg, 0, $e);
        }
    }

    /**
     * Get available model names as simple array
     */
    private function getAvailableModelNames(): array
    {
        try {
            $response = $this->getModels();
            if ($response->getStatusCode() !== 200) {
                return [];
            }
            
            $data = json_decode($response->getContent(), true);
            if (!is_array($data) || !isset($data['models'])) {
                return [];
            }
            
            return array_map(fn($model) => $model['name'] ?? '', $data['models']);
        } catch (\Exception $e) {
            error_log("Failed to get model names: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Chat completion using Ollama /api/chat endpoint
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param string $model Model name
     * @param array $options Additional options
     */
    public function chatCompletion(array $messages, string $model = 'llama3.2', array $options = []): ResponseInterface
    {
        if (empty($messages)) {
            throw new \InvalidArgumentException('Messages cannot be empty');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => array_merge([
                'temperature' => 0.7,
                'num_predict' => 2048,
            ], $options)
        ];

        try {
            return $this->httpClient->post($this->llmBaseUrl . '/api/chat', [
                'json' => $payload,
                'timeout' => 300,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to complete chat with LLM: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process categorization prompt (specialized for RAG pipeline)
     */
    public function promptForCategorization(string $prompt, string $model = 'llama3.2'): ResponseInterface
    {
        $systemMessage = "You are a graph-mapping assistant for Neo4j. " .
                        "Analyze the provided document and extract structured data as JSON. " .
                        "Focus on entities like Organizations, Projects, Requirements, and their relationships.";

        return $this->chatCompletion([
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $prompt]
        ], $model, [
            'temperature' => 0.3, // Lower temperature for more consistent structured output
            'num_predict' => 4096, // More tokens for complex JSON responses
        ]);
    }
}
