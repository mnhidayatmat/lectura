<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Manual Score Entry</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->title }} &middot; Max {{ number_format($assessment->total_marks, 0) }} marks</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.assessments.scores.store-manual', [$tenant->slug, $course, $assessment]) }}">
        @csrf

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                            <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">#</th>
                            <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Student</th>
                            <th class="text-center px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Marks (/ {{ number_format($assessment->total_marks, 0) }})</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($students as $i => $student)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <td class="px-5 py-2.5 text-xs text-slate-400">{{ $i + 1 }}</td>
                                <td class="px-5 py-2.5 font-medium text-slate-900 dark:text-white text-xs">{{ $student->name }}</td>
                                <td class="px-5 py-2.5 text-center">
                                    <input type="number" name="marks[{{ $student->id }}]" value="{{ $existingScores[$student->id] ?? '' }}" min="0" max="{{ $assessment->total_marks }}" step="0.5" placeholder="—" class="w-24 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-center focus:ring-2 focus:ring-indigo-500">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save All Marks</button>
        </div>
    </form>
</x-tenant-layout>
