<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Assignments</h2>
                <p class="mt-1 text-sm text-slate-500">Create assignments, collect submissions, and mark with AI assistance</p>
            </div>
            <a href="{{ route('tenant.assignments.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Assignment
            </a>
        </div>
    </x-slot>

    @if($assignments->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
            <div class="w-20 h-20 bg-gradient-to-br from-amber-100 to-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No assignments yet</h3>
            <p class="text-sm text-slate-500 max-w-sm mx-auto mb-6">Create your first assignment with a rubric to start collecting student submissions.</p>
            <a href="{{ route('tenant.assignments.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Create Assignment</a>
        </div>
    @else
        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $assignments->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-emerald-200 p-4">
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Published</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $assignments->where('status', 'published')->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-amber-200 p-4">
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Drafts</p>
                <p class="text-2xl font-bold text-amber-700 mt-1">{{ $assignments->where('status', 'draft')->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-indigo-200 p-4">
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Submissions</p>
                <p class="text-2xl font-bold text-indigo-700 mt-1">{{ $assignments->sum('submissions_count') }}</p>
            </div>
        </div>

        {{-- Grouped by Course --}}
        <div class="space-y-8">
            @foreach($courses as $course)
                @php $courseAssignments = $assignments->where('course_id', $course->id); @endphp
                @if($courseAssignments->isNotEmpty())
                    <div>
                        {{-- Course Header --}}
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center shadow-sm">
                                    <span class="text-xs font-bold text-white">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">{{ $course->code }} — {{ $course->title }}</h3>
                                    <p class="text-xs text-slate-400">{{ $courseAssignments->count() }} {{ Str::plural('assignment', $courseAssignments->count()) }} &middot; {{ $courseAssignments->sum('submissions_count') }} submissions</p>
                                </div>
                            </div>
                        </div>

                        {{-- Assignments Table --}}
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 bg-slate-50/50">
                                            <th class="text-left px-6 py-3 font-medium text-slate-500">Assignment</th>
                                            <th class="text-center px-6 py-3 font-medium text-slate-500">Marks</th>
                                            <th class="text-center px-6 py-3 font-medium text-slate-500">Submissions</th>
                                            <th class="text-center px-6 py-3 font-medium text-slate-500">Status</th>
                                            <th class="text-right px-6 py-3 font-medium text-slate-500">Deadline</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($courseAssignments as $a)
                                            <tr class="hover:bg-slate-50/50 cursor-pointer transition" onclick="window.location='{{ route('tenant.assignments.show', [app('current_tenant')->slug, $a]) }}'">
                                                <td class="px-6 py-4">
                                                    <p class="font-medium text-slate-900">{{ $a->title }}</p>
                                                    <p class="text-xs text-slate-400">{{ ucfirst($a->type) }} &middot; {{ ucfirst($a->marking_mode) }}</p>
                                                </td>
                                                <td class="px-6 py-4 text-center font-medium text-slate-700">{{ $a->total_marks }}</td>
                                                <td class="px-6 py-4 text-center font-medium text-indigo-600">{{ $a->submissions_count }}</td>
                                                <td class="px-6 py-4 text-center">
                                                    @php
                                                        $colors = ['draft' => 'amber', 'published' => 'emerald', 'closed' => 'slate', 'graded' => 'indigo'];
                                                        $c = $colors[$a->status] ?? 'slate';
                                                    @endphp
                                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">{{ ucfirst($a->status) }}</span>
                                                </td>
                                                <td class="px-6 py-4 text-right text-xs text-slate-400">{{ $a->deadline?->format('d M Y, H:i') ?? 'No deadline' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</x-tenant-layout>
