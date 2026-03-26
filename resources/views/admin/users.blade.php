<x-admin-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">All Users</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $users->count() }} users across all institutions</p>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="text-left px-6 py-3 font-medium text-slate-500">User</th>
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Email</th>
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Institutions & Roles</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Type</th>
                        <th class="text-right px-6 py-3 font-medium text-slate-500">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full {{ $u->is_super_admin ? 'bg-red-100 text-red-700' : 'bg-indigo-100 text-indigo-700' }} flex items-center justify-center text-xs font-bold">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-slate-900">{{ $u->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $u->email }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($u->tenantUsers as $tu)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium
                                            {{ $tu->role === 'admin' ? 'bg-red-50 text-red-700' :
                                               ($tu->role === 'lecturer' ? 'bg-indigo-50 text-indigo-700' :
                                               ($tu->role === 'coordinator' ? 'bg-amber-50 text-amber-700' : 'bg-teal-50 text-teal-700')) }}">
                                            {{ ucfirst($tu->role) }} @ {{ $tu->tenant->name ?? '?' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($u->is_super_admin)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">Super Admin</span>
                                @elseif($u->google_id)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-xs font-medium">Google</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">Email</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-slate-400 text-xs">{{ $u->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
