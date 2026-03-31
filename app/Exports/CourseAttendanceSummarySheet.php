<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CourseAttendanceSummarySheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected array $reportData,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function headings(): array
    {
        return ['#', 'Student Name', 'Email', 'Present', 'Late', 'Absent', 'Excused', 'Total', 'Rate (%)', 'Warning'];
    }

    public function array(): array
    {
        $rows = [];
        $i = 1;

        foreach ($this->reportData['summary'] as $row) {
            $rows[] = [
                $i++,
                $row['student']->name,
                $row['student']->email,
                $row['present'],
                $row['late'],
                $row['absent'],
                $row['excused'],
                $row['total'],
                $row['rate'],
                $row['warning_level'] ? "Level {$row['warning_level']}" : '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
