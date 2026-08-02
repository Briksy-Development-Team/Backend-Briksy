<?php

namespace App\Services\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ImportTemplateService
{
    public function build(array $fields, array $sampleRow, string $format, string $sheetName, string $filename): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        foreach (array_values($fields) as $index => $field) {
            $columnIndex = $index + 1;
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->setCellValue("{$column}1", $field['label'] ?? $field['key']);
            $sheet->setCellValue("{$column}2", $sampleRow[$field['key']] ?? '');
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $directory = storage_path('app/import-templates');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create import template directory.');
        }

        $format = strtolower($format);
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename.'.'.$format;

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setSheetIndex(0);
            $writer->save($path);
        } elseif ($format === 'xlsx') {
            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
        } else {
            throw new RuntimeException("Unsupported template format: {$format}");
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $path;
    }
}
