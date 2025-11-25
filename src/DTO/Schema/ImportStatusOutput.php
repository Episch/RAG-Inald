<?php

declare(strict_types=1);

namespace App\DTO\Schema;

use ApiPlatform\Metadata\ApiProperty;

/**
 * Output DTO für Import-Status Übersicht mit Statistiken
 */
class ImportStatusOutput
{
    #[ApiProperty(
        description: 'Gesamtzahl aller Import-Jobs',
        example: 42
    )]
    public int $totalJobs = 0;

    #[ApiProperty(
        description: 'Anzahl aktiver/laufender Jobs',
        example: 2
    )]
    public int $activeJobs = 0;

    #[ApiProperty(
        description: 'Anzahl erfolgreich abgeschlossener Jobs',
        example: 35
    )]
    public int $completedJobs = 0;

    #[ApiProperty(
        description: 'Anzahl fehlgeschlagener Jobs',
        example: 5
    )]
    public int $failedJobs = 0;

    #[ApiProperty(
        description: 'Gesamtzahl extrahierter Requirements (über alle erfolgreichen Jobs)',
        example: 1247
    )]
    public int $totalRequirementsExtracted = 0;

    #[ApiProperty(
        description: 'Neuester/Letzter Job (falls vorhanden)'
    )]
    public ?RequirementExtractionJobOutput $latestJob = null;

    #[ApiProperty(
        description: 'Liste aller Jobs (chronologisch, neueste zuerst)',
        openapiContext: [
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/RequirementExtractionJobOutput']
        ]
    )]
    public array $jobs = [];

    #[ApiProperty(
        description: 'Statistiken pro Projekt',
        openapiContext: [
            'type' => 'object',
            'additionalProperties' => [
                'type' => 'object',
                'properties' => [
                    'projectName' => ['type' => 'string'],
                    'jobCount' => ['type' => 'integer'],
                    'totalRequirements' => ['type' => 'integer'],
                    'lastImport' => ['type' => 'string', 'format' => 'date-time']
                ]
            ]
        ]
    )]
    public array $projectStats = [];

    #[ApiProperty(
        description: 'Fehlgeschlagene Jobs mit Fehlerdetails',
        openapiContext: [
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/RequirementExtractionJobOutput']
        ]
    )]
    public array $recentFailures = [];
}

