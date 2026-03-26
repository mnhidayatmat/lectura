<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Notifications</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $unreadCount }} unread</p>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('tenant.notifications.mark-all-read', app('current_tenant')->slug) }}">
                    @csrf
                    <button class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Mark all as read</button>
                </form>
            @endif
        </div>
    </x-slot>

    @if($notifications->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No notifications yet</h3>
            <p class="text-sm text-slate-500">You'll see updates about assignments, marks, and attendance here.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden divide-y divide-slate-100">
            @foreach($notifications as $n)
                @php
                    $data = $n->data;
                    $colors = ['amber' => 'bg-amber-100 text-amber-700', 'emerald' => 'bg-emerald-100 text-emerald-700', 'indigo' => 'bg-indigo-100 text-indigo-700', 'red' => 'bg-red-100 text-red-700', 'teal' => 'bg-teal-100 text-teal-700'];
                    $iconColor = $colors[$data['color'] ?? 'slate'] ?? 'bg-slate-100 text-slate-600';
                    $icons = [
                        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                        'upload' => 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
                        'alert' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z',
                    ];
                    $iconPath = $icons[$data['icon'] ?? 'document'] ?? $icons['document'];
                @endphp
                <div class="flex items-start gap-4 px-6 py-4 {{ $n->read_at ? '' : 'bg-indigo-50/30' }}">
                    <div class="w-10 h-10 rounded-xl {{ $iconColor }} flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ $data['title'] ?? 'Notification' }}</p>
                        <p class="text-sm text-slate-600 mt-0.5">{{ $data['message'] ?? '' }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @if(!$n->read_at)
                        <form method="POST" action="{{ route('tenant.notifications.mark-read', [app('current_tenant')->slug, $n->id]) }}">
                            @csrf
                            <button class="w-2.5 h-2.5 bg-indigo-500 rounded-full flex-shrink-0 mt-2" title="Mark as read"></button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
