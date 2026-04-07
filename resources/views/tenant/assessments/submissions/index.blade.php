<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $assessment->title }} — Submissions</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} &middot; {{ $assessment->total_marks }} marks &middot; {{ ucfirst(str_replace('_', ' ', $assessment->type)) }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        @foreach([
            ['label' => 'Enrolled', 'value' => $stats['enrolled'], 'color' => 'slate'],
            ['label' => 'Submitted', 'value' => $stats['submitted'], 'color' => 'blue'],
            ['label' => 'Graded', 'value' => $stats['graded'], 'color' => 'amber'],
            ['label' => 'Released', 'value' => $stats['released'], 'color' => 'emerald'],
        ] as $stat)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                <p class="text-2xl font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">{{ $stat['value'] }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $stat['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Bulk Release --}}
    @if($scores->where('finalized_at', '!=', null)->where('is_released', false)->count() > 0)
        <div class="mb-6">
            <form method="POST" action="{{ route('tenant.assessments.scores.release', [$tenant->slug, $course, $assessment]) }}" x-data="{ confirmRelease: false }">
                @csrf
                <button type="button" @click="confirmRelease = true" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Release All Graded Marks ({{ $scores->where('finalized_at', '!=', null)->where('is_released', false)->count() }})
                </button>
                {{-- Confirm modal --}}
                <div x-show="confirmRelease" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="confirmRelease = false">
                    <div @click.outside="confirmRelease = false" class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 max-w-sm w-full mx-4 space-y-4">
                        <h3 class="font-semibold text-slate-900 dark:text-white">Release All Marks?</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Students will be able to see their marks and feedback. Notifications will be sent.</p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="confirmRelease = false" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition">Release</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Student List --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($enrolledStudents->isEmpty())
            <div class="p-10 text-center text-sm text-slate-400">No students enrolled in this course yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                        <th class="text-left px-6 py-3 font-medium text-slate-500 dark:text-slate-400">Student</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Status</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Files</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Late</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Marks</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-500 dark:text-slate-400">Released</th>
                        <th class="text-right px-6 py-3 font-medium text-slate-500 dark:text-slate-400">Action</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($enrolledStudents as $student)
                            @php
                                $sub = $submissions->get($student->id);
                                $score = $scores->get($student->id);
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-xs font-bold text-indigo-700 dark:text-indigo-400">{{ strtoupper(substr($student->name, 0, 1)) }}</div>
                                        <div>
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $student->name }}</span>
                                            <p class="text-[11px] text-slate-400">{{ $student->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($sub)
                                        @php $badge = $sub->status_badge; @endphp
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-900/20 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-400">{{ $badge['label'] }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">Not submitted</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-slate-500 dark:text-slate-400">
                                    {{ $sub ? $sub->files->count() . ' file(s)' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($sub && $sub->is_late)
                                        <span class="text-red-600 text-xs font-medium">Late</span>
                                    @elseif($sub)
                                        <span class="text-slate-400 text-xs">On time</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($score && $score->finalized_at)
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $score->raw_marks }}/{{ $score->max_marks }}</span>
                                        <span class="text-[10px] text-slate-400 ml-1">({{ $score->percentage }}%)</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($score && $score->is_released)
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-100 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Released</span>
                                            <form method="POST" action="{{ route('tenant.assessments.scores.unrelease', [$tenant->slug, $course, $assessment, $score]) }}">
                                                @csrf
                                                <button type="submit" class="text-slate-400 hover:text-red-500 transition" title="Retract">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878l4.242 4.242M21 21l-4.879-4.879"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($score && $score->finalized_at)
                                        <span class="text-xs text-amber-600 dark:text-amber-400">Pending</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if($sub)
                                        <a href="{{ route('tenant.assessments.submissions.show', [$tenant->slug, $course, $assessment, $sub]) }}" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">Review</a>
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
