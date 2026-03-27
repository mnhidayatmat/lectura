<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">AI Usage</h2>
                <p class="mt-1 text-sm text-slate-500">Monitor AI credit usage across all institutions</p>
            </div>
            <form method="GET" action="{{ route('admin.ai-usage') }}" class="flex items-center gap-2">
                <select name="period" onchange="this.form.submit()" class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <option value="7" {{ $period === '7' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ $period === '30' ? 'selected' : '' }}>Last 30 days</option>
                    <option value="90" {{ $period === '90' ? 'selected' : '' }}>Last 90 days</option>
                    <option value="all" {{ $period === 'all' ? 'selected' : '' }}>All time</option>
                </select>
            </form>
        </div>
    </x-slot>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Total Calls</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalCalls) }}</p>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="text-emerald-600">{{ number_format($successCalls) }} success</span>
                @if($failedCalls > 0)
                    <span class="text-red-500">{{ number_format($failedCalls) }} failed</span>
                @endif
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Input Tokens</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalInputTokens) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Output Tokens</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalOutputTokens) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 p-5">
            <p class="text-sm text-amber-600">Estimated Cost</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">${{ number_format($totalCost, 4) }}</p>
            @if($avgDuration)
                <p class="mt-2 text-xs text-slate-400">Avg {{ number_format($avgDuration, 0) }}ms / call</p>
            @endif
        </div>
    </div>

    {{-- Breakdowns --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- By Module --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900 text-sm">By Module</h3>
            </div>
            @if($byModule->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">No data yet</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($byModule as $module => $stats)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-700 text-sm">{{ str_replace('_', ' ', ucfirst($module)) }}</p>
                                <p class="text-xs text-slate-400">{{ number_format($stats['input_tokens'] + $stats['output_tokens']) }} tokens</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-slate-900 text-sm">{{ $stats['calls'] }}</p>
                                <p class="text-xs text-amber-600">${{ number_format($stats['cost'], 4) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- By Provider --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900 text-sm">By Provider</h3>
            </div>
            @if($byProvider->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">No data yet</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($byProvider as $provider => $stats)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-700 text-sm">{{ ucfirst($provider) }}</p>
                                <p class="text-xs text-slate-400">{{ number_format($stats['input_tokens'] + $stats['output_tokens']) }} tokens</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-slate-900 text-sm">{{ $stats['calls'] }}</p>
                                <p class="text-xs text-amber-600">${{ number_format($stats['cost'], 4) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- By Institution --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900 text-sm">By Institution</h3>
            </div>
            @if($byTenant->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">No data yet</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($byTenant as $tenantId => $stats)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-700 text-sm">{{ $stats['name'] }}</p>
                                <p class="text-xs text-slate-400">{{ number_format($stats['tokens']) }} tokens</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-slate-900 text-sm">{{ $stats['calls'] }}</p>
                                <p class="text-xs text-amber-600">${{ number_format($stats['cost'], 4) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Recent Logs --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-900 text-sm">Recent Activity</h3>
        </div>
        @if($recentLogs->isEmpty())
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <p class="text-sm text-slate-500">No AI usage recorded yet</p>
                <p class="text-xs text-slate-400 mt-1">Usage will appear here when users interact with AI features</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-5 py-3 font-medium text-slate-500">User</th>
                            <th class="text-left px-5 py-3 font-medium text-slate-500">Institution</th>
                            <th class="text-left px-5 py-3 font-medium text-slate-500">Module</th>
                            <th class="text-left px-5 py-3 font-medium text-slate-500">Provider</th>
                            <th class="text-right px-5 py-3 font-medium text-slate-500">Tokens</th>
                            <th class="text-right px-5 py-3 font-medium text-slate-500">Cost</th>
                            <th class="text-center px-5 py-3 font-medium text-slate-500">Status</th>
                            <th class="text-right px-5 py-3 font-medium text-slate-500">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recentLogs as $log)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-5 py-3 text-slate-700">{{ $log->user->name ?? 'System' }}</td>
                                <td class="px-5 py-3 text-slate-500 text-xs">{{ $recentTenants[$log->tenant_id] ?? '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium">{{ str_replace('_', ' ', $log->module) }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs text-slate-500">{{ ucfirst($log->provider) }}</span>
                                    <span class="text-[10px] text-slate-400 block">{{ $log->model }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-slate-600">
                                    {{ number_format($log->input_tokens + $log->output_tokens) }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-amber-600">
                                    ${{ number_format($log->cost_usd ?? 0, 4) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if($log->response_status === 'success')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[11px] font-medium">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> OK
                                        </span>
                                    @elseif($log->response_status === 'failed')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[11px] font-medium">
                                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Fail
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[11px] font-medium">{{ $log->response_status }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-slate-400">
                                    <span title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</span>
                                    @if($log->duration_ms)
                                        <span class="block text-[10px]">{{ number_format($log->duration_ms) }}ms</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin-layout>
