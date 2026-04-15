@php
    $student = $mentorship->student;
    $detail = $mentorship->liDetail;
@endphp
<a href="{{ route('tenant.mentees.show', [$tenant->slug, $student]) }}"
   class="block bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-400 hover:shadow-md transition">
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-lg flex-shrink-0">
            @if($student->avatar_url)
                <img src="{{ $student->avatar_url }}" alt="{{ $student->name }}" class="w-full h-full rounded-full object-cover">
            @else
                {{ strtoupper(substr($student->name, 0, 1)) }}
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $student->name }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $student->email }}</p>

            @if($mentorship->academicTerm)
                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">{{ $mentorship->academicTerm->name }}</p>
            @endif

            @if($mentorship->isLiSupervisor() && $detail)
                <div class="mt-3 space-y-1">
                    @if($detail->company_name)
                        <p class="text-xs text-slate-600 dark:text-slate-300 truncate">
                            <span class="inline-block w-4">🏢</span> {{ $detail->company_name }}
                        </p>
                    @endif
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide
                        @switch($detail->placement_status)
                            @case('ongoing') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 @break
                            @case('completed') bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 @break
                            @case('terminated') bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 @break
                            @default bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300
                        @endswitch">
                        {{ str_replace('_', ' ', $detail->placement_status) }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</a>
