<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CourseAttendanceDetailSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected array $reportData,
    ) {}

    public function title(): string
    {
        return 'Detail';
    }

    public function headings(): array
    {
        $headers = ['#', 'Student Name'];

        foreach ($this->reportData['sessions'] as $session) {
            $label = 'W' . ($session->week_number ?? '?');
            $label .= ' ' . ucfirst($session->session_type);
            $label .= "\n" . ($session->started_at?->format('d M') ?? '');
            $headers[] = $label;
        }

        return $headers;
    }

    public function array(): array
    {
        $rows = [];
        $i = 1;
        $statusMap = ['present' => 'P', 'late' => 'L', 'absent' => 'A', 'excused' => 'E'];

        foreach ($this->reportData['students'] as $student) {
            $row = [$i++, $student->name];

            foreach ($this->reportData['sessions'] as $session) {
                $status = $this->reportData['matrix'][$student->id][$session->id] ?? null;
                $row[] = $status ? ($statusMap[$status] ?? $status) : '-';
            }

            $rows[] = $row;
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
