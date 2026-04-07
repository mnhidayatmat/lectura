<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $assessment->title }} — Scores</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} &middot; {{ number_format($assessment->weightage, 0) }}% &middot; {{ number_format($assessment->total_marks, 0) }} marks</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('tenant.assessments.scores.compute', [$tenant->slug, $course, $assessment]) }}">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Compute
                    </button>
                </form>
                <a href="{{ route('tenant.assessments.scores.manual', [$tenant->slug, $course, $assessment]) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                    Manual Entry
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($scores->isEmpty())
            <div class="p-12 text-center text-sm text-slate-400">No scores yet. Click "Compute" to pull from linked items, or use "Manual Entry".</div>
        @else
            @php
                $avg = $scores->avg('percentage');
                $passing = $scores->where('percentage', '>=', 50)->count();
            @endphp
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-6">
                <div class="text-center">
                    <p class="text-2xl font-bold text-indigo-600">{{ number_format($avg, 1) }}%</p>
                    <p class="text-[10px] text-slate-400 uppercase">Average</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-emerald-600">{{ $passing }}/{{ $scores->count() }}</p>
                    <p class="text-[10px] text-slate-400 uppercase">Passing</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                            <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">#</th>
                            <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Student</th>
                            <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Marks</th>
                            <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Percentage</th>
                            <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Weighted</th>
                            <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Source</th>
                            <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Released</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($scores as $i => $score)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <td class="px-5 py-3 text-xs text-slate-400">{{ $i + 1 }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900 dark:text-white">{{ $score->user->name }}</td>
                                <td class="px-3 py-3 text-center text-xs">{{ number_format($score->raw_marks, 1) }}/{{ number_format($score->max_marks, 0) }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="text-xs font-bold {{ $score->percentage >= 50 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($score->percentage, 1) }}%</span>
                                </td>
                                <td class="px-3 py-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ number_format($score->weighted_marks, 2) }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded {{ $score->is_computed ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                        {{ $score->is_computed ? 'Auto' : 'Manual' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($score->is_released)
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Released</span>
                                    @else
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">Hidden</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-tenant-layout>
