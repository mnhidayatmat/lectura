<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Mentorship Assignments</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Assign academic tutors and LI supervisors to students</p>
            </div>
            <a href="{{ route('tenant.admin.mentorships.create', $tenant->slug) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                + New Assignment
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('tenant.admin.mentorships.index', $tenant->slug) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium {{ ! $role ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">All</a>
        <a href="{{ route('tenant.admin.mentorships.index', [$tenant->slug, 'role' => 'academic_tutor']) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $role === 'academic_tutor' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">Academic Tutors</a>
        <a href="{{ route('tenant.admin.mentorships.index', [$tenant->slug, 'role' => 'li_supervisor']) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $role === 'li_supervisor' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">LI Supervisors</a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($mentorships->isEmpty())
            <p class="p-8 text-sm text-slate-500 text-center">No mentorship assignments yet.</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Lecturer</th>
                        <th class="px-4 py-3 text-left">Student</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Term</th>
                        <th class="px-4 py-3 text-left">Assigned</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($mentorships as $m)
                        <tr>
                            <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $m->lecturer->name }}</td>
                            <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $m->student->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase
                                    {{ $m->isLiSupervisor() ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }}">
                                    {{ $m->roleLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ optional($m->academicTerm)->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ optional($m->assigned_at)->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('tenant.admin.mentorships.destroy', [$tenant->slug, $m]) }}" class="inline"
                                      onsubmit="return confirm('Revoke this mentorship?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-tenant-layout>
