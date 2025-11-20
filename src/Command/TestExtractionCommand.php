<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DocumentExtractor\TikaExtractorService;
use App\Service\Embeddings\OllamaEmbeddingsService;
use App\Service\LLM\OllamaLLMService;
use App\Service\Neo4j\Neo4jConnectorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Test requirements extraction pipeline
 */
#[AsCommand(
    name: 'app:test:extraction',
    description: 'Test requirements extraction pipeline with a sample document',
)]
class TestExtractionCommand extends Command
{
    public function __construct(
        private readonly TikaExtractorService $tikaExtractor,
        private readonly OllamaLLMService $llmService,
        private readonly OllamaEmbeddingsService $embeddingsService,
        private readonly Neo4jConnectorService $neo4jConnector
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Path to document file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Requirements Extraction Pipeline Test');

        // Check services
        $io->section('Checking services...');
        $services = [
            'Tika' => $this->tikaExtractor->isAvailable(),
            'Ollama' => $this->llmService->isAvailable(),
            'Neo4j' => $this->neo4jConnector->isAvailable(),
        ];

        foreach ($services as $name => $available) {
            if ($available) {
                $io->success("$name: Available");
            } else {
                $io->error("$name: Not available");
            }
        }

        if (in_array(false, $services, true)) {
            $io->warning('Some services are not available. Test cannot proceed.');
            return Command::FAILURE;
        }

        // Test file extraction
        $filePath = $input->getArgument('file');
        if ($filePath && file_exists($filePath)) {
            $io->section('Testing document extraction...');
            try {
                $text = $this->tikaExtractor->extractText($filePath);
                $io->success('Extracted ' . strlen($text) . ' characters from document');
                $io->writeln(substr($text, 0, 500) . '...');
            } catch (\Exception $e) {
                $io->error('Extraction failed: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // Test LLM
        $io->section('Testing LLM generation...');
        try {
            $response = $this->llmService->generate(
                'Say "Hello from Ollama!"',
                [],
                ['model' => 'llama3.2']
            );
            $io->success('LLM Response: ' . $response['response']);
        } catch (\Exception $e) {
            $io->error('LLM test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test embeddings
        $io->section('Testing embeddings generation...');
        try {
            $embeddings = $this->embeddingsService->embed('Test requirement text');
            $io->success('Generated ' . count($embeddings) . ' dimensional embedding');
        } catch (\Exception $e) {
            $io->error('Embeddings test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('All tests passed! 🚀');

        return Command::SUCCESS;
    }
}

