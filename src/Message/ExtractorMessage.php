<?php
namespace App\Message;

class ExtractorMessage
{
    public function __construct(
        public string $path = ''
    ) {}
}
