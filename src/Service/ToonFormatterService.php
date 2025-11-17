<?php

namespace App\Service;

use App\Dto\Requirements\RequirementsGraphDto;
use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\IndentStyle;

/**
 * TOON (Token-Oriented Object Notation) Formatter Service
 * 
 * Wrapper um die professionelle helgesverre/toon-php Library.
 * Konvertiert Requirements-Daten zu/von TOON-Format für optimale LLM-Performance.
 * TOON spart 30-40% Tokens gegenüber JSON bei tabellarischen Daten.
 * 
 * @link https://github.com/HelgeSverre/toon-php
 * @link https://github.com/toon-format/spec
 */
class ToonFormatterService
{
    /**
     * Konvertiert Requirements-Graph zu TOON-Format (kompakt)
     */
    public function encodeRequirementsGraph(RequirementsGraphDto $graph): string
    {
        $data = $this->prepareGraphData($graph);
        
        // Nutze kompaktes Format für LLM-Prompts (spart Tokens)
        return toon_compact($data);
    }

    /**
     * Konvertiert beliebige Daten zu TOON (kompakt)
     */
    public function encode(array $data): string
    {
        return toon_compact($data);
    }

    /**
     * Konvertiert beliebige Daten zu TOON (lesbar mit 2 Spaces)
     */
    public function encodeReadable(array $data): string
    {
        return toon_readable($data);
    }

    /**
     * Parsed TOON-Format zurück zu Array-Struktur
     */
    public function decode(string $toon): array
    {
        try {
            return toon_decode($toon);
        } catch (\Exception $e) {
            // Fallback zu lenient parsing
            return toon_decode_lenient($toon);
        }
    }

    /**
     * Generiert TOON-Beispiel für LLM-Prompt
     */
    public function generateExampleForPrompt(): string
    {
        $example = [
            'requirements' => [
                [
                    'id' => 'REQ-001',
                    'name' => 'User Authentication',
                    'type' => 'functional',
                    'priority' => 'critical',
                    'status' => 'approved',
                    'source' => 'Security Doc v2.1'
                ],
                [
                    'id' => 'REQ-002',
                    'name' => 'Data Encryption',
                    'type' => 'non-functional',
                    'priority' => 'high',
                    'status' => 'draft',
                    'source' => 'Compliance Requirements'
                ]
            ],
            'roles' => [
                [
                    'id' => 'ROLE-001',
                    'name' => 'Security Officer',
                    'level' => 'manager',
                    'responsibilities' => 'Security, Compliance, Data Protection'
                ]
            ],
            'relationships' => [
                ['type' => 'OWNED_BY', 'source' => 'REQ-001', 'target' => 'ROLE-001'],
                ['type' => 'OWNED_BY', 'source' => 'REQ-002', 'target' => 'ROLE-001']
            ]
        ];

        return toon_compact($example);
    }

    /**
     * Vergleicht Token-Ersparnis von TOON vs. JSON
     * 
     * @return array{toon: int, json: int, savings: int, savings_percent: string}
     */
    public function compareWithJson(array $data): array
    {
        return toon_compare($data);
    }

    /**
     * Schätzt Token-Count für TOON-Output (4 chars/token heuristic)
     */
    public function estimateTokens(array $data): int
    {
        return toon_estimate_tokens($data);
    }

    /**
     * Bereitet Graph-Daten für TOON-Encoding vor
     */
    private function prepareGraphData(RequirementsGraphDto $graph): array
    {
        $data = [];

        // Requirements
        if (!empty($graph->requirements)) {
            $data['requirements'] = array_map(
                fn($r) => is_array($r) ? $r : $r->toArray(), 
                $graph->requirements
            );
        }

        // Roles
        if (!empty($graph->roles)) {
            $data['roles'] = array_map(
                fn($r) => is_array($r) ? $r : $r->toArray(), 
                $graph->roles
            );
        }

        // Environments
        if (!empty($graph->environments)) {
            $data['environments'] = array_map(
                fn($e) => is_array($e) ? $e : $e->toArray(), 
                $graph->environments
            );
        }

        // Businesses
        if (!empty($graph->businesses)) {
            $data['businesses'] = array_map(
                fn($b) => is_array($b) ? $b : $b->toArray(), 
                $graph->businesses
            );
        }

        // Infrastructures
        if (!empty($graph->infrastructures)) {
            $data['infrastructures'] = array_map(
                fn($i) => is_array($i) ? $i : $i->toArray(), 
                $graph->infrastructures
            );
        }

        // Software Applications
        if (!empty($graph->softwareApplications)) {
            $data['softwareApplications'] = array_map(
                fn($s) => is_array($s) ? $s : $s->toArray(), 
                $graph->softwareApplications
            );
        }

        // Relationships
        if (!empty($graph->relationships)) {
            $data['relationships'] = $graph->relationships;
        }

        return $data;
    }
}
