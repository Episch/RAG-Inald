<?php

namespace App\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Service\FileStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Secured file storage management for admin users
 */
#[ApiResource(
    shortName: 'FileStorage',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/files',
            controller: FileStorageController::class . '::listFiles',
            description: 'List all stored files (extraction and LLM response files). Requires admin authentication.',
            normalizationContext: ['groups' => ['admin:read']],
            output: false,
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Get(
            uriTemplate: '/admin/files/{fileId}',
            controller: FileStorageController::class . '::getFile',
            description: 'Get specific file data by file ID. Requires admin authentication.',
            normalizationContext: ['groups' => ['admin:read']],
            output: false,
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Get(
            uriTemplate: '/admin/files/{fileId}/content',
            controller: FileStorageController::class . '::getFileContent',
            description: 'Get only the extracted content of a specific file. Requires admin authentication.',
            normalizationContext: ['groups' => ['admin:read']],
            output: false,
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(
            uriTemplate: '/admin/files/{fileId}',
            controller: FileStorageController::class . '::deleteFile',
            description: 'Delete a specific file by file ID. Requires admin authentication.',
            normalizationContext: ['groups' => ['admin:read']],
            output: false,
            security: "is_granted('ROLE_ADMIN')"
        )
    ]
)]
class FileStorageController extends AbstractController
{
    private FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Liste alle verfügbaren Dateien auf
     */
    public function listFiles(Request $request): JsonResponse
    {
        $type = $request->query->get('type', '');
        $files = $this->fileStorageService->listFiles($type);

        return new JsonResponse([
            'status' => 'success',
            'count' => count($files),
            'files' => $files,
            'available_types' => ['extraction', 'llm_response']
        ]);
    }

    /**
     * Hole Details einer spezifischen Datei
     */
    public function getFile(string $fileId): JsonResponse
    {
        // Versuche zuerst als Extraction-Datei
        $data = $this->fileStorageService->findExtractionFile($fileId);
        
        if ($data === null) {
            // Versuche als LLM-Datei
            $data = $this->fileStorageService->findLlmFile($fileId);
        }

        if ($data === null) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'File not found',
                'file_id' => $fileId
            ], 404);
        }

        return new JsonResponse([
            'status' => 'success',
            'file_id' => $fileId,
            'data' => $data
        ]);
    }

    /**
     * Hole nur den Extraction-Inhalt einer Datei
     */
    public function getFileContent(string $fileId): JsonResponse
    {
        $content = $this->fileStorageService->getExtractionContent($fileId);
        
        if ($content === null) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'File or content not found',
                'file_id' => $fileId
            ], 404);
        }

        return new JsonResponse([
            'status' => 'success',
            'file_id' => $fileId,
            'content' => $content
        ]);
    }

    /**
     * Lösche eine Datei
     */
    public function deleteFile(string $fileId): JsonResponse
    {
        $success = $this->fileStorageService->deleteFile($fileId);
        
        if (!$success) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'File not found or could not be deleted',
                'file_id' => $fileId
            ], 404);
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'File deleted successfully',
            'file_id' => $fileId
        ]);
    }
}
