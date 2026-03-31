<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report - {{ $course->code }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 20px 0 8px; color: #475569; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
        .meta { font-size: 10px; color: #64748b; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #e2e8f0; padding: 5px 8px; text-align: center; font-size: 10px; }
        th { background-color: #f8fafc; font-weight: 600; color: #475569; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .present { color: #059669; font-weight: bold; }
        .late { color: #d97706; font-weight: bold; }
        .absent { color: #dc2626; font-weight: bold; }
        .excused { color: #2563eb; font-weight: bold; }
        .rate-good { color: #059669; }
        .rate-warn { color: #d97706; }
        .rate-bad { color: #dc2626; }
        .warning-badge { background: #fef3c7; color: #92400e; padding: 1px 6px; border-radius: 8px; font-size: 9px; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>Attendance Report: {{ $course->code }} - {{ $course->title }}</h1>
    <div class="meta">
        @if($section)
            Section: {{ $section->name }} &middot;
        @endif
        Sessions: {{ $sessions->count() }} &middot;
        Students: {{ $students->count() }} &middot;
        Generated: {{ now()->format('d M Y, h:i A') }}
    </div>

    <h2>Student Summary</h2>
    <table>
        <thead>
            <tr>
                <th class="text-left">#</th>
                <th class="text-left">Student</th>
                <th>Present</th>
                <th>Late</th>
                <th>Absent</th>
                <th>Excused</th>
                <th>Total</th>
                <th>Rate</th>
                <th>Warning</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summary as $i => $row)
                <tr>
                    <td class="text-left">{{ $i + 1 }}</td>
                    <td class="text-left">{{ $row['student']->name }}</td>
                    <td class="present">{{ $row['present'] }}</td>
                    <td class="late">{{ $row['late'] }}</td>
                    <td class="absent">{{ $row['absent'] }}</td>
                    <td class="excused">{{ $row['excused'] }}</td>
                    <td>{{ $row['total'] }}</td>
                    <td class="{{ $row['rate'] >= 80 ? 'rate-good' : ($row['rate'] >= 60 ? 'rate-warn' : 'rate-bad') }}">
                        {{ $row['rate'] }}%
                    </td>
                    <td>
                        @if($row['warning_level'])
                            <span class="warning-badge">W{{ $row['warning_level'] }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($sessions->count() > 0 && $sessions->count() <= 20)
        <h2>Session Detail</h2>
        <table>
            <thead>
                <tr>
                    <th class="text-left">Student</th>
                    @foreach($sessions as $session)
                        <th>
                            W{{ $session->week_number ?? '?' }}<br>
                            <span style="font-weight:normal;font-size:8px;">{{ $session->started_at?->format('d/m') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($students as $student)
                    <tr>
                        <td class="text-left">{{ $student->name }}</td>
                        @foreach($sessions as $session)
                            @php
                                $st = $matrix[$student->id][$session->id] ?? null;
                            @endphp
                            <td class="{{ $st }}">
                                {{ match($st) { 'present' => 'P', 'late' => 'L', 'absent' => 'A', 'excused' => 'E', default => '-' } }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Lectura Attendance Report &middot; {{ $course->code }} &middot; Generated {{ now()->format('d M Y, h:i A') }}
    </div>
</body>
</html>
