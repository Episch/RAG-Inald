<?php

// src/Service/TokenChunker.php
namespace App\Service;

use Yethee\Tiktoken\EncoderProvider;

class TokenChunker
{
    private EncoderProvider $provider;
    private int $chunkSize;
    private int $overlap;

    public function __construct(int $chunkSize = 800, int $overlap = 100)
    {
        $this->provider = new EncoderProvider();
        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
    }


    public function chunk(string $text, string $model = 'gpt-3.5-turbo'): array
    {
        $encoder = $this->provider->getForModel($model);
        $tokens = $encoder->encode($text);
        $chunks = [];

        $i = 0;
        $total = count($tokens);
        while ($i < $total) {
            $slice = array_slice($tokens, $i, $this->chunkSize);
            $chunks[] = $encoder->decode($slice);
            $i += $this->chunkSize - $this->overlap;
        }

        return $chunks;
    }
}
