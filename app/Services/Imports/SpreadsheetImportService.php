<?php

namespace App\Services\Imports;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class SpreadsheetImportService
{
    public function detectFileType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => 'csv',
            'xlsx', 'xlsm' => 'xlsx',
            default => throw new RuntimeException("Unsupported import file type: {$extension}"),
        };
    }

    public function readHeaders(string $absolutePath, string $fileType): array
    {
        $iterator = $this->iterateRows($absolutePath, $fileType);

        foreach ($iterator as $row) {
            return array_keys($row);
        }

        return [];
    }

    public function iterateRows(string $absolutePath, string $fileType): Generator
    {
        return match ($fileType) {
            'csv' => $this->iterateCsvRows($absolutePath),
            'xlsx' => $this->iterateXlsxRows($absolutePath),
            default => throw new RuntimeException("Unsupported import file type: {$fileType}"),
        };
    }

    private function iterateCsvRows(string $absolutePath): Generator
    {
        $file = new \SplFileObject($absolutePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',');

        $headers = [];
        $rowNumber = 0;

        foreach ($file as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $rowNumber++;

            if ($rowNumber === 1) {
                $headers = array_map(
                    static fn ($header) => is_string($header) ? trim($header) : (string) $header,
                    $row,
                );

                continue;
            }

            $values = [];
            foreach ($headers as $index => $header) {
                $values[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            yield $values;
        }
    }

    private function iterateXlsxRows(string $absolutePath): Generator
    {
        /** @var Xlsx $reader */
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($absolutePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();

        $headers = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowValues = $worksheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false);
            $cells = $rowValues[0] ?? [];

            if ($row === 1) {
                $headers = array_map(
                    static fn ($header) => is_string($header) ? trim($header) : (string) $header,
                    $cells,
                );

                continue;
            }

            $values = [];
            foreach ($headers as $index => $header) {
                $values[$header] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            yield $values;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
