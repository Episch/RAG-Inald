<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Neo4j\Neo4jConnectorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Initialize Neo4j database with indexes
 */
#[AsCommand(
    name: 'app:neo4j:init',
    description: 'Initialize Neo4j database with required indexes and constraints',
)]
class Neo4jInitCommand extends Command
{
    public function __construct(
        private readonly Neo4jConnectorService $neo4jConnector
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Neo4j Database Initialization');

        // Check connection
        $io->section('Checking Neo4j connection...');
        if (!$this->neo4jConnector->isAvailable()) {
            $io->error('Neo4j is not available. Please check your connection.');
            return Command::FAILURE;
        }
        $io->success('Neo4j connection successful');

        // Create indexes
        $io->section('Creating indexes...');
        try {
            $this->neo4jConnector->createIndexes();
            $io->success('Indexes created successfully');
        } catch (\Exception $e) {
            $io->error('Failed to create indexes: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Neo4j initialization completed successfully!');

        return Command::SUCCESS;
    }
}

