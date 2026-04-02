<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.overview', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Course Assessment Plan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Assessment
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Weightage summary --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Total Weightage</h3>
                <span class="text-sm font-bold {{ $totalWeightage == 100 ? 'text-emerald-600' : ($totalWeightage > 100 ? 'text-red-600' : 'text-amber-600') }}">
                    {{ number_format($totalWeightage, 1) }}%
                    @if($totalWeightage == 100) <span class="text-emerald-500 ml-1">&#10003;</span> @endif
                </span>
            </div>
            <div class="h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all {{ $totalWeightage == 100 ? 'bg-emerald-500' : ($totalWeightage > 100 ? 'bg-red-500' : 'bg-indigo-500') }}" style="width: {{ min($totalWeightage, 100) }}%"></div>
            </div>
            @if($totalWeightage != 100)
                <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-2">
                    {{ $totalWeightage > 100 ? 'Weightage exceeds 100%. Please adjust.' : 'Remaining: ' . number_format(100 - $totalWeightage, 1) . '% to allocate.' }}
                </p>
            @endif

            {{-- CLO coverage --}}
            @if($course->learningOutcomes->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">CLO Coverage</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($course->learningOutcomes as $clo)
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-medium {{ $coveredCloIds->contains($clo->id) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' }}">
                                {{ $clo->code }}
                                @if($coveredCloIds->contains($clo->id))
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Assessment table --}}
        @if($course->assessments->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">No assessments defined yet. Start building your course assessment plan.</p>
                <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add First Assessment
                </a>
            </div>
        @else
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Assessment</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Type</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Weight</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Marks</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">CLOs</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Bloom</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Linked</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400">Status</th>
                                <th class="text-right px-5 py-3 font-medium text-slate-500 dark:text-slate-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($course->assessments as $assessment)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $assessment->title }}</p>
                                        @if($assessment->description)
                                            <p class="text-[11px] text-slate-400 line-clamp-1">{{ $assessment->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-xs font-medium text-slate-600 dark:text-slate-300 capitalize">{{ str_replace('_', ' ', $assessment->type) }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($assessment->weightage, 0) }}%</span>
                                    </td>
                                    <td class="px-3 py-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ number_format($assessment->total_marks, 0) }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <div class="flex flex-wrap justify-center gap-0.5">
                                            @foreach($assessment->clos as $clo)
                                                <span class="text-[9px] font-medium px-1.5 py-0.5 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 rounded">{{ $clo->code }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($assessment->bloom_level)
                                            @php
                                                $bloomColors = ['remember' => 'slate', 'understand' => 'blue', 'apply' => 'emerald', 'analyze' => 'amber', 'evaluate' => 'orange', 'create' => 'red'];
                                                $bc = $bloomColors[$assessment->bloom_level] ?? 'slate';
                                            @endphp
                                            <span class="text-[9px] font-medium px-1.5 py-0.5 bg-{{ $bc }}-100 dark:bg-{{ $bc }}-900/30 text-{{ $bc }}-700 dark:text-{{ $bc }}-400 rounded capitalize">{{ $assessment->bloom_level }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-xs {{ $assessment->items->count() > 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                            {{ $assessment->items->count() }} {{ Str::plural('item', $assessment->items->count()) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @php $badge = $assessment->status_badge; @endphp
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-900/30 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-400">{{ $badge['label'] }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $assessment]) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Edit</a>
                                            <form method="POST" action="{{ route('tenant.assessments.destroy', [$tenant->slug, $course, $assessment]) }}" onsubmit="return confirm('Delete this assessment?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Quick links --}}
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('tenant.assessment-reports.course', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:text-indigo-600 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                View Reports
            </a>
            @if($course->programme_id)
                <a href="{{ route('tenant.clo-plo.edit', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:text-indigo-600 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    CLO-PLO Mapping
                </a>
            @endif
        </div>
    </div>
</x-tenant-layout>
