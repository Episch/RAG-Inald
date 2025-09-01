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
            # {{tika_json}}
            $rendered = str_replace('{{'.$key.'}}', $value, $rendered);
        }
        return $rendered;
    }
}
