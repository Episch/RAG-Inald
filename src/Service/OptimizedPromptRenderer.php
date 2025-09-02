<?php

namespace App\Service;

use App\Service\CacheManager;

/**
 * Optimized prompt renderer with caching and template validation
 */
class OptimizedPromptRenderer extends PromptRenderer
{
    private CacheManager $cacheManager;
    private array $compiledVariables = [];

    public function __construct(string $template, CacheManager $cacheManager = null)
    {
        parent::__construct($template);
        $this->cacheManager = $cacheManager ?? new CacheManager(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
    }

    /**
     * Render template with caching and optimization
     */
    public function render(array $variables = []): string
    {
        // Pre-process variables for better caching
        $processedVariables = $this->processVariables($variables);
        $templateHash = md5($this->template);

        if ($this->cacheManager) {
            return $this->cacheManager->cachePromptRendering(
                $templateHash,
                $processedVariables,
                fn() => $this->doRender($processedVariables)
            );
        }

        return $this->doRender($processedVariables);
    }

    /**
     * Actual rendering logic
     */
    private function doRender(array $variables): string
    {
        $rendered = $this->template;
        
        // Use more efficient replacement strategy for large templates
        if (strlen($this->template) > 10000) {
            return $this->renderLargeTemplate($rendered, $variables);
        }
        
        return $this->renderStandardTemplate($rendered, $variables);
    }

    /**
     * Optimized rendering for large templates
     */
    private function renderLargeTemplate(string $template, array $variables): string
    {
        // Find all placeholders first to avoid unnecessary iterations
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        $placeholders = array_unique($matches[1]);
        
        $replacements = [];
        foreach ($placeholders as $placeholder) {
            if (isset($variables[$placeholder])) {
                $replacements['{{' . $placeholder . '}}'] = $variables[$placeholder];
            }
        }
        
        return strtr($template, $replacements);
    }

    /**
     * Standard rendering for smaller templates
     */
    private function renderStandardTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }
        
        return $template;
    }

    /**
     * Pre-process variables for consistent caching
     */
    private function processVariables(array $variables): array
    {
        $processed = [];
        
        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $processed[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $processed[$key] = $value ?? '';
            }
        }
        
        return $processed;
    }

    /**
     * Validate template syntax
     */
    public function validateTemplate(): array
    {
        $errors = [];
        
        // Check for unclosed placeholders
        if (substr_count($this->template, '{{') !== substr_count($this->template, '}}')) {
            $errors[] = 'Mismatched placeholder braces';
        }
        
        // Check for invalid placeholder names
        preg_match_all('/\{\{([^}]+)\}\}/', $this->template, $matches);
        foreach ($matches[1] as $placeholder) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($placeholder))) {
                $errors[] = "Invalid placeholder name: {{$placeholder}}";
            }
        }
        
        return $errors;
    }

    /**
     * Get template statistics
     */
    public function getTemplateStats(): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $this->template, $matches);
        
        return [
            'template_size' => strlen($this->template),
            'placeholder_count' => count($matches[0]),
            'unique_placeholders' => array_unique($matches[1]),
            'estimated_render_time' => $this->estimateRenderTime()
        ];
    }

    /**
     * Estimate render time based on template complexity
     */
    private function estimateRenderTime(): string
    {
        $size = strlen($this->template);
        $placeholders = substr_count($this->template, '{{');
        
        $complexity = ($size / 1000) + ($placeholders * 0.1);
        
        if ($complexity < 1) return '< 1ms';
        if ($complexity < 10) return '< 10ms';
        if ($complexity < 100) return '< 100ms';
        
        return '> 100ms';
    }
}
