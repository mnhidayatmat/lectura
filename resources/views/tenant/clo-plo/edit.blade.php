<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">CLO-PLO Mapping</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if($plos->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">No Programme Learning Outcomes (PLOs) defined yet for {{ $course->programme?->name ?? 'this programme' }}.</p>
            @if($course->programme)
                <a href="{{ route('tenant.plos.index', [$tenant->slug, $course->programme]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Manage PLOs
                </a>
            @endif
        </div>
    @elseif($clos->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
            <p class="text-sm text-slate-500 dark:text-slate-400">No Course Learning Outcomes (CLOs) defined yet for this course.</p>
        </div>
    @else
        <form method="POST" action="{{ route('tenant.clo-plo.update', [$tenant->slug, $course]) }}">
            @csrf @method('PUT')

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Mapping Matrix</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Select mapping level: P = Primary, S = Secondary, empty = unmapped</p>
                    </div>
                    @if($course->programme)
                        <a href="{{ route('tenant.plos.index', [$tenant->slug, $course->programme]) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Manage PLOs</a>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400 min-w-[200px]">CLO</th>
                                @foreach($plos as $plo)
                                    <th class="text-center px-2 py-3 font-medium text-slate-500 dark:text-slate-400 min-w-[80px]">
                                        <div class="text-xs">{{ $plo->code }}</div>
                                        @if($plo->domain)
                                            <div class="text-[9px] text-slate-400 font-normal">{{ ucfirst(str_replace('_', ' ', $plo->domain)) }}</div>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($clos as $clo)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                    <td class="px-4 py-3">
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $clo->code }}</span>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2">{{ $clo->description }}</p>
                                    </td>
                                    @foreach($plos as $plo)
                                        @php $currentLevel = $mappings[$clo->id][$plo->id] ?? ''; @endphp
                                        <td class="px-2 py-3 text-center" x-data="{ level: '{{ $currentLevel }}' }">
                                            <input type="hidden" name="mappings[{{ $clo->id }}][{{ $plo->id }}]" :value="level">
                                            <button type="button" @click="level = level === '' ? 'primary' : (level === 'primary' ? 'secondary' : '')"
                                                :class="{
                                                    'bg-indigo-600 text-white border-indigo-600': level === 'primary',
                                                    'bg-amber-500 text-white border-amber-500': level === 'secondary',
                                                    'bg-white dark:bg-slate-800 text-slate-300 border-slate-200 dark:border-slate-600': level === ''
                                                }"
                                                class="w-8 h-8 rounded-lg border-2 text-[10px] font-bold transition-all duration-150 hover:scale-110">
                                                <span x-text="level === 'primary' ? 'P' : (level === 'secondary' ? 'S' : '')"></span>
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-4">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save Mapping</button>
                <div class="flex items-center gap-3 text-[11px] text-slate-400">
                    <span class="flex items-center gap-1"><span class="w-4 h-4 rounded bg-indigo-600 inline-block"></span> P = Primary</span>
                    <span class="flex items-center gap-1"><span class="w-4 h-4 rounded bg-amber-500 inline-block"></span> S = Secondary</span>
                    <span class="flex items-center gap-1"><span class="w-4 h-4 rounded bg-white border border-slate-200 inline-block"></span> Unmapped</span>
                </div>
            </div>
        </form>
    @endif
</x-tenant-layout>
