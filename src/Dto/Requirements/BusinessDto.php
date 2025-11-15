<?php

namespace App\Dto\Requirements;

/**
 * DTO für Business Entity (IRREB)
 * 
 * Repräsentiert Business-Kontext und Ziele für Requirements.
 */
class BusinessDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $goal = null,
        public readonly ?string $objective = null,
        public readonly ?array $kpis = [], // Key Performance Indicators
        public readonly ?string $businessCase = null,
        public readonly ?array $successCriteria = [],
        public readonly ?array $risks = [],
        public readonly ?string $roi = null, // Return on Investment
        public readonly ?string $timeframe = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'goal' => $this->goal,
            'objective' => $this->objective,
            'kpis' => $this->kpis ?? [],
            'businessCase' => $this->businessCase,
            'successCriteria' => $this->successCriteria ?? [],
            'risks' => $this->risks ?? [],
            'roi' => $this->roi,
            'timeframe' => $this->timeframe
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('biz_'),
            name: $data['name'] ?? '',
            goal: $data['goal'] ?? null,
            objective: $data['objective'] ?? null,
            kpis: $data['kpis'] ?? [],
            businessCase: $data['businessCase'] ?? null,
            successCriteria: $data['successCriteria'] ?? [],
            risks: $data['risks'] ?? [],
            roi: $data['roi'] ?? null,
            timeframe: $data['timeframe'] ?? null
        );
    }
}

