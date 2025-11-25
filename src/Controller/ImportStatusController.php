<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Schema\ImportStatusOutput;
use App\DTO\Schema\RequirementExtractionJobOutput;
use App\State\RequirementExtractionProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller für Import-Status Übersicht
 */
class ImportStatusController extends AbstractController
{
    #[Route('/api/requirements/import-status', name: 'api_requirements_import_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getImportStatus(): JsonResponse
    {
        $allJobs = RequirementExtractionProcessor::getAllJobs();
        
        $status = new ImportStatusOutput();
        $status->totalJobs = count($allJobs);
        
        // Statistiken berechnen
        $activeJobs = [];
        $completedJobs = [];
        $failedJobs = [];
        $totalRequirements = 0;
        $projectStats = [];
        
        foreach ($allJobs as $job) {
            // Jobs nach Status gruppieren
            switch ($job->status) {
                case 'pending':
                case 'processing':
                    $activeJobs[] = $job;
                    break;
                case 'completed':
                    $completedJobs[] = $job;
                    // Requirements zählen
                    if ($job->result && isset($job->result->requirements)) {
                        $requirementsCount = count($job->result->requirements);
                        $totalRequirements += $requirementsCount;
                        
                        // Projekt-Statistiken
                        $projectName = $job->projectName;
                        if (!isset($projectStats[$projectName])) {
                            $projectStats[$projectName] = [
                                'projectName' => $projectName,
                                'jobCount' => 0,
                                'totalRequirements' => 0,
                                'lastImport' => null,
                            ];
                        }
                        $projectStats[$projectName]['jobCount']++;
                        $projectStats[$projectName]['totalRequirements'] += $requirementsCount;
                        
                        if (!$projectStats[$projectName]['lastImport'] || 
                            $job->completedAt > new \DateTimeImmutable($projectStats[$projectName]['lastImport'])) {
                            $projectStats[$projectName]['lastImport'] = $job->completedAt?->format('c');
                        }
                    }
                    break;
                case 'failed':
                    $failedJobs[] = $job;
                    break;
            }
        }
        
        $status->activeJobs = count($activeJobs);
        $status->completedJobs = count($completedJobs);
        $status->failedJobs = count($failedJobs);
        $status->totalRequirementsExtracted = $totalRequirements;
        
        // Neuester Job
        $status->latestJob = !empty($allJobs) 
            ? RequirementExtractionJobOutput::fromJob($allJobs[0]) 
            : null;
        
        // Alle Jobs (als DTOs)
        $status->jobs = array_map(
            fn($job) => RequirementExtractionJobOutput::fromJob($job),
            $allJobs
        );
        
        // Projekt-Statistiken
        $status->projectStats = array_values($projectStats);
        
        // Letzte Fehler (max 5)
        $status->recentFailures = array_map(
            fn($job) => RequirementExtractionJobOutput::fromJob($job),
            array_slice($failedJobs, 0, 5)
        );
        
        return $this->json($status);
    }
}

