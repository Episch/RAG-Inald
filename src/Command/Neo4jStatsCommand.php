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
    name: 'app:neo4j:stats',
    description: 'Show Neo4j database statistics and sample requirements',
)]
class Neo4jStatsCommand extends Command
{
    public function __construct(
        private readonly Neo4jConnectorService $neo4jConnector
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('📊 Neo4j Database Statistics');

        try {
            $client = $this->neo4jConnector->getClient();

            // Count Requirements
            $reqResult = $client->run('MATCH (req:SoftwareRequirement) RETURN count(req) as total');
            $totalReqs = $reqResult->first()->get('total');

            // Count Applications
            $appResult = $client->run('MATCH (app:SoftwareApplication) RETURN count(app) as total');
            $totalApps = $appResult->first()->get('total');

            // Count other nodes
            $personResult = $client->run('MATCH (p:Person) RETURN count(p) as total');
            $totalPersons = $personResult->first()->get('total');

            $riskResult = $client->run('MATCH (r:Risk) RETURN count(r) as total');
            $totalRisks = $riskResult->first()->get('total');

            $constraintResult = $client->run('MATCH (c:Constraint) RETURN count(c) as total');
            $totalConstraints = $constraintResult->first()->get('total');

            $assumptionResult = $client->run('MATCH (a:Assumption) RETURN count(a) as total');
            $totalAssumptions = $assumptionResult->first()->get('total');

            // Display counts
            $io->section('📈 Node Counts');
            $io->table(
                ['Node Type', 'Count'],
                [
                    ['SoftwareRequirement', $totalReqs],
                    ['SoftwareApplication', $totalApps],
                    ['Person', $totalPersons],
                    ['Risk', $totalRisks],
                    ['Constraint', $totalConstraints],
                    ['Assumption', $totalAssumptions],
                ]
            );

            // Show sample requirements
            $io->section('📋 Sample Requirements (first 10)');
            $sampleResult = $client->run(
                'MATCH (req:SoftwareRequirement) 
                RETURN req.identifier as id, req.name as name, req.category as category, req.priority as priority
                ORDER BY req.identifier
                LIMIT 10'
            );

            $samples = [];
            foreach ($sampleResult as $record) {
                $samples[] = [
                    $record->get('id'),
                    substr($record->get('name'), 0, 50),
                    $record->get('category'),
                    $record->get('priority'),
                ];
            }

            if (!empty($samples)) {
                $io->table(['ID', 'Name', 'Category', 'Priority'], $samples);
            } else {
                $io->warning('No requirements found in database!');
            }

            // Show requirements per project
            $io->section('📊 Requirements per Project');
            $projectResult = $client->run(
                'MATCH (app:SoftwareApplication)-[:HAS_REQUIREMENT]->(req:SoftwareRequirement)
                RETURN app.name as project, count(req) as req_count
                ORDER BY req_count DESC'
            );

            $projects = [];
            foreach ($projectResult as $record) {
                $projects[] = [
                    $record->get('project'),
                    $record->get('req_count'),
                ];
            }

            if (!empty($projects)) {
                $io->table(['Project', 'Requirements'], $projects);
            }

            $io->success("Total: {$totalReqs} requirements in {$totalApps} projects");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to query Neo4j: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

