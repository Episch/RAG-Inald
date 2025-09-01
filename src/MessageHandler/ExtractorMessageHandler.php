<?php
namespace App\MessageHandler;

use App\Message\ExtractorMessage;
use App\Service\Connector\TikaConnector;
use App\Service\PromptRenderer;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class ExtractorMessageHandler
{
    private Finder $finder;

    private TikaConnector $extractorConnector;

    private string $promptsPath;

    public function __construct(Finder $finder, TikaConnector $extractorConnector, string $promptsPath)
    {
        $this->finder = $finder;
        $this->extractorConnector = $extractorConnector;
        $this->promptsPath = $promptsPath;
    }

    public function __invoke(ExtractorMessage $message)
    {
        $secureBasePath = __DIR__ . '/../../public/storage/'; // TODO: remove it with config list of folders
        $path = $message->path;

        if (!is_dir($secureBasePath . $path)) {
            throw new \RuntimeException('Path does not exist: ' . $secureBasePath . $path);
        }
        $filename = 'Prozessorientierter_Bericht_-_David_Nagelschmidt.pdf';
        $files = $this->finder->files()->in($secureBasePath . $path)->name($filename);

        // Iterator in Array umwandeln und erste Datei nehmen
        $file = iterator_to_array($files, false)[0] ?? null;
        if (!$file) {
            throw new \RuntimeException('File does not exist: ' . $filename);
        }

        // $file ist jetzt ein SplFileInfo-Objekt
        $realPath = $file->getRealPath();

        // extract
        $content = $this->extractorConnector->analyzeDocument($realPath);
        // reduce text noize
        $extractContent = $this->extractorConnector->parseOptimization($content?->getContent() ?: null);

        // prepare prompt
        $config = Yaml::parseFile($this->promptsPath);
        $template = $config['graph_mapping']['template'];
        $prompt = new PromptRenderer($template);
        $fullPrompt = $prompt->render(['tika_json' => $extractContent]);

        var_export($fullPrompt);


        //throw new \Exception($fullPrompt); // TODO work on chunking etc.

        /*
        // send prompt to lmm
        $processedDocumentData = $this->ollamaConnector->promptForCategorization($fullPrompt);

        file_put_contents($secureBasePath . $path . '/../llm_doc_'.date('now') , $processedDocumentData?->getContent());
        Throw new \Exception($processedDocumentData); // TODO work on chunking etc.
        $result = $llmService->generateText($prompt, [
            'tika_json' => $tikaJson
        ]);*/

        return 0;
    }

}
