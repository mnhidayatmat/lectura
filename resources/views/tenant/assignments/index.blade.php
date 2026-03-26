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
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-20 h-20 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No assignments yet</h3>
            <p class="text-sm text-slate-500 max-w-sm mx-auto mb-6">Create your first assignment with a rubric to start collecting student submissions.</p>
            <a href="{{ route('tenant.assignments.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Create Assignment</a>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Assignment</th>
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Course</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Marks</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Submissions</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Status</th>
                        <th class="text-right px-6 py-3 font-medium text-slate-500">Deadline</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($assignments as $a)
                            <tr class="hover:bg-slate-50/50 cursor-pointer" onclick="window.location='{{ route('tenant.assignments.show', [app('current_tenant')->slug, $a]) }}'">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-900">{{ $a->title }}</p>
                                    <p class="text-xs text-slate-400">{{ ucfirst($a->type) }} &middot; {{ ucfirst($a->marking_mode) }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-500">{{ $a->course->code }}</td>
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
    @endif
</x-tenant-layout>
