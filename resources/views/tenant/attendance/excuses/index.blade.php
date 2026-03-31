<x-tenant-layout>
    @php $tenant = app('current_tenant'); @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.attendance.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Attendance Excuses</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Review student absence excuses</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Filter Tabs --}}
        <div class="flex gap-2">
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <a href="{{ route('tenant.attendance.excuses', [$tenant->slug, 'status' => $key]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                    {{ $label }}
                    @if($key === 'pending' && $pendingCount > 0)
                        <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-[10px] font-bold {{ $status === 'pending' ? 'text-white' : 'bg-red-100 text-red-700' }}">{{ $pendingCount }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Excuse List --}}
        @forelse($excuses as $excuse)
            @php
                $session = $excuse->record->session;
                $course = $session->section->course;
            @endphp
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-sm font-bold text-indigo-700">{{ strtoupper(substr($excuse->user->name, 0, 1)) }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $excuse->user->name }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $course->code }} {{ $session->section->name }}
                                    &middot; Week {{ $session->week_number ?? '?' }} {{ ucfirst($session->session_type) }}
                                    &middot; {{ $session->started_at?->format('d M Y') }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase flex-shrink-0
                            {{ $excuse->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($excuse->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                            {{ $excuse->status }}
                        </span>
                    </div>

                    <div class="mt-3 ml-13 pl-13">
                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-[10px] font-medium text-slate-600 mb-2">
                            {{ ucfirst(str_replace('_', ' ', $excuse->category)) }}
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $excuse->reason }}</p>

                        @if($excuse->attachment_filename)
                            <a href="{{ route('tenant.attendance.excuses.attachment', [$tenant->slug, $excuse]) }}"
                               class="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ $excuse->attachment_filename }}
                            </a>
                        @endif

                        <p class="text-[11px] text-slate-400 mt-2">Submitted {{ $excuse->created_at->diffForHumans() }}</p>
                    </div>

                    {{-- Review Actions --}}
                    @if($excuse->status === 'pending')
                        <div class="mt-4 ml-13 pl-13 pt-4 border-t border-slate-100" x-data="{ note: '' }">
                            <div class="mb-3">
                                <input type="text" x-model="note" placeholder="Optional note to student..."
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('tenant.attendance.excuses.approve', [$tenant->slug, $excuse]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="note" :value="note" />
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('tenant.attendance.excuses.reject', [$tenant->slug, $excuse]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="note" :value="note" />
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif($excuse->reviewer_note)
                        <div class="mt-3 ml-13 pl-13">
                            <p class="text-xs text-slate-500 italic">
                                Reviewer note: {{ $excuse->reviewer_note }}
                                <span class="text-slate-400">— {{ $excuse->reviewer?->name }}, {{ $excuse->reviewed_at?->format('d M Y') }}</span>
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
                <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm text-slate-500">No {{ $status === 'all' ? '' : $status }} excuses found.</p>
            </div>
        @endforelse

        {{ $excuses->withQueryString()->links() }}
    </div>
</x-tenant-layout>
