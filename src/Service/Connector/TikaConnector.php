<?php
// src/Service/Connector/TikaConnector.php
namespace App\Service\Connector;

use App\Service\HttpClientService;
use App\Contract\ConnectorInterface;
use App\Exception\DocumentExtractionException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TikaConnector implements ConnectorInterface
{
    private string $tikaBaseUrl;
    private HttpClientService $httpClient;

    public function __construct(HttpClientService $httpClient)
    {
        $this->httpClient = $httpClient;
        
        // 🔒 Security: Validate environment variable
        $tikaUrl = $_ENV['DOCUMENT_EXTRACTOR_URL'] ?? '';
        if (empty($tikaUrl)) {
            throw new \InvalidArgumentException('DOCUMENT_EXTRACTOR_URL environment variable is required');
        }
        
        $this->tikaBaseUrl = rtrim($tikaUrl, '/');
    }

    public function getStatus(): ResponseInterface
    {
        try {
            return $this->httpClient->get($this->tikaBaseUrl . '/version');
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to connect to Tika: " . $e->getMessage(), 0, $e);
        }
    }

    public function getServiceInfo(): array
    {
        try {
            $response = $this->getStatus();
            $content = $response->getContent();
            
            // Try to parse version info - Tika returns different formats
            $version = 'unknown';
            if ($response->getStatusCode() === 200) {
                // Tika /version endpoint returns plain text like "Apache Tika 3.2.2"
                if (preg_match('/Apache Tika ([0-9.]+)/', $content, $matches)) {
                    $version = $matches[1];
                } else {
                    $version = trim($content);
                }
            }
            
            return [
                'name' => 'Apache Tika',
                'version' => $version,
                'status_code' => $response->getStatusCode(),
                'healthy' => $response->getStatusCode() === 200
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Apache Tika',
                'version' => 'unknown',
                'status_code' => 503,
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function analyzeDocument(string $filePath): ResponseInterface
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("File not accessible: {$filePath}");
        }
        
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        try {
            return $this->httpClient->put($this->tikaBaseUrl . '/rmeta/text', [
                'body' => fopen($filePath, 'rb'),
                'headers' => [
                    'Content-Type' => $mimeType,
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to analyze document with Tika: " . $e->getMessage(), 0, $e);
        }
    }

    public function parseOptimization(?string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        // 🛡️ Robust JSON parsing with error handling
        $decodedContent = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON content from Tika: ' . json_last_error_msg());
        }
        
        if (!is_array($decodedContent) || empty($decodedContent)) {
            return null;
        }
        
        $textContent = $decodedContent[0]['X-TIKA:content'] ?? null;

        if ($textContent === null || $textContent === '') {
            return null;
        }

        // 🛡️ Ensure we're working with a string to avoid deprecation warnings
        $textContent = (string) $textContent;

        // Whitespaces
        $textContent = preg_replace('/\s+/', ' ', $textContent);       // Mehrfach-Whitespaces
        $textContent = preg_replace('/\n{2,}/', "\n", $textContent);   // Mehrfach-Zeilenumbrüche
        $textContent = preg_replace('/^\s+|\s+$/m', '', $textContent); // Leerzeichen am Zeilenanfang/-ende

        // Numbers
        $textContent = preg_replace('/\n\d+\s*\n/', "\n", $textContent);   // isolierte Seitenzahlen
        $textContent = preg_replace('/^\s*(Seite|Page)\s*\d+.*$/mi', '', $textContent);
        $textContent = preg_replace('/^\s*(Kapitel|Chapter)\s*\d+.*$/mi', '', $textContent);
        // $textContent = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', '', $textContent); // Datumsangaben YYYY-MM-DD

        // Codefragments
        $textContent = preg_replace('/<\?php.*?\?>/s', '', $textContent);             // PHP-Blöcke
        $textContent = preg_replace('/```.*?```/s', '', $textContent);                // Markdown-Codeblöcke
        $textContent = preg_replace('/^[\s\t]*[a-zA-Z0-9_]+\s*\(.*\)\s*{.*$/m', '', $textContent); // Funktionsköpfe
        $textContent = preg_replace('/[;{}<>]{2,}/', '', $textContent);               // Überreste von Syntax

        // signs
        $textContent = preg_replace('/\.{2,}/', '.', $textContent);       // Mehrfachpunkte
        $textContent = preg_replace('/(\.\s*){2,}/', '.', $textContent);  // Punkt-Wiederholung
        
        // 🔧 Fix: Replace problematic quote characters with safe alternatives
        if ($textContent !== null) {
            // Replace smart quotes with regular quotes using UTF-8 byte sequences (safer than direct Unicode)
            $textContent = str_replace([
                "\xE2\x80\x9C", // " (LEFT DOUBLE QUOTATION MARK)
                "\xE2\x80\x9D", // " (RIGHT DOUBLE QUOTATION MARK)
                "\xE2\x80\x9E", // „ (DOUBLE LOW-9 QUOTATION MARK)
                "\xC2\xAB",     // « (LEFT GUILLEMET)
                "\xC2\xBB"      // » (RIGHT GUILLEMET)
            ], '"', $textContent);
            
            $textContent = str_replace([
                "\xE2\x80\x98", // ' (LEFT SINGLE QUOTATION MARK)
                "\xE2\x80\x99", // ' (RIGHT SINGLE QUOTATION MARK)
                "\xE2\x80\x9A", // ‚ (SINGLE LOW-9 QUOTATION MARK)
                "\xE2\x80\x9B"  // ‛ (SINGLE HIGH-REVERSED-9 QUOTATION MARK)
            ], "'", $textContent);
        }

        // html & xml noise
        $textContent = preg_replace('/<style.*?>.*?<\/style>/si', '', $textContent); // CSS
        $textContent = preg_replace('/<script.*?>.*?<\/script>/si', '', $textContent); // JS
        $textContent = preg_replace('/<[^>]+>/', '', $textContent); // Alle Tags
        $textContent = preg_replace('/&[a-z]+;/', '', $textContent); // HTML-Entities (&nbsp; &gt;)

        // technical noise
        // $textContent = preg_replace('/https?:\/\/\S+/', '', $textContent);    // URLs
        $textContent = preg_replace('/[A-Z]:\\\\[^\s]+/', '', $textContent); // Windows-Pfade
        $textContent = preg_replace('/\/[^\s]+/', '', $textContent);         // UNIX-Pfade
        $textContent = preg_replace('/[A-F0-9]{32,}/', '', $textContent);    // Hashes (MD5/SHA)

        return trim($textContent);
    }
}
