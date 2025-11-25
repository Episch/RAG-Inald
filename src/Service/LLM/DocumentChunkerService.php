<?php

declare(strict_types=1);

namespace App\Service\LLM;

use Psr\Log\LoggerInterface;

/**
 * Service zum Chunken von großen Dokumenten für LLM-Verarbeitung
 * 
 * Teilt große Dokumente in kleinere Chunks auf, die einzeln verarbeitet
 * und dann zu einem Gesamtergebnis zusammengeführt werden können.
 */
class DocumentChunkerService
{
    private const MAX_CHARS_PER_CHUNK = 8000;  // ~2000 Tokens bei ca. 4 chars/token
    private const OVERLAP_CHARS = 500;          // Overlap zwischen Chunks für Kontext
    
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Teilt ein Dokument in Chunks auf
     * 
     * @param string $text Der zu chunkende Text
     * @return array Array von Text-Chunks
     */
    public function chunkDocument(string $text): array
    {
        $textLength = strlen($text);
        
        // Wenn Text klein genug ist, kein Chunking nötig
        if ($textLength <= self::MAX_CHARS_PER_CHUNK) {
            $this->logger->info('Document small enough, no chunking needed', [
                'text_length' => $textLength,
                'max_chunk_size' => self::MAX_CHARS_PER_CHUNK,
            ]);
            
            return [$text];
        }

        $this->logger->info('Chunking large document', [
            'text_length' => $textLength,
            'max_chunk_size' => self::MAX_CHARS_PER_CHUNK,
            'overlap' => self::OVERLAP_CHARS,
        ]);

        $chunks = [];
        $position = 0;
        $chunkIndex = 0;

        while ($position < $textLength) {
            $chunkSize = min(self::MAX_CHARS_PER_CHUNK, $textLength - $position);
            $chunk = substr($text, $position, $chunkSize);
            
            // Versuche bei Absatzende zu schneiden für besseren Kontext
            if ($position + $chunkSize < $textLength) {
                $chunk = $this->cutAtNaturalBreakpoint($chunk);
            }
            
            $chunks[] = $chunk;
            
            $this->logger->debug('Created chunk', [
                'chunk_index' => $chunkIndex,
                'chunk_length' => strlen($chunk),
                'position' => $position,
            ]);
            
            // Nächster Chunk startet mit Overlap für Kontext
            $position += strlen($chunk) - self::OVERLAP_CHARS;
            $chunkIndex++;
            
            // Sicherheitscheck gegen Endlosschleifen
            if ($chunkIndex > 1000) {
                $this->logger->warning('Too many chunks, aborting', [
                    'chunk_count' => $chunkIndex,
                ]);
                break;
            }
        }

        $this->logger->info('Document chunking completed', [
            'total_chunks' => count($chunks),
            'original_length' => $textLength,
            'avg_chunk_size' => $textLength > 0 ? round(array_sum(array_map('strlen', $chunks)) / count($chunks)) : 0,
        ]);

        return $chunks;
    }

    /**
     * Schneidet einen Chunk an einem natürlichen Breakpoint (Absatz, Satz, etc.)
     */
    private function cutAtNaturalBreakpoint(string $chunk): string
    {
        // Versuche bei doppeltem Zeilenumbruch zu schneiden (Absatzende)
        if (($lastParagraph = strrpos($chunk, "\n\n")) !== false) {
            return substr($chunk, 0, $lastParagraph + 2);
        }

        // Versuche bei einzelnem Zeilenumbruch zu schneiden
        if (($lastNewline = strrpos($chunk, "\n")) !== false) {
            return substr($chunk, 0, $lastNewline + 1);
        }

        // Versuche bei Satzende zu schneiden
        if (($lastSentence = $this->findLastSentenceEnd($chunk)) !== false) {
            return substr($chunk, 0, $lastSentence);
        }

        // Fallback: bei Leerzeichen schneiden
        if (($lastSpace = strrpos($chunk, ' ')) !== false && $lastSpace > strlen($chunk) * 0.8) {
            return substr($chunk, 0, $lastSpace);
        }

        // Wenn nichts gefunden, originalen Chunk zurückgeben
        return $chunk;
    }

    /**
     * Findet das letzte Satzende in einem Text
     */
    private function findLastSentenceEnd(string $text): int|false
    {
        $sentenceEnders = ['. ', '! ', '? ', '.\n', '!\n', '?\n'];
        $lastPos = false;

        foreach ($sentenceEnders as $ender) {
            $pos = strrpos($text, $ender);
            if ($pos !== false && ($lastPos === false || $pos > $lastPos)) {
                $lastPos = $pos + strlen($ender);
            }
        }

        return $lastPos;
    }

    /**
     * Berechnet die geschätzte Anzahl von Chunks für einen Text
     */
    public function estimateChunkCount(string $text): int
    {
        $textLength = strlen($text);
        
        if ($textLength <= self::MAX_CHARS_PER_CHUNK) {
            return 1;
        }

        return (int) ceil($textLength / (self::MAX_CHARS_PER_CHUNK - self::OVERLAP_CHARS));
    }
}

