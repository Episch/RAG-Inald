<?php

namespace App\Service;

class PromptRenderer
{
    protected string $template;

    public function __construct(string $template)
    {
        $this->template = $template;
    }

    public function render(array $variables = []): string
    {
        $rendered = $this->template;
        foreach ($variables as $key => $value) {
            // Escaped JSON einfügen, falls notwendig
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
            // 🔧 Fix: Handle null values to avoid deprecation warning
            $value = $value ?? '';
            
            # {{tika_json}}
            $rendered = str_replace('{{'.$key.'}}', (string)$value, $rendered);
        }
        return $rendered;
    }
}
