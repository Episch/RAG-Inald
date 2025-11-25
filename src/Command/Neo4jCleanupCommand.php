<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Neo4j\Neo4jConnectorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:neo4j:cleanup',
    description: 'Clean up Neo4j database (delete all nodes and relationships)',
)]
class Neo4jCleanupCommand extends Command
{
    public function __construct(
        private readonly Neo4jConnectorService $neo4jConnector
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation')
            ->addOption('only-requirements', null, InputOption::VALUE_NONE, 'Delete only Requirements (keep Applications)')
            ->setHelp('This command allows you to clean up your Neo4j database by removing all or selected nodes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $onlyRequirements = $input->getOption('only-requirements');

        $io->title('Neo4j Database Cleanup');

        try {
            // Get current stats
            $stats = $this->getDatabaseStats();

            $io->section('Current Database Status');
            $tableRows = [
                ['SoftwareApplication nodes', $stats['applications']],
                ['SoftwareRequirement nodes', $stats['requirements']],
                ['HAS_REQUIREMENT relationships', $stats['relationships']],
            ];
            
            if ($stats['unlabeled'] > 0) {
                $tableRows[] = ['⚠️  Unlabeled/Orphaned nodes', $stats['unlabeled']];
            }
            
            $tableRows[] = ['Total nodes', $stats['applications'] + $stats['requirements'] + $stats['unlabeled']];
            
            $io->table(['Type', 'Count'], $tableRows);

            if ($stats['applications'] === 0 && $stats['requirements'] === 0) {
                $io->success('Database is already empty!');
                return Command::SUCCESS;
            }

            // Confirmation
            if ($onlyRequirements) {
                $io->warning(sprintf(
                    'This will delete %d SoftwareRequirement nodes and their relationships!',
                    $stats['requirements']
                ));
                $confirmMessage = 'Do you want to delete all Requirements?';
            } else {
                $io->warning(sprintf(
                    'This will delete ALL %d nodes (%d Applications + %d Requirements) and %d relationships!',
                    $stats['applications'] + $stats['requirements'],
                    $stats['applications'],
                    $stats['requirements'],
                    $stats['relationships']
                ));
                $io->caution('THIS WILL COMPLETELY WIPE YOUR NEO4J DATABASE!');
                $confirmMessage = 'Are you ABSOLUTELY SURE you want to delete everything?';
            }

            if (!$force && !$io->confirm($confirmMessage, false)) {
                $io->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }

            // Perform cleanup
            $io->section('Cleaning up...');
            $progressBar = $io->createProgressBar(4);
            $progressBar->start();

            if ($onlyRequirements) {
                // Delete only Requirements
                $progressBar->setMessage('Deleting SoftwareRequirement nodes...');
                $deleted = $this->deleteRequirements();
                $progressBar->advance();
                
                $io->newLine(2);
                $io->success(sprintf('Deleted %d SoftwareRequirement nodes and their relationships.', $deleted));
            } else {
                // Delete everything
                $progressBar->setMessage('Deleting relationships...');
                $this->deleteRelationships();
                $progressBar->advance();

                $progressBar->setMessage('Deleting SoftwareRequirement nodes...');
                $reqDeleted = $this->deleteRequirements();
                $progressBar->advance();

                $progressBar->setMessage('Deleting SoftwareApplication nodes...');
                $appDeleted = $this->deleteApplications();
                $progressBar->advance();

                // NEW: Delete any remaining unlabeled or orphaned nodes
                $progressBar->setMessage('Cleaning up unlabeled/orphaned nodes...');
                $orphanedDeleted = $this->deleteAllRemainingNodes();
                $progressBar->advance();

                $progressBar->finish();
                $io->newLine(2);

                $io->success(sprintf(
                    'Deleted %d Applications, %d Requirements, and %d orphaned nodes.',
                    $appDeleted,
                    $reqDeleted,
                    $orphanedDeleted
                ));
            }

            // Verify cleanup
            $io->section('Verification');
            $newStats = $this->getDatabaseStats();
            
            if ($onlyRequirements) {
                if ($newStats['requirements'] === 0) {
                    $io->success('✅ All Requirements deleted successfully!');
                } else {
                    $io->error(sprintf('⚠️  Still %d Requirements remaining!', $newStats['requirements']));
                }
            } else {
                if ($newStats['applications'] === 0 && $newStats['requirements'] === 0) {
                    $io->success('✅ Database is now empty!');
                } else {
                    $io->error(sprintf(
                        '⚠️  Still %d nodes remaining (%d Apps, %d Reqs)!',
                        $newStats['applications'] + $newStats['requirements'],
                        $newStats['applications'],
                        $newStats['requirements']
                    ));
                }
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Cleanup failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getDatabaseStats(): array
    {
        $appResult = $this->neo4jConnector->getClient()->run(
            'MATCH (a:SoftwareApplication) RETURN count(a) as count'
        );
        
        $reqResult = $this->neo4jConnector->getClient()->run(
            'MATCH (r:SoftwareRequirement) RETURN count(r) as count'
        );
        
        $relResult = $this->neo4jConnector->getClient()->run(
            'MATCH ()-[rel:HAS_REQUIREMENT]->() RETURN count(rel) as count'
        );

        // Check for unlabeled or other nodes
        $allNodesResult = $this->neo4jConnector->getClient()->run(
            'MATCH (n) RETURN count(n) as count'
        );
        
        $apps = $appResult->first()->get('count');
        $reqs = $reqResult->first()->get('count');
        $allNodes = $allNodesResult->first()->get('count');
        
        return [
            'applications' => $apps,
            'requirements' => $reqs,
            'relationships' => $relResult->first()->get('count'),
            'unlabeled' => $allNodes - $apps - $reqs, // Nodes without proper labels
        ];
    }

    private function deleteRelationships(): int
    {
        $result = $this->neo4jConnector->getClient()->run(
            'MATCH ()-[rel:HAS_REQUIREMENT]->() DELETE rel RETURN count(rel) as deleted'
        );
        
        return $result->first()->get('deleted');
    }

    private function deleteRequirements(): int
    {
        $result = $this->neo4jConnector->getClient()->run(
            'MATCH (r:SoftwareRequirement) DETACH DELETE r RETURN count(r) as deleted'
        );
        
        return $result->first()->get('deleted');
    }

    private function deleteApplications(): int
    {
        $result = $this->neo4jConnector->getClient()->run(
            'MATCH (a:SoftwareApplication) DETACH DELETE a RETURN count(a) as deleted'
        );
        
        return $result->first()->get('deleted');
    }

    private function deleteAllRemainingNodes(): int
    {
        // Delete ALL remaining nodes (including unlabeled/orphaned)
        $result = $this->neo4jConnector->getClient()->run(
            'MATCH (n) DETACH DELETE n RETURN count(n) as deleted'
        );
        
        return $result->first()->get('deleted');
    }
}

