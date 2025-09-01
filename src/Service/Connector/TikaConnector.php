<?php
// src/Service/Connector/TikaConnector.php
namespace App\Service\Connector;

use App\Service\HttpClientService;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TikaConnector
{
    private string $tikaBaseUrl;
    private HttpClientService $httpClient;

    public function __construct(HttpClientService $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->tikaBaseUrl = rtrim($_ENV['DOCUMENT_EXTRACTOR_URL'], '/');
    }

    public function getStatus(): ResponseInterface
    {
        return $this->httpClient->get($this->tikaBaseUrl . '/version');
    }

    public function analyzeDocument(string $filePath): ResponseInterface
    {
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        return $this->httpClient->put($this->tikaBaseUrl . '/rmeta/text', [
            'body' => fopen($filePath, 'rb'),
            'headers' => [
                'Content-Type' => $mimeType,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function parseOptimization($content){

        $content = json_decode($content, true);
        $content = $content[0]['X-TIKA:content'];

        if ($content === null) {
            return null;
        }


        // Whitespaces
        $content = preg_replace('/\s+/', ' ', $content);       // Mehrfach-Whitespaces
        $content = preg_replace('/\n{2,}/', "\n", $content);   // Mehrfach-Zeilenumbrüche
        $content = preg_replace('/^\s+|\s+$/m', '', $content); // Leerzeichen am Zeilenanfang/-ende

        // Numbers
        $content = preg_replace('/\n\d+\s*\n/', "\n", $content);   // isolierte Seitenzahlen
        $content = preg_replace('/^\s*(Seite|Page)\s*\d+.*$/mi', '', $content);
        $content = preg_replace('/^\s*(Kapitel|Chapter)\s*\d+.*$/mi', '', $content);
        // $content = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', '', $content); // Datumsangaben YYYY-MM-DD

        // Codefragments
        $content = preg_replace('/<\?php.*?\?>/s', '', $content);             // PHP-Blöcke
        $content = preg_replace('/```.*?```/s', '', $content);                // Markdown-Codeblöcke
        $content = preg_replace('/^[\s\t]*[a-zA-Z0-9_]+\s*\(.*\)\s*{.*$/m', '', $content); // Funktionsköpfe
        $content = preg_replace('/[;{}<>]{2,}/', '', $content);               // Überreste von Syntax

        // signs
        $content = preg_replace('/\.{2,}/', '.', $content);       // Mehrfachpunkte
        $content = preg_replace('/(\.\s*){2,}/', '.', $content);  // Punkt-Wiederholung
        $content = preg_replace('/[“”"„«»]/', '"', $content);     // Normale Anführungszeichen
        $content = preg_replace('/[‘’‚‛]/', "'", $content);       // Normale Apostrophe

        // html & xml noise
        $content = preg_replace('/<style.*?>.*?<\/style>/si', '', $content); // CSS
        $content = preg_replace('/<script.*?>.*?<\/script>/si', '', $content); // JS
        $content = preg_replace('/<[^>]+>/', '', $content); // Alle Tags
        $content = preg_replace('/&[a-z]+;/', '', $content); // HTML-Entities (&nbsp; &gt;)

        // technical noise
        // $content = preg_replace('/https?:\/\/\S+/', '', $content);    // URLs
        $content = preg_replace('/[A-Z]:\\\\[^\s]+/', '', $content); // Windows-Pfade
        $content = preg_replace('/\/[^\s]+/', '', $content);         // UNIX-Pfade
        $content = preg_replace('/[A-F0-9]{32,}/', '', $content);    // Hashes (MD5/SHA)

        return trim($content);
    }
}
