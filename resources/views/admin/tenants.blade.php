<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Institutions</h2>
                <p class="mt-1 text-sm text-slate-500">Manage all registered institutions on the platform</p>
            </div>
            <button class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl shadow-sm transition" onclick="alert('Tenant creation form will be implemented in Phase 1')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Institution
            </button>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        @if($tenants->isEmpty())
            <div class="p-16 flex flex-col items-center text-center">
                <div class="w-20 h-20 bg-red-50 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">No institutions yet</h3>
                <p class="text-sm text-slate-500 max-w-sm">Create your first institution to start onboarding lecturers and students.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Institution</th>
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Slug</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Users</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">AI Provider</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Status</th>
                            <th class="text-right px-6 py-3 font-medium text-slate-500">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($tenants as $t)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700">
                                            {{ strtoupper(substr($t->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $t->name }}</p>
                                            <p class="text-xs text-slate-400">{{ $t->timezone }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4"><code class="text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-600">{{ $t->slug }}</code></td>
                                <td class="px-6 py-4 text-center font-medium text-slate-700">{{ $t->tenant_users_count }}</td>
                                <td class="px-6 py-4 text-center"><span class="text-xs bg-teal-50 text-teal-700 px-2 py-0.5 rounded-full font-medium">{{ ucfirst($t->getSetting('ai.provider', 'claude')) }}</span></td>
                                <td class="px-6 py-4 text-center">
                                    @if($t->is_active)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Active</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-slate-400 text-xs">{{ $t->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin-layout>
