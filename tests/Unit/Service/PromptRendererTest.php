<?php

namespace App\Tests\Unit\Service;

use App\Service\PromptRenderer;
use PHPUnit\Framework\TestCase;

class PromptRendererTest extends TestCase
{
    /**
     * 🟢 POSITIVE: Test basic template rendering
     */
    public function testRender_BasicStringSubstitution(): void
    {
        $template = 'Hello {{name}}, welcome to {{system}}!';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render([
            'name' => 'David',
            'system' => 'RAG Pipeline'
        ]);
        
        $this->assertEquals('Hello David, welcome to RAG Pipeline!', $result);
    }

    /**
     * 🟢 POSITIVE: Test rendering with array values (JSON conversion)
     */
    public function testRender_ArrayToJson(): void
    {
        $template = 'Configuration: {{config}}';
        $renderer = new PromptRenderer($template);
        
        $config = [
            'enabled' => true,
            'max_tokens' => 1000,
            'model' => 'gpt-4'
        ];
        
        $result = $renderer->render(['config' => $config]);
        
        $expectedJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->assertEquals("Configuration: {$expectedJson}", $result);
    }

    /**
     * 🟢 POSITIVE: Test rendering with object values (JSON conversion)
     */
    public function testRender_ObjectToJson(): void
    {
        $template = 'Data: {{data}}';
        $renderer = new PromptRenderer($template);
        
        $data = (object) ['key' => 'value', 'number' => 42];
        
        $result = $renderer->render(['data' => $data]);
        
        $expectedJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->assertEquals("Data: {$expectedJson}", $result);
    }

    /**
     * 🟢 POSITIVE: Test multiple variable substitution
     */
    public function testRender_MultipleVariables(): void
    {
        $template = 'User {{user}} has {{count}} messages in {{folder}} folder.';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render([
            'user' => 'Alice',
            'count' => 5,
            'folder' => 'Inbox'
        ]);
        
        $this->assertEquals('User Alice has 5 messages in Inbox folder.', $result);
    }

    /**
     * 🟢 POSITIVE: Test complex template with mixed data types
     */
    public function testRender_MixedDataTypes(): void
    {
        $template = 'User: {{user}}\nSettings: {{settings}}\nActive: {{active}}';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render([
            'user' => 'Bob',
            'settings' => ['theme' => 'dark', 'notifications' => true],
            'active' => true
        ]);
        
        $this->assertStringContainsString('User: Bob', $result);
        $this->assertStringContainsString('"theme": "dark"', $result);
        $this->assertStringContainsString('Active: 1', $result); // boolean converts to 1
    }

    /**
     * 🔴 NEGATIVE: Test rendering with missing variables (should leave placeholder)
     */
    public function testRender_MissingVariables(): void
    {
        $template = 'Hello {{name}}, your score is {{score}}!';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render(['name' => 'Charlie']);
        
        $this->assertEquals('Hello Charlie, your score is {{score}}!', $result);
        $this->assertStringContainsString('{{score}}', $result);
    }

    /**
     * 🟢 POSITIVE: Test rendering with no variables
     */
    public function testRender_NoVariables(): void
    {
        $template = 'This is a static template with no variables.';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render([]);
        
        $this->assertEquals($template, $result);
    }

    /**
     * 🟢 POSITIVE: Test rendering with empty values
     */
    public function testRender_EmptyValues(): void
    {
        $template = 'Name: {{name}}, Description: {{description}}';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render([
            'name' => '',
            'description' => null
        ]);
        
        $this->assertEquals('Name: , Description: ', $result);
    }

    /**
     * 🟢 POSITIVE: Test rendering with Unicode characters
     */
    public function testRender_UnicodeSupport(): void
    {
        $template = 'Greeting: {{greeting}} {{emoji}}';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render([
            'greeting' => 'Hällö Wörld',
            'emoji' => '🎉🚀'
        ]);
        
        $this->assertEquals('Greeting: Hällö Wörld 🎉🚀', $result);
    }

    /**
     * 🔴 NEGATIVE: Test rendering with malformed template variables
     */
    public function testRender_MalformedVariables(): void
    {
        $template = 'This {{incomplete and this {single} and {{proper}}';
        $renderer = new PromptRenderer($template);
        
        $result = $renderer->render(['proper' => 'WORKS']);
        
        // Should only replace properly formatted variables
        $this->assertEquals('This {{incomplete and this {single} and WORKS', $result);
    }

    /**
     * 🟢 POSITIVE: Test rendering with nested JSON structure
     */
    public function testRender_NestedJsonStructure(): void
    {
        $template = 'Document analysis: {{tika_json}}';
        $renderer = new PromptRenderer($template);
        
        $tikaData = [
            'metadata' => [
                'title' => 'Test Document',
                'author' => 'Test Author'
            ],
            'content' => 'This is the extracted content...',
            'pages' => 5
        ];
        
        $result = $renderer->render(['tika_json' => $tikaData]);
        
        $this->assertStringContainsString('"title": "Test Document"', $result);
        $this->assertStringContainsString('"content": "This is the extracted content..."', $result);
        $this->assertStringContainsString('"pages": 5', $result);
    }
}
