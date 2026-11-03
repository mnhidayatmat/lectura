<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Teaching Portfolio - {{ $course->code }}</title>
    <style>
        @page { margin: 22mm 16mm 18mm; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 10.5px; color: #1e293b; margin: 0; }
        .cover { border-bottom: 3px solid #4c3fbf; padding-bottom: 14px; margin-bottom: 18px; }
        .eyebrow { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #6d5ce7; font-weight: bold; }
        h1 { font-size: 22px; margin: 6px 0 2px; color: #1e1b4b; }
        .subtitle { font-size: 13px; color: #475569; margin-bottom: 10px; }
        .meta td { padding: 2px 14px 2px 0; font-size: 10px; color: #475569; }
        .meta td.k { color: #94a3b8; }
        .stats { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 6px 0; margin: 4px -6px 6px; }
        .stats td { background: #f5f3ff; border-radius: 6px; padding: 8px 10px; text-align: center; }
        .stats .n { font-size: 18px; font-weight: bold; color: #4c3fbf; }
        .stats .l { font-size: 9px; color: #64748b; }
        .cats { font-size: 9.5px; color: #475569; margin-top: 6px; }
        .chip { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 8px; padding: 1px 7px; margin: 0 3px 3px 0; }
        .week { page-break-inside: auto; margin-top: 16px; }
        .week-head { border-left: 4px solid #6d5ce7; padding: 2px 0 2px 8px; margin-bottom: 8px; page-break-after: avoid; }
        .week-head .t { font-size: 14px; font-weight: bold; color: #1e1b4b; }
        .week-head .d { font-size: 9.5px; color: #64748b; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 0 8px; table-layout: fixed; }
        table.grid td { width: 50%; vertical-align: top; padding: 0 5px; }
        .photo { border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px; page-break-inside: avoid; }
        .photo img { width: 100%; max-height: 200px; border-radius: 4px; }
        .noimg { height: 120px; background: #f1f5f9; color: #94a3b8; text-align: center; line-height: 120px; border-radius: 4px; }
        .cap { font-weight: bold; font-size: 10.5px; margin-top: 5px; color: #0f172a; }
        .desc { font-size: 9.5px; color: #475569; margin-top: 2px; }
        .info { font-size: 8.5px; color: #64748b; margin-top: 4px; }
        .empty { text-align: center; color: #94a3b8; padding: 40px 0; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @php
        $tz = $tenant->timezone ?: config('app.timezone');
    @endphp
    <div class="footer">{{ $course->code }} Teaching Portfolio &middot; {{ $lecturer->name }} &middot; Generated {{ now()->format('d M Y') }} by Lectura</div>

    <div class="cover">
        <div class="eyebrow">Teaching Portfolio</div>
        <h1>{{ $course->code }} &mdash; {{ $course->title }}</h1>
        <div class="subtitle">{{ $tenant->name }}</div>
        <table class="meta">
            <tr><td class="k">Lecturer</td><td>{{ $lecturer->name }}</td></tr>
            @if($course->academicTerm)
                <tr><td class="k">Semester</td><td>{{ $course->academicTerm->name }}</td></tr>
            @endif
            @php
                $dated = $timeline->flatMap(fn ($g) => $g['photos'])->pluck('taken_at')->filter();
            @endphp
            @if($dated->isNotEmpty())
                <tr><td class="k">Period</td><td>{{ $dated->min()->timezone($tz)->format('d M Y') }} &ndash; {{ $dated->max()->timezone($tz)->format('d M Y') }}</td></tr>
            @endif
        </table>
        <table class="stats">
            <tr>
                <td><div class="n">{{ $photoCount }}</div><div class="l">Photos</div></td>
                <td><div class="n">{{ $timeline->whereNotNull('week')->count() }}</div><div class="l">Weeks documented</div></td>
                <td><div class="n">{{ $timeline->flatMap(fn ($g) => $g['photos'])->pluck('attendance_session_id')->filter()->unique()->count() }}</div><div class="l">Class sessions</div></td>
                <td><div class="n">{{ $categoryCounts->count() }}</div><div class="l">Activity types</div></td>
            </tr>
        </table>
        @if($categoryCounts->isNotEmpty())
            <div class="cats">
                @foreach($categoryCounts as $category => $count)
                    <span class="chip">{{ \App\Models\PortfolioPhoto::CATEGORIES[$category] ?? ucfirst($category) }} &middot; {{ $count }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @forelse($timeline as $group)
        <div class="week">
            <div class="week-head">
                <div class="t">{{ $group['label'] }}</div>
                <div class="d">
                    @if($group['from'])
                        {{ $group['from']->copy()->timezone($tz)->format('d M Y') }}@if($group['to'] && ! $group['to']->copy()->timezone($tz)->isSameDay($group['from']->copy()->timezone($tz))) &ndash; {{ $group['to']->copy()->timezone($tz)->format('d M Y') }}@endif
                        &middot;
                    @endif
                    {{ $group['photos']->count() }} {{ \Illuminate\Support\Str::plural('photo', $group['photos']->count()) }}
                </div>
            </div>
            <table class="grid">
                @foreach($group['photos']->chunk(2) as $row)
                    <tr>
                        @foreach($row as $photo)
                            <td>
                                <div class="photo">
                                    @if($images[$photo->id] ?? null)
                                        <img src="{{ $images[$photo->id] }}" alt="">
                                    @else
                                        <div class="noimg">Image unavailable</div>
                                    @endif
                                    @if($photo->caption)
                                        <div class="cap">{{ $photo->caption }}</div>
                                    @endif
                                    @if($photo->description)
                                        <div class="desc">{{ $photo->description }}</div>
                                    @endif
                                    <div class="info">
                                        {{ $photo->category_label }}
                                        @if($photo->section) &middot; {{ $photo->section->name }} @endif
                                        @if($photo->taken_at) &middot; {{ $photo->taken_at->copy()->timezone($tz)->format('D, d M Y, g:i A') }} @endif
                                    </div>
                                </div>
                            </td>
                        @endforeach
                        @if($row->count() === 1)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @empty
        <div class="empty">No photos yet. Add photos from Lectura Go or the Portfolio page.</div>
    @endforelse
</body>
</html>
