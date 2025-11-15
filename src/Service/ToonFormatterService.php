<?php

namespace App\Service;

use App\Dto\Requirements\RequirementsGraphDto;

/**
 * TOON (Token-Oriented Object Notation) Formatter Service
 * 
 * Konvertiert Requirements-Daten zu/von TOON-Format für optimale LLM-Performance.
 * TOON spart 30-40% Tokens gegenüber JSON bei tabellarischen Daten.
 * 
 * @link https://github.com/toon-format/toon
 */
class ToonFormatterService
{
    /**
     * Konvertiert Requirements-Graph zu TOON-Format
     */
    public function encodeRequirementsGraph(RequirementsGraphDto $graph): string
    {
        $toon = [];

        // Requirements
        if (!empty($graph->requirements)) {
            $toon[] = $this->encodeTable(
                'requirements',
                array_map(fn($r) => is_array($r) ? $r : $r->toArray(), $graph->requirements),
                ['id', 'name', 'description', 'type', 'priority', 'status', 'source']
            );
        }

        // Roles
        if (!empty($graph->roles)) {
            $toon[] = $this->encodeTable(
                'roles',
                array_map(fn($r) => is_array($r) ? $r : $r->toArray(), $graph->roles),
                ['id', 'name', 'description', 'level', 'department']
            );
        }

        // Environments
        if (!empty($graph->environments)) {
            $toon[] = $this->encodeTable(
                'environments',
                array_map(fn($e) => is_array($e) ? $e : $e->toArray(), $graph->environments),
                ['id', 'name', 'type', 'description', 'location']
            );
        }

        // Businesses
        if (!empty($graph->businesses)) {
            $toon[] = $this->encodeTable(
                'businesses',
                array_map(fn($b) => is_array($b) ? $b : $b->toArray(), $graph->businesses),
                ['id', 'name', 'goal', 'objective']
            );
        }

        // Infrastructures
        if (!empty($graph->infrastructures)) {
            $toon[] = $this->encodeTable(
                'infrastructures',
                array_map(fn($i) => is_array($i) ? $i : $i->toArray(), $graph->infrastructures),
                ['id', 'name', 'type', 'description', 'provider']
            );
        }

        // Software Applications
        if (!empty($graph->softwareApplications)) {
            $toon[] = $this->encodeTable(
                'softwareApplications',
                array_map(fn($s) => is_array($s) ? $s : $s->toArray(), $graph->softwareApplications),
                ['id', 'name', 'version', 'operatingSystem', 'category']
            );
        }

        // Relationships
        if (!empty($graph->relationships)) {
            $toon[] = $this->encodeTable(
                'relationships',
                $graph->relationships,
                ['type', 'source', 'target']
            );
        }

        return implode("\n\n", $toon);
    }

    /**
     * Encodiert eine Tabelle im TOON-Format
     * 
     * Format: name[N]{field1,field2}:
     *           value1,value2
     *           value3,value4
     */
    private function encodeTable(string $name, array $items, array $fields): string
    {
        if (empty($items)) {
            return "{$name}[0]:";
        }

        $count = count($items);
        $fieldsStr = implode(',', $fields);
        $lines = ["{$name}[{$count}]{{$fieldsStr}}:"];

        foreach ($items as $item) {
            $values = [];
            foreach ($fields as $field) {
                $value = $item[$field] ?? '';
                $values[] = $this->escapeValue($value);
            }
            $lines[] = '  ' . implode(',', $values);
        }

        return implode("\n", $lines);
    }

    /**
     * Escaped einen Wert für TOON-Format
     */
    private function escapeValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Konvertiere zu String
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            // Arrays als JSON in Quotes
            return '"' . str_replace('"', '""', json_encode($value)) . '"';
        }

        $str = (string) $value;

        // Quote wenn nötig (enthält Komma, Newline, oder startet mit Quote)
        if (str_contains($str, ',') || 
            str_contains($str, "\n") || 
            str_contains($str, '"') ||
            preg_match('/^\s|\s$/', $str)) {
            // Escape Quotes mit doppelten Quotes
            $escaped = str_replace('"', '""', $str);
            return '"' . $escaped . '"';
        }

        return $str;
    }

    /**
     * Parsed TOON-Format zurück zu Array-Struktur
     * 
     * Simplified Parser für Requirements-Daten
     */
    public function decode(string $toon): array
    {
        $result = [
            'requirements' => [],
            'roles' => [],
            'environments' => [],
            'businesses' => [],
            'infrastructures' => [],
            'softwareApplications' => [],
            'relationships' => []
        ];

        $lines = explode("\n", $toon);
        $currentTable = null;
        $currentFields = [];

        foreach ($lines as $line) {
            // Skip leere Zeilen
            if (trim($line) === '') {
                continue;
            }

            // Header-Zeile: name[N]{fields}:
            if (preg_match('/^(\w+)\[(\d+)\]\{([^}]+)\}:$/', $line, $matches)) {
                $currentTable = $matches[1];
                $currentFields = explode(',', $matches[3]);
                continue;
            }

            // Daten-Zeile
            if ($currentTable && preg_match('/^\s\s(.+)$/', $line, $matches)) {
                $values = $this->parseRow($matches[1]);
                
                if (count($values) === count($currentFields)) {
                    $item = array_combine($currentFields, $values);
                    $result[$currentTable][] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Parsed eine TOON-Row (comma-separated mit Quote-Support)
     */
    private function parseRow(string $row): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($row);

        for ($i = 0; $i < $length; $i++) {
            $char = $row[$i];

            if ($char === '"') {
                // Check für escaped Quote ("")
                if ($inQuotes && $i + 1 < $length && $row[$i + 1] === '"') {
                    $current .= '"';
                    $i++; // Skip nächstes Quote
                } else {
                    $inQuotes = !$inQuotes;
                }
            } elseif ($char === ',' && !$inQuotes) {
                $values[] = $this->unescapeValue($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Letzter Wert
        $values[] = $this->unescapeValue($current);

        return $values;
    }

    /**
     * Un-escaped einen TOON-Wert
     */
    private function unescapeValue(string $value): string|int|float|bool|null
    {
        $trimmed = trim($value);

        // Empty
        if ($trimmed === '') {
            return null;
        }

        // Boolean
        if ($trimmed === 'true') {
            return true;
        }
        if ($trimmed === 'false') {
            return false;
        }

        // Numeric
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }

    /**
     * Generiert TOON-Beispiel für LLM-Prompt
     */
    public function generateExampleForPrompt(): string
    {
        return <<<'TOON'
requirements[2]{id,name,type,priority,status}:
  REQ-001,User Authentication,functional,critical,approved
  REQ-002,Data Encryption,non-functional,high,draft

roles[1]{id,name,level}:
  ROLE-001,Security Officer,manager

relationships[2]{type,source,target}:
  OWNED_BY,REQ-001,ROLE-001
  OWNED_BY,REQ-002,ROLE-001
TOON;
    }
}

