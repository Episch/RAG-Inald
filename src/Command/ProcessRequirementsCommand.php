<?php

namespace App\Command;

use App\Message\RequirementsMessage;
use App\Service\RequirementsExtractionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Finder\Finder;

/**
 * Command zum Verarbeiten von Requirements-Dokumenten
 * 
 * Extrahiert Requirements aus PDF/Excel-Dokumenten und importiert sie nach Neo4j.
 * 
 * Verwendung:
 *   php bin/console app:process-requirements /path/to/documents
 *   php bin/console app:process-requirements /path/to/document.pdf --model=llama3.2
 *   php bin/console app:process-requirements /path/to/folder --async --no-import
 */
#[AsCommand(
    name: 'app:process-requirements',
    description: 'Extrahiert Requirements aus Dokumenten (PDF/Excel) und importiert sie nach Neo4j'
)]
class ProcessRequirementsCommand extends Command
{
    public function __construct(
        private readonly RequirementsExtractionService $extractionService,
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Pfad zu einer Datei oder einem Verzeichnis mit Requirements-Dokumenten'
            )
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'LLM-Modell für die Extraktion',
                'llama3.2'
            )
            ->addOption(
                'no-import',
                null,
                InputOption::VALUE_NONE,
                'Nicht automatisch nach Neo4j importieren'
            )
            ->addOption(
                'async',
                'a',
                InputOption::VALUE_NONE,
                'Asynchrone Verarbeitung über Message Queue'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output-Dateiname für das JSON-Ergebnis',
                null
            )
            ->addOption(
                'pattern',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Datei-Pattern für Verzeichnis-Scan (z.B. "*.pdf")',
                '*'
            )
            ->addOption(
                'recursive',
                'r',
                InputOption::VALUE_NONE,
                'Rekursiv Unterverzeichnisse durchsuchen'
            )
            ->setHelp(<<<HELP
Dieses Command extrahiert Requirements aus Dokumenten und importiert sie nach Neo4j.

<info>Beispiele:</info>

  # Einzelne Datei verarbeiten
  <comment>php bin/console app:process-requirements /path/to/requirements.pdf</comment>

  # Verzeichnis mit allen PDFs verarbeiten
  <comment>php bin/console app:process-requirements /path/to/documents --pattern="*.pdf"</comment>

  # Asynchrone Verarbeitung über Queue
  <comment>php bin/console app:process-requirements /path/to/documents --async</comment>

  # Ohne automatischen Neo4j-Import
  <comment>php bin/console app:process-requirements /path/to/doc.pdf --no-import</comment>

  # Mit spezifischem LLM-Modell
  <comment>php bin/console app:process-requirements /path/to/doc.pdf --model=llama3.2:7b</comment>

  # Rekursiv alle Excel-Dateien verarbeiten
  <comment>php bin/console app:process-requirements /path/to/root --pattern="*.xlsx" --recursive</comment>

<info>Unterstützte Dateiformate:</info>
  - PDF (.pdf)
  - Excel (.xls, .xlsx)
  - OpenDocument Spreadsheet (.ods)

<info>Pipeline-Schritte:</info>
  1. Text-Extraktion via Apache Tika (Docker)
  2. Requirements-Analyse via Ollama LLM (Docker)
  3. Strukturierung nach IRREB + schema.org
  4. Import nach Neo4j (optional)
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        $model = $input->getOption('model');
        $importToNeo4j = !$input->getOption('no-import');
        $async = $input->getOption('async');
        $outputFilename = $input->getOption('output');
        $pattern = $input->getOption('pattern');
        $recursive = $input->getOption('recursive');

        $io->title('Requirements-Extraktion & Neo4j Import');

        // Validiere Pfad
        if (!file_exists($path)) {
            $io->error("Pfad existiert nicht: {$path}");
            return Command::FAILURE;
        }

        // Sammle Dateien
        $filePaths = $this->collectFiles($path, $pattern, $recursive);

        if (empty($filePaths)) {
            $io->warning('Keine passenden Dateien gefunden.');
            return Command::FAILURE;
        }

        $io->section('Konfiguration');
        $io->table(
            ['Parameter', 'Wert'],
            [
                ['Dateien gefunden', count($filePaths)],
                ['LLM-Modell', $model],
                ['Neo4j-Import', $importToNeo4j ? 'Ja' : 'Nein'],
                ['Verarbeitung', $async ? 'Asynchron (Queue)' : 'Synchron'],
                ['Output-Datei', $outputFilename ?: 'Auto-generiert']
            ]
        );

        if (count($filePaths) > 5) {
            $io->listing(array_merge(
                array_slice(array_map('basename', $filePaths), 0, 5),
                ['... und ' . (count($filePaths) - 5) . ' weitere']
            ));
        } else {
            $io->listing(array_map('basename', $filePaths));
        }

        if (!$io->confirm('Fortfahren?', true)) {
            $io->info('Abgebrochen.');
            return Command::SUCCESS;
        }

        // Verarbeitung
        if ($async) {
            return $this->processAsync($io, $filePaths, $model, $importToNeo4j, $outputFilename);
        } else {
            return $this->processSync($io, $filePaths, $model, $importToNeo4j, $outputFilename);
        }
    }

    /**
     * Sammelt Dateien basierend auf Pfad und Pattern
     */
    private function collectFiles(string $path, string $pattern, bool $recursive): array
    {
        $filePaths = [];

        if (is_file($path)) {
            // Einzelne Datei
            $filePaths[] = realpath($path);
        } elseif (is_dir($path)) {
            // Verzeichnis
            $finder = new Finder();
            $finder->files()->in($path)->name($pattern);

            if (!$recursive) {
                $finder->depth('== 0');
            }

            // Filter für unterstützte Dateitypen
            $finder->name(['*.pdf', '*.xls', '*.xlsx', '*.ods']);

            foreach ($finder as $file) {
                $filePaths[] = $file->getRealPath();
            }
        }

        return $filePaths;
    }

    /**
     * Synchrone Verarbeitung
     */
    private function processSync(
        SymfonyStyle $io,
        array $filePaths,
        string $model,
        bool $importToNeo4j,
        ?string $outputFilename
    ): int {
        $io->section('Starte synchrone Verarbeitung...');

        $progressBar = $io->createProgressBar(4);
        $progressBar->setFormat('verbose');

        try {
            $startTime = microtime(true);
            
            $progressBar->setMessage('Extrahiere Text aus Dokumenten...');
            $progressBar->advance();

            $progressBar->setMessage('Analysiere mit LLM...');
            $progressBar->advance();

            $progressBar->setMessage('Strukturiere Requirements...');
            $progressBar->advance();

            // Führe Extraktion aus
            $requirementsGraph = $this->extractionService->extractFromDocuments(
                filePaths: $filePaths,
                model: $model,
                importToNeo4j: $importToNeo4j
            );

            $progressBar->setMessage('Fertig!');
            $progressBar->finish();
            $io->newLine(2);

            // Token-Statistiken vom Service holen und anzeigen
            $executionTime = round(microtime(true) - $startTime, 2);
            $tokenStats = $this->extractionService->getTokenStats();
            $this->displayTokenStats($io, $executionTime, $tokenStats);

            // Zeige Ergebnisse
            $this->displayResults($io, $requirementsGraph, $importToNeo4j);

            // Speichere optional als Datei
            if ($outputFilename) {
                $this->saveToFile($io, $requirementsGraph, $outputFilename);
            }

            $io->success('Requirements erfolgreich extrahiert!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Fehler bei der Verarbeitung: ' . $e->getMessage());
            $io->block($e->getTraceAsString(), 'TRACE', 'fg=red', ' ', true);
            return Command::FAILURE;
        }
    }

    /**
     * Asynchrone Verarbeitung über Message Queue
     */
    private function processAsync(
        SymfonyStyle $io,
        array $filePaths,
        string $model,
        bool $importToNeo4j,
        ?string $outputFilename
    ): int {
        $io->section('Starte asynchrone Verarbeitung...');

        try {
            $message = new RequirementsMessage(
                filePaths: $filePaths,
                model: $model,
                importToNeo4j: $importToNeo4j,
                saveAsFile: true,
                outputFilename: $outputFilename,
                requestId: uniqid('req_cmd_')
            );

            $this->messageBus->dispatch($message);

            $io->success([
                'Message wurde in die Queue eingereiht!',
                'Request-ID: ' . $message->getRequestId(),
                'Die Verarbeitung läuft im Hintergrund.'
            ]);

            $io->note([
                'Überwache den Status mit:',
                '  php bin/console messenger:consume async -vv',
                '',
                'Oder prüfe die Logs:',
                '  tail -f var/log/dev.log'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Fehler beim Dispatch: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Zeigt Token-Statistiken an
     */
    private function displayTokenStats(SymfonyStyle $io, float $executionTime, array $tokenStats): void
    {
        $io->section('⚡ Performance & Token-Statistiken');
        
        $promptTokens = $tokenStats['prompt_tokens'] ?? 0;
        $completionTokens = $tokenStats['completion_tokens'] ?? 0;
        $totalTokens = $tokenStats['total_tokens'] ?? 0;
        $chunksProcessed = $tokenStats['chunks_processed'] ?? 0;
        $format = $tokenStats['format'] ?? 'TOON';
        $model = $tokenStats['model'] ?? 'unknown';
        
        // Berechne geschätzte JSON-Tokens (für Vergleich)
        $estimatedJsonTokens = $totalTokens > 0 ? round($totalTokens / 0.65) : 0; // TOON spart ~35%
        $savedTokens = $estimatedJsonTokens - $totalTokens;
        $savedPercentage = $estimatedJsonTokens > 0 ? round(($savedTokens / $estimatedJsonTokens) * 100) : 0;
        
        $rows = [
            ['Modell', $model],
            ['Format', $format . ' (Token-optimiert)'],
            ['', ''], // Separator
            ['📥 Input Tokens', number_format($promptTokens)],
            ['📤 Output Tokens', number_format($completionTokens)],
            ['💯 Total Tokens', number_format($totalTokens)],
        ];
        
        if ($chunksProcessed > 0) {
            $rows[] = ['🔄 Chunks verarbeitet', $chunksProcessed];
        }
        
        $rows[] = ['', '']; // Separator
        $rows[] = ['⏱️  Gesamtzeit', $executionTime . 's'];
        
        if ($savedTokens > 0) {
            $rows[] = ['', ''];
            $rows[] = ['💰 Ersparnis vs. JSON', number_format($savedTokens) . " Tokens (~{$savedPercentage}%)"];
            $rows[] = ['📊 JSON würde kosten', number_format($estimatedJsonTokens) . ' Tokens'];
        }
        
        $io->table(['Metrik', 'Wert'], $rows);
        
        // Cost-Estimation (optional, basierend auf gängigen Preisen)
        if ($totalTokens > 0) {
            $this->displayCostEstimation($io, $totalTokens, $promptTokens, $completionTokens, $savedTokens);
        }
    }
    
    /**
     * Zeigt Kosten-Schätzung an
     */
    private function displayCostEstimation(
        SymfonyStyle $io, 
        int $totalTokens, 
        int $promptTokens, 
        int $completionTokens,
        int $savedTokens
    ): void {
        $io->note([
            '💵 Kosten-Schätzung (Ollama = lokal/kostenlos):',
            '   Lokales LLM (Ollama): $0.00',
            '   Mit OpenAI GPT-4 würde das kosten:',
            "   - Input: ~$" . number_format($promptTokens * 0.00003, 4),
            "   - Output: ~$" . number_format($completionTokens * 0.00006, 4),
            "   - Total: ~$" . number_format(($promptTokens * 0.00003) + ($completionTokens * 0.00006), 4),
            '',
            "   🎉 Mit TOON gespart: ~{$savedTokens} Tokens",
            '   Das sind ~$' . number_format($savedTokens * 0.00003, 4) . ' weniger bei GPT-4!',
        ]);
    }

    /**
     * Zeigt Extraktions-Ergebnisse an
     */
    private function displayResults(SymfonyStyle $io, $requirementsGraph, bool $importToNeo4j): void
    {
        $io->section('📊 Extraktions-Ergebnisse');

        $io->table(
            ['Entitätstyp', 'Anzahl'],
            [
                ['Requirements', count($requirementsGraph->requirements)],
                ['Roles', count($requirementsGraph->roles)],
                ['Environments', count($requirementsGraph->environments)],
                ['Businesses', count($requirementsGraph->businesses)],
                ['Infrastructures', count($requirementsGraph->infrastructures)],
                ['Software Applications', count($requirementsGraph->softwareApplications)],
                ['Relationships', count($requirementsGraph->relationships)]
            ]
        );

        // Zeige einige Requirements als Beispiel
        if (!empty($requirementsGraph->requirements)) {
            $io->section('Beispiel-Requirements (erste 3)');
            
            $sampleCount = min(3, count($requirementsGraph->requirements));
            for ($i = 0; $i < $sampleCount; $i++) {
                $req = $requirementsGraph->requirements[$i];
                $reqData = is_array($req) ? $req : $req->toArray();
                
                $io->definitionList(
                    ['ID' => $reqData['id'] ?? 'N/A'],
                    ['Name' => $reqData['name'] ?? 'N/A'],
                    ['Type' => $reqData['type'] ?? 'N/A'],
                    ['Priority' => $reqData['priority'] ?? 'N/A'],
                    ['Description' => substr($reqData['description'] ?? '', 0, 100) . '...']
                );
                $io->newLine();
            }
        }

        if ($importToNeo4j) {
            $io->success('Daten wurden erfolgreich nach Neo4j importiert!');
        } else {
            $io->note('Neo4j-Import wurde übersprungen (--no-import)');
        }
    }

    /**
     * Speichert Requirements-Graph als JSON-Datei
     */
    private function saveToFile(SymfonyStyle $io, $requirementsGraph, string $filename): void
    {
        $outputPath = __DIR__ . '/../../var/requirements_output/';
        
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $fullPath = $outputPath . $filename;

        $jsonOutput = json_encode(
            $requirementsGraph->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        file_put_contents($fullPath, $jsonOutput);

        $io->info("Ergebnis gespeichert: {$fullPath}");
    }
}

