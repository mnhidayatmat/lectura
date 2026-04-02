<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Assessment Report</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- TOS Matrix --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <h3 class="font-semibold text-slate-900 dark:text-white">Table of Specification (TOS)</h3>
            </div>
            @if($course->assessments->isNotEmpty() && $course->learningOutcomes->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Assessment</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Weight</th>
                                @foreach($course->learningOutcomes as $clo)
                                    <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">{{ $clo->code }}</th>
                                @endforeach
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Bloom</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($tosMatrix as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white text-xs">{{ $row['title'] }}</td>
                                    <td class="px-3 py-3 text-center text-xs font-bold text-indigo-600">{{ number_format($row['weightage'], 0) }}%</td>
                                    @foreach($course->learningOutcomes as $clo)
                                        <td class="px-3 py-3 text-center">
                                            @if(in_array($clo->id, $row['clo_ids']))
                                                <span class="inline-flex w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 items-center justify-center text-[10px] font-bold">&#10003;</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-3 text-center">
                                        @if($row['bloom_level'])
                                            <span class="text-[10px] font-medium capitalize text-slate-600 dark:text-slate-300">{{ $row['bloom_level'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-10 text-center text-sm text-slate-400">No data available yet.</div>
            @endif
        </div>

        {{-- CLO Attainment --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-slate-900 dark:text-white mb-4">CLO Attainment</h3>
            @if(collect($cloAttainment)->whereNotNull('average')->isNotEmpty())
                <div class="space-y-3">
                    @foreach($cloAttainment as $id => $data)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $data['code'] }}</span>
                                <span class="text-xs font-medium {{ ($data['average'] ?? 0) >= 50 ? 'text-emerald-600' : 'text-red-500' }}">
                                    {{ $data['average'] !== null ? number_format($data['average'], 1) . '%' : 'No data' }}
                                </span>
                            </div>
                            <div class="h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ ($data['average'] ?? 0) >= 50 ? 'bg-emerald-500' : 'bg-red-400' }}" style="width: {{ $data['average'] ?? 0 }}%"></div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5 line-clamp-1">{{ $data['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-400 text-center py-4">No scores computed yet. Link assessments to quizzes/assignments and compute scores first.</p>
            @endif
        </div>
    </div>
</x-tenant-layout>
