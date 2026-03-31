<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900">My Assignments</h2>
    </x-slot>

    @if($assignmentsByCourse->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
            <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-sm text-slate-500">No assignments available yet.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($assignmentsByCourse as $group)
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center shadow-sm">
                            <span class="text-xs font-bold text-white">{{ strtoupper(substr($group['course']->code, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ $group['course']->code }} — {{ $group['course']->title }}</h3>
                            <p class="text-xs text-slate-400">{{ $group['assignments']->count() }} {{ Str::plural('assignment', $group['assignments']->count()) }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($group['assignments'] as $a)
                            <a href="{{ route('tenant.assignments.show', [app('current_tenant')->slug, $a]) }}" class="block bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md hover:border-indigo-200 transition">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $a->title }}</p>
                                        <p class="text-sm text-slate-500 mt-0.5">{{ $a->total_marks }} marks</p>
                                    </div>
                                    @if($a->deadline)
                                        <div class="text-right flex-shrink-0">
                                            <p class="text-xs font-medium {{ $a->deadline->isPast() ? 'text-red-600' : 'text-slate-500' }}">
                                                {{ $a->deadline->isPast() ? 'Overdue' : 'Due ' . $a->deadline->diffForHumans() }}
                                            </p>
                                            <p class="text-xs text-slate-400">{{ $a->deadline->format('d M Y, H:i') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
