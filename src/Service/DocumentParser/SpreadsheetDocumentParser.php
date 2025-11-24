<?php

declare(strict_types=1);

namespace App\Service\DocumentParser;

use App\Contract\DocumentParserInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

/**
 * Excel/Spreadsheet Document Parser
 * 
 * Native PHP parser for Excel files using PhpSpreadsheet
 * Supports: XLSX, XLS, CSV, ODS
 */
class SpreadsheetDocumentParser implements DocumentParserInterface
{
    private const SUPPORTED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // XLSX
        'application/vnd.ms-excel', // XLS
        'text/csv', // CSV
        'application/vnd.oasis.opendocument.spreadsheet', // ODS
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function extractText(string $filePath): string
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $extractedText = [];

            // Iterate through all sheets
            foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
                $sheetName = $sheet->getTitle();
                $extractedText[] = "=== Sheet: {$sheetName} ===\n";

                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Iterate through rows and columns
                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    
                    for ($col = 'A'; $col <= $highestColumn; $col++) {
                        $cellValue = $sheet->getCell("{$col}{$row}")->getValue();
                        
                        if ($cellValue !== null && $cellValue !== '') {
                            $rowData[] = $cellValue;
                        }
                    }

                    if (!empty($rowData)) {
                        $extractedText[] = implode(' | ', $rowData);
                    }
                }

                $extractedText[] = "\n";
            }

            $text = implode("\n", $extractedText);

            $this->logger->info('Spreadsheet extraction successful', [
                'file' => basename($filePath),
                'sheets_count' => $spreadsheet->getSheetCount(),
                'text_length' => strlen($text),
            ]);

            return $text;

        } catch (\Exception $e) {
            $this->logger->error('Spreadsheet extraction failed', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to extract text from spreadsheet: {$e->getMessage()}", 0, $e);
        }
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function getPriority(): int
    {
        // Higher priority than Tika for spreadsheets
        return 100;
    }

    public function getName(): string
    {
        return 'PHPSpreadsheet Native Parser';
    }
}

