<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\LlmController;
use App\Dto\Base\AbstractDto;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'LLM',
    operations: [
        new Post(
            uriTemplate: '/llm/generate',
            controller: LlmController::class,
            description: 'Generate text responses using local LLM (Ollama) with customizable parameters including temperature, max tokens, and model selection. Supports both async and sync processing.',
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['write']],
            input: LlmPrompt::class
        ),
    ]
)]
class LlmPrompt extends AbstractDto
{
    #[Groups(['write', 'read'])]
    #[Assert\NotBlank(message: 'Prompt is required')]
    #[Assert\Length(
        min: 10,
        max: 50000,
        minMessage: 'Prompt must be at least {{ limit }} characters long',
        maxMessage: 'Prompt cannot be longer than {{ limit }} characters'
    )]
    public string $prompt = '';

    #[Groups(['write', 'read'])]
    #[Assert\Choice(
        choices: ['llama3.2', 'llama3.1', 'llama3', 'mistral', 'codellama', 'qwen2.5'],
        message: 'Invalid model. Allowed models: {{ choices }}'
    )]
    public string $model = 'llama3.2';

    #[Groups(['write', 'read'])]
    #[Assert\Type('bool')]
    public bool $async = true; // Default to async processing

    #[Groups(['write', 'read'])]
    #[Assert\Range(min: 0.0, max: 2.0)]
    public float $temperature = 0.7;

    #[Groups(['write', 'read'])]
    #[Assert\Range(min: 1, max: 8192)]
    public int $maxTokens = 2048;

    // Getters and setters
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function setAsync(bool $async): void
    {
        $this->async = $async;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): void
    {
        $this->temperature = $temperature;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }
}
