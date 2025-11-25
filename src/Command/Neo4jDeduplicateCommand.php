<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Neo4j\Neo4jConnectorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:neo4j:deduplicate',
    description: 'Remove duplicate Constraint/Risk/Assumption nodes and merge them',
)]
class Neo4jDeduplicateCommand extends Command
{
    public function __construct(
        private readonly Neo4jConnectorService $neo4jConnector
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔧 Neo4j Deduplication - Merge duplicate nodes');

        try {
            $client = $this->neo4jConnector->getClient();

            // Step 1: Find and merge duplicate Constraints
            $io->section('📋 Deduplicating Constraints...');
            $constraintResult = $client->run(
                'MATCH (c:Constraint)
                WITH c.description as desc, collect(c) as nodes
                WHERE size(nodes) > 1
                WITH nodes, head(nodes) as keeper, tail(nodes) as duplicates
                UNWIND duplicates as duplicate
                MATCH (duplicate)<-[r:HAS_CONSTRAINT]-(req)
                MERGE (req)-[:HAS_CONSTRAINT]->(keeper)
                DELETE r, duplicate
                RETURN count(duplicate) as merged'
            );
            $constraintsMerged = $constraintResult->first()->get('merged') ?? 0;
            $io->success("Merged {$constraintsMerged} duplicate Constraint nodes");

            // Step 2: Find and merge duplicate Risks
            $io->section('⚠️ Deduplicating Risks...');
            $riskResult = $client->run(
                'MATCH (r:Risk)
                WITH r.description as desc, collect(r) as nodes
                WHERE size(nodes) > 1
                WITH nodes, head(nodes) as keeper, tail(nodes) as duplicates
                UNWIND duplicates as duplicate
                MATCH (duplicate)<-[rel:HAS_RISK]-(req)
                MERGE (req)-[:HAS_RISK]->(keeper)
                DELETE rel, duplicate
                RETURN count(duplicate) as merged'
            );
            $risksMerged = $riskResult->first()->get('merged') ?? 0;
            $io->success("Merged {$risksMerged} duplicate Risk nodes");

            // Step 3: Find and merge duplicate Assumptions
            $io->section('💭 Deduplicating Assumptions...');
            $assumptionResult = $client->run(
                'MATCH (a:Assumption)
                WITH a.description as desc, collect(a) as nodes
                WHERE size(nodes) > 1
                WITH nodes, head(nodes) as keeper, tail(nodes) as duplicates
                UNWIND duplicates as duplicate
                MATCH (duplicate)<-[r:HAS_ASSUMPTION]-(req)
                MERGE (req)-[:HAS_ASSUMPTION]->(keeper)
                DELETE r, duplicate
                RETURN count(duplicate) as merged'
            );
            $assumptionsMerged = $assumptionResult->first()->get('merged') ?? 0;
            $io->success("Merged {$assumptionsMerged} duplicate Assumption nodes");

            // Step 4: Clean up orphaned nodes (no relationships)
            $io->section('🗑️ Cleaning up orphaned nodes...');
            $orphanResult = $client->run(
                'MATCH (n)
                WHERE (n:Constraint OR n:Risk OR n:Assumption)
                AND NOT (n)--()
                DELETE n
                RETURN count(n) as deleted'
            );
            $orphansDeleted = $orphanResult->first()->get('deleted') ?? 0;
            $io->success("Deleted {$orphansDeleted} orphaned nodes");

            // Summary
            $io->success(sprintf(
                'Deduplication complete! Merged %d Constraints, %d Risks, %d Assumptions. Deleted %d orphans.',
                $constraintsMerged,
                $risksMerged,
                $assumptionsMerged,
                $orphansDeleted
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to deduplicate: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}


