<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assignments.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $assignment->title }}</h2>
                    <p class="text-sm text-slate-500">{{ $assignment->course->code }} &middot; {{ $assignment->total_marks }} marks &middot; {{ $assignment->deadline?->format('d M Y, H:i') ?? 'No deadline' }} &middot; {{ ucfirst($assignment->submission_type ?? 'file') }} submission</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($assignment->status === 'draft')
                    <form method="POST" action="{{ route('tenant.assignments.publish', [app('current_tenant')->slug, $assignment]) }}">
                        @csrf
                        <button class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Publish</button>
                    </form>
                @endif
                <div x-data="{ confirmDelete: false }">
                    <button @click="confirmDelete = true" class="px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-xl transition">Delete</button>
                    {{-- Delete confirmation modal --}}
                    <div x-show="confirmDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="confirmDelete = false">
                        <div @click.outside="confirmDelete = false" class="bg-white rounded-2xl shadow-xl p-6 max-w-sm w-full mx-4 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900">Delete Assignment</h3>
                                    <p class="text-sm text-slate-500">This will permanently delete "{{ $assignment->title }}" and all its submissions.</p>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button @click="confirmDelete = false" class="px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                                <form method="POST" action="{{ route('tenant.assignments.destroy', [app('current_tenant')->slug, $assignment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-xl transition">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Info --}}
        @if($assignment->description || $assignment->instruction_filename)
            <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                @if($assignment->description)
                    <div>
                        <h3 class="font-semibold text-slate-900 mb-2">Description</h3>
                        <p class="text-sm text-slate-600 whitespace-pre-line">{{ $assignment->description }}</p>
                    </div>
                @endif

                @if($assignment->instruction_filename)
                    <div class="flex items-center gap-3 pt-3 {{ $assignment->description ? 'border-t border-slate-100' : '' }}">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-0.5">Instruction File <span class="normal-case text-slate-400">(visible to students)</span></p>
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $assignment->instruction_filename }}</p>
                            @if($assignment->instruction_drive_file_id)
                                <p class="text-[11px] text-emerald-600 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Saved to Google Drive
                                </p>
                            @endif
                        </div>
                        <a href="{{ route('tenant.assignments.instruction', [app('current_tenant')->slug, $assignment]) }}"
                           target="_blank"
                           class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50 rounded-xl transition flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            View
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Rubric --}}
        @if($assignment->rubric && $assignment->rubric->criteria->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Rubric</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Criteria</th>
                            @foreach($assignment->rubric->criteria->first()?->levels ?? [] as $level)
                                <th class="text-center px-4 py-3 font-medium text-slate-500">{{ $level->label }}</th>
                            @endforeach
                            <th class="text-center px-4 py-3 font-medium text-slate-500">Max</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($assignment->rubric->criteria as $criteria)
                                <tr>
                                    <td class="px-6 py-3 font-medium text-slate-900">{{ $criteria->title }}</td>
                                    @foreach($criteria->levels as $level)
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex px-2 py-0.5 bg-slate-100 rounded text-xs font-medium text-slate-600">{{ $level->marks }}</span>
                                            <p class="text-[10px] text-slate-400 mt-0.5">{{ $level->description }}</p>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-center font-bold text-slate-700">{{ $criteria->max_marks }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Submissions --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Submissions ({{ $assignment->submissions->count() }})</h3>
            </div>
            @if($assignment->submissions->isEmpty())
                <div class="p-10 text-center text-sm text-slate-400">No submissions yet. Students can submit after publishing.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Student</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Content</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Status</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Late</th>
                            <th class="text-right px-6 py-3 font-medium text-slate-500">Submitted</th>
                            <th class="text-right px-6 py-3 font-medium text-slate-500"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($assignment->submissions->sortBy('user.name') as $sub)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">{{ strtoupper(substr($sub->user->name ?? '?', 0, 1)) }}</div>
                                            <span class="font-medium text-slate-900">{{ $sub->user->name ?? 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        @if($sub->files->count()) <span class="text-xs">{{ $sub->files->count() }} file(s)</span> @endif
                                        @if($sub->files->count() && $sub->text_content) <span class="text-slate-300 mx-0.5">+</span> @endif
                                        @if($sub->text_content) <span class="text-xs text-indigo-600">Text</span> @endif
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        @php
                                            $sc = ['submitted' => 'slate', 'ai_processing' => 'amber', 'ai_completed' => 'teal', 'marking' => 'indigo', 'graded' => 'emerald'];
                                        @endphp
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $sc[$sub->status] ?? 'slate' }}-100 text-{{ $sc[$sub->status] ?? 'slate' }}-700">{{ str_replace('_', ' ', ucfirst($sub->status)) }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        @if($sub->is_late)<span class="text-red-600 text-xs font-medium">Late</span>@else<span class="text-slate-400 text-xs">On time</span>@endif
                                    </td>
                                    <td class="px-6 py-3 text-right text-xs text-slate-400">{{ $sub->submitted_at->format('d M Y, H:i') }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <a href="{{ route('tenant.assignments.review', [app('current_tenant')->slug, $assignment, $sub]) }}" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Review</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
