<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Activity Log</h2>
                <p class="mt-1 text-sm text-slate-500">System-wide audit trail of all changes</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Filters --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Module</label>
                    <select name="log" class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 bg-white">
                        <option value="">All Modules</option>
                        @foreach($logNames as $name)
                            <option value="{{ $name }}" {{ request('log') === $name ? 'selected' : '' }}>{{ ucfirst($name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Event</label>
                    <select name="event" class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 bg-white">
                        <option value="">All Events</option>
                        @foreach($events as $event)
                            <option value="{{ $event }}" {{ request('event') === $event ? 'selected' : '' }}>{{ ucfirst($event) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl transition">Filter</button>
                    @if(request()->hasAny(['log', 'event']))
                        <a href="{{ route('admin.activity') }}" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Activity List --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Recent Activity</h3>
                <span class="text-xs text-slate-400">{{ $activities->total() }} entries</span>
            </div>

            @if($activities->isEmpty())
                <div class="p-16 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No activity recorded yet</h3>
                    <p class="text-sm text-slate-500 max-w-sm">Activity will appear here as users create, update, and delete records across the platform.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($activities as $activity)
                        <div class="px-6 py-4 hover:bg-slate-50/50 transition" x-data="{ expanded: false }">
                            <div class="flex items-start gap-4 cursor-pointer" @click="expanded = !expanded">
                                {{-- Event Icon --}}
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5
                                    {{ match($activity->event) {
                                        'created' => 'bg-emerald-100',
                                        'updated' => 'bg-blue-100',
                                        'deleted' => 'bg-red-100',
                                        default => 'bg-slate-100',
                                    } }}">
                                    @if($activity->event === 'created')
                                        <svg class="w-4.5 h-4.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    @elseif($activity->event === 'updated')
                                        <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    @elseif($activity->event === 'deleted')
                                        <svg class="w-4.5 h-4.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    @else
                                        <svg class="w-4.5 h-4.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-slate-900">{{ $activity->description }}</p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-400">
                                        @if($activity->causer)
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                {{ $activity->causer->name }}
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-medium">{{ $activity->log_name }}</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                            {{ match($activity->event) {
                                                'created' => 'bg-emerald-50 text-emerald-600',
                                                'updated' => 'bg-blue-50 text-blue-600',
                                                'deleted' => 'bg-red-50 text-red-600',
                                                default => 'bg-slate-50 text-slate-500',
                                            } }}">{{ $activity->event }}</span>
                                        <span>{{ $activity->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>

                                {{-- Expand arrow --}}
                                @php $changes = $activity->attribute_changes; @endphp
                                @if($changes && (isset($changes['attributes']) || isset($changes['old'])))
                                    <svg class="w-4 h-4 text-slate-300 transition-transform flex-shrink-0 mt-1" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                            </div>

                            {{-- Expanded: Changed attributes --}}
                            @if($changes && isset($changes['attributes']))
                                <div x-show="expanded" x-cloak x-transition class="mt-3 ml-13 pl-4 border-l-2 border-slate-100">
                                    @if(isset($changes['old']))
                                        <div class="space-y-1.5">
                                            @foreach($changes['attributes'] as $key => $newValue)
                                                @php $oldValue = $changes['old'][$key] ?? '(empty)'; @endphp
                                                <div class="text-xs">
                                                    <span class="font-medium text-slate-500">{{ Str::title(str_replace('_', ' ', $key)) }}:</span>
                                                    <span class="text-red-500 line-through">{{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}</span>
                                                    <svg class="w-3 h-3 text-slate-300 inline mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                    <span class="text-emerald-600 font-medium">{{ is_array($newValue) ? json_encode($newValue) : $newValue }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            @foreach($changes['attributes'] as $key => $value)
                                                <div class="text-xs">
                                                    <span class="font-medium text-slate-500">{{ Str::title(str_replace('_', ' ', $key)) }}:</span>
                                                    <span class="text-slate-700">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($activities->hasPages())
                    <div class="px-6 py-4 border-t border-slate-100">
                        {{ $activities->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
