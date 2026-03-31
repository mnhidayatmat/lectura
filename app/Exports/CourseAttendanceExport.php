<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CourseAttendanceExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $reportData,
    ) {}

    public function sheets(): array
    {
        return [
            'Summary' => new CourseAttendanceSummarySheet($this->reportData),
            'Detail' => new CourseAttendanceDetailSheet($this->reportData),
        ];
    }
}
