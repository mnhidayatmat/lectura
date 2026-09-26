<a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md hover:border-indigo-200 transition group">
    <div class="flex items-start justify-between mb-4">
        <x-course-avatar :course="$course" size="md" class="group-hover:scale-105 transition" />
        @php $badge = $course->statusBadge; @endphp
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
    </div>
    <h3 class="font-semibold text-slate-900 group-hover:text-indigo-700 transition">{{ $course->code }}</h3>
    <p class="text-sm text-slate-500 mt-0.5 line-clamp-1">{{ $course->title }}</p>
    <div class="mt-4 flex items-center gap-4 text-xs text-slate-400">
        <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            {{ $course->sections_count }} sections
        </span>
        <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ $course->num_weeks }} weeks
        </span>
        <span>{{ str_replace('_', ' ', ucfirst($course->teaching_mode)) }}</span>
    </div>
    @php
        $semesters = $course->sections->map(fn ($section) => $section->academicTerm ?? $course->academicTerm)
            ->filter()->unique('id')->sortByDesc('start_date');
    @endphp
    @if($semesters->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1">
            @foreach($semesters as $semester)
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $semester->isCurrent() ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-500' }}">{{ $semester->name }}</span>
            @endforeach
        </div>
    @endif
</a>
