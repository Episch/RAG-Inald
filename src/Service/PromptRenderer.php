<?php

namespace App\Service;

/**
 * Template renderer for prompt generation with variable substitution.
 * 
 * Handles secure rendering of templates with placeholder replacement and
 * automatic JSON encoding of complex data structures.
 */
class PromptRenderer
{
    /**
     * Initialize renderer with template content.
     * 
     * @param string $template Template content with {{variable}} placeholders
     */
    public function __construct(protected readonly string $template)
    {
    }

    /**
     * Render template with provided variables.
     * 
     * @param array $variables Associative array of variables for substitution
     * 
     * @return string Rendered template with variables substituted
     */
    public function render(array $variables = []): string
    {
        $rendered = $this->template;
        foreach ($variables as $key => $value) {
            // Insert escaped JSON if necessary
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
            // Handle null values to avoid deprecation warnings
            $value = $value ?? '';
            
            // Replace {{variable}} placeholders
            $rendered = str_replace('{{'.$key.'}}', (string)$value, $rendered);
        }
        return $rendered;
    }
}