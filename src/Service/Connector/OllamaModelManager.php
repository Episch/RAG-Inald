<?php

namespace App\Service\Connector;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Helper service for managing Ollama models
 */
class OllamaModelManager
{
    public function __construct(private LlmConnector $llmConnector)
    {
    }

    /**
     * Check if a specific model is available
     */
    public function isModelAvailable(string $modelName): bool
    {
        try {
            $response = $this->llmConnector->getModels();
            $data = json_decode($response->getContent(), true);
            
            if (!is_array($data) || !isset($data['models'])) {
                return false;
            }
            
            foreach ($data['models'] as $model) {
                if (($model['name'] ?? '') === $modelName) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Failed to check model availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Pull/download a model if it doesn't exist
     */
    public function ensureModelExists(string $modelName): bool
    {
        if ($this->isModelAvailable($modelName)) {
            return true; // Model already exists
        }
        
        error_log("📥 Model '{$modelName}' not found, attempting to pull...");
        
        try {
            return $this->pullModel($modelName);
        } catch (\Exception $e) {
            error_log("❌ Failed to pull model '{$modelName}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Pull/download a model from Ollama
     */
    public function pullModel(string $modelName): bool
    {
        $payload = ['name' => $modelName];
        
        try {
            // Use the llmConnector's httpClient (we need to access it)
            $response = $this->makeHttpRequest('/api/pull', $payload, 600); // 10 minute timeout for model downloads
            
            if ($response->getStatusCode() === 200) {
                error_log("✅ Successfully pulled model: {$modelName}");
                return true;
            } else {
                error_log("⚠️ Pull request returned status: " . $response->getStatusCode());
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("❌ Failed to pull model '{$modelName}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recommended models for different use cases
     */
    public function getRecommendedModels(): array
    {
        return [
            'general' => ['llama3.2', 'llama3.1', 'mistral'],
            'coding' => ['codellama', 'deepseek-coder'],
            'fast' => ['phi3', 'tinyllama'],
            'multilingual' => ['qwen2.5', 'llama3.2'],
        ];
    }

    /**
     * Get the best available model or recommend one to install
     */
    public function getBestAvailableModel(): array
    {
        $recommended = $this->getRecommendedModels()['general'];
        
        try {
            $response = $this->llmConnector->getModels();
            $data = json_decode($response->getContent(), true);
            
            if (is_array($data) && isset($data['models']) && !empty($data['models'])) {
                // Return first available model
                $firstModel = $data['models'][0];
                return [
                    'status' => 'available',
                    'model' => $firstModel['name'] ?? 'unknown',
                    'models_count' => count($data['models']),
                ];
            }
        } catch (\Exception $e) {
            // Fall through to recommendation
        }
        
        return [
            'status' => 'needs_install',
            'recommended' => $recommended[0],
            'message' => "No models available. Run: ollama pull {$recommended[0]}",
        ];
    }

    /**
     * Helper method to make HTTP requests (accessing LlmConnector's httpClient indirectly)
     */
    private function makeHttpRequest(string $endpoint, array $payload, int $timeout = 300): ResponseInterface
    {
        // We need to use reflection or create a public method in LlmConnector to access httpClient
        // For now, let's create a simple curl-based solution
        
        $url = ($_ENV['LMM_URL'] ?? 'http://localhost:11434') . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \RuntimeException("Curl error: {$error}");
        }
        
        // Create a mock ResponseInterface (this is a simplified version)
        return new class($statusCode, $response) implements ResponseInterface {
            public function __construct(private int $statusCode, private string $content) {}
            public function getStatusCode(): int { return $this->statusCode; }
            public function getContent(bool $throw = true): string { return $this->content; }
            public function getHeaders(bool $throw = true): array { return []; }
            public function getInfo(string $type = null) { return null; }
            public function cancel(): void {}
            public function toArray(bool $throw = true): array { return json_decode($this->content, true) ?: []; }
        };
    }
}
