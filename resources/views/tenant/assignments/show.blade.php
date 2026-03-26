<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assignments.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $assignment->title }}</h2>
                    <p class="text-sm text-slate-500">{{ $assignment->course->code }} &middot; {{ $assignment->total_marks }} marks &middot; {{ $assignment->deadline?->format('d M Y, H:i') ?? 'No deadline' }}</p>
                </div>
            </div>
            @if($assignment->status === 'draft')
                <form method="POST" action="{{ route('tenant.assignments.publish', [app('current_tenant')->slug, $assignment]) }}">
                    @csrf
                    <button class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Publish</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Info --}}
        @if($assignment->description)
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="font-semibold text-slate-900 mb-2">Description</h3>
                <p class="text-sm text-slate-600 whitespace-pre-line">{{ $assignment->description }}</p>
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
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Files</th>
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
                                    <td class="px-6 py-3 text-center">{{ $sub->files->count() }}</td>
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
