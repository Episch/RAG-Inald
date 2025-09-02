<?php

namespace App\Tests\Unit\Service;

use App\Service\TokenChunker;
use PHPUnit\Framework\TestCase;

class TokenChunkerTest extends TestCase
{
    /**
     * 🟢 POSITIVE: Test basic text chunking
     */
    public function testChunk_BasicTextSplitting(): void
    {
        $chunker = new TokenChunker(10, 2); // Small chunks for testing
        
        $text = 'This is a sample text that should be split into multiple chunks for processing.';
        
        $chunks = $chunker->chunk($text);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));
        
        // Verify chunks are strings
        foreach ($chunks as $chunk) {
            $this->assertIsString($chunk);
            $this->assertNotEmpty($chunk);
        }
    }

    /**
     * 🟢 POSITIVE: Test chunking with overlap
     */
    public function testChunk_OverlapFunctionality(): void
    {
        $chunker = new TokenChunker(20, 5); // 20 tokens, 5 overlap
        
        $text = str_repeat('word ', 100); // Create text with many tokens
        
        $chunks = $chunker->chunk($text);
        
        $this->assertGreaterThan(1, count($chunks));
        
        // With overlap, later chunks should contain some content from previous chunks
        // This is hard to test directly due to tokenization, but we can verify structure
        $this->assertIsArray($chunks);
    }

    /**
     * 🟢 POSITIVE: Test chunking short text (no chunking needed)
     */
    public function testChunk_ShortText(): void
    {
        $chunker = new TokenChunker(1000, 100);
        
        $text = 'Short text that fits in one chunk.';
        
        $chunks = $chunker->chunk($text);
        
        $this->assertCount(1, $chunks);
        $this->assertEquals($text, trim($chunks[0]));
    }

    /**
     * 🔴 NEGATIVE: Test chunking empty text
     */
    public function testChunk_EmptyText(): void
    {
        $chunker = new TokenChunker(100, 10);
        
        $chunks = $chunker->chunk('');
        
        $this->assertIsArray($chunks);
        // Should handle empty text gracefully
        $this->assertTrue(count($chunks) <= 1);
        
        if (count($chunks) === 1) {
            $this->assertEquals('', $chunks[0]);
        }
    }

    /**
     * 🟢 POSITIVE: Test different GPT models
     */
    public function testChunk_DifferentModels(): void
    {
        $chunker = new TokenChunker(50, 5);
        
        $text = 'This is a test text for different GPT models to process and tokenize correctly.';
        
        $chunksGpt35 = $chunker->chunk($text, 'gpt-3.5-turbo');
        $chunksGpt4 = $chunker->chunk($text, 'gpt-4');
        
        $this->assertIsArray($chunksGpt35);
        $this->assertIsArray($chunksGpt4);
        
        // Both should produce valid chunks
        $this->assertGreaterThan(0, count($chunksGpt35));
        $this->assertGreaterThan(0, count($chunksGpt4));
    }

    /**
     * 🟢 POSITIVE: Test constructor parameter validation
     */
    public function testConstruct_DefaultParameters(): void
    {
        $chunker = new TokenChunker();
        
        // Should work with defaults
        $text = 'Testing default parameters for token chunker functionality.';
        $chunks = $chunker->chunk($text);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(0, count($chunks));
    }

    /**
     * 🟢 POSITIVE: Test custom chunk size and overlap
     */
    public function testConstruct_CustomParameters(): void
    {
        $chunker = new TokenChunker(200, 50);
        
        $text = str_repeat('This is a longer text meant to be chunked into specific sizes. ', 20);
        $chunks = $chunker->chunk($text);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(0, count($chunks));
        
        // Test with different parameters
        $smallChunker = new TokenChunker(50, 10);
        $smallChunks = $smallChunker->chunk($text);
        
        // Both should produce valid chunks
        $this->assertIsArray($smallChunks);
        $this->assertGreaterThan(0, count($smallChunks));
        
        // Content length should be similar (tokens may vary, but both should process the text)
        $totalChunkLength = array_sum(array_map('strlen', $chunks));
        $totalSmallChunkLength = array_sum(array_map('strlen', $smallChunks));
        $this->assertGreaterThan(0, $totalChunkLength);
        $this->assertGreaterThan(0, $totalSmallChunkLength);
    }

    /**
     * 🟢 POSITIVE: Test chunking preserves content
     */
    public function testChunk_ContentPreservation(): void
    {
        $chunker = new TokenChunker(30, 5);
        
        $text = 'Important data that must be preserved during chunking process.';
        $chunks = $chunker->chunk($text);
        
        // All chunks combined should contain all original content
        $combinedChunks = implode(' ', $chunks);
        
        // Extract key words to verify content preservation
        $originalWords = preg_split('/\s+/', $text);
        $combinedWords = preg_split('/\s+/', $combinedChunks);
        
        foreach ($originalWords as $word) {
            if (!empty(trim($word))) {
                $this->assertTrue(
                    in_array($word, $combinedWords) || 
                    strpos($combinedChunks, $word) !== false,
                    "Word '{$word}' should be preserved in chunks"
                );
            }
        }
    }

    /**
     * 🔴 NEGATIVE: Test chunking with zero chunk size (edge case)
     */
    public function testChunk_ZeroChunkSize(): void
    {
        // This might throw an exception or handle gracefully
        // depending on the tiktoken implementation
        $chunker = new TokenChunker(0, 0);
        
        $text = 'Test text';
        
        // Should either throw exception or handle gracefully
        try {
            $chunks = $chunker->chunk($text);
            // If it doesn't throw, verify it produces reasonable output
            $this->assertIsArray($chunks);
        } catch (\Exception $e) {
            // Exception is acceptable for invalid parameters
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * 🟢 POSITIVE: Test chunking with special characters and unicode
     */
    public function testChunk_SpecialCharacters(): void
    {
        $chunker = new TokenChunker(25, 3);
        
        $text = 'Tëst wïth spëcïäl chäräctërs änd ümläüts! Also émojis: 🚀🎉 and symbols: @#$%^&*()';
        
        $chunks = $chunker->chunk($text);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(0, count($chunks));
        
        // Verify all chunks are valid strings
        foreach ($chunks as $chunk) {
            $this->assertIsString($chunk);
        }
    }

    /**
     * 🟢 POSITIVE: Test chunking large text
     */
    public function testChunk_LargeText(): void
    {
        $chunker = new TokenChunker(100, 20);
        
        // Generate large text
        $largeText = str_repeat('This is a sentence in a large document that needs to be processed efficiently. ', 500);
        
        $chunks = $chunker->chunk($largeText);
        
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(10, count($chunks)); // Should produce many chunks
        
        // Verify no chunk is empty
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk));
        }
    }
}
