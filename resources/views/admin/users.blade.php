<x-admin-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">All Users</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $users->count() }} users across all institutions</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- Subscription Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Total Users</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $users->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 p-5">
            <p class="text-sm text-amber-600">Pro Users</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $users->where('is_pro', true)->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Free Users</p>
            <p class="text-2xl font-bold text-slate-700 mt-1">{{ $users->where('is_pro', false)->count() }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="text-left px-6 py-3 font-medium text-slate-500">User</th>
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Email</th>
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Institutions & Roles</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Type</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Plan</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Actions</th>
                        <th class="text-right px-6 py-3 font-medium text-slate-500">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full {{ $u->is_super_admin ? 'bg-red-100 text-red-700' : ($u->is_pro ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700') }} flex items-center justify-center text-xs font-bold">
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
                                @elseif($u->tenantUsers->isNotEmpty())
                                    @php $primaryRole = $u->tenantUsers->first()->role; @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $primaryRole === 'admin' ? 'bg-red-50 text-red-700' :
                                           ($primaryRole === 'lecturer' ? 'bg-indigo-50 text-indigo-700' :
                                           ($primaryRole === 'coordinator' ? 'bg-amber-50 text-amber-700' :
                                           ($primaryRole === 'student' ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-500'))) }}">
                                        {{ ucfirst($primaryRole) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">No Role</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($u->is_pro)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        Pro
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">Free</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <form method="POST" action="{{ route('admin.users.toggle-pro', $u) }}" x-data x-on:submit.prevent="if(confirm('{{ $u->is_pro ? 'Downgrade' : 'Upgrade' }} {{ $u->name }} to {{ $u->is_pro ? 'Free' : 'Pro' }}?')) $el.submit()">
                                    @csrf
                                    @if($u->is_pro)
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            Downgrade
                                        </button>
                                    @else
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            Upgrade Pro
                                        </button>
                                    @endif
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right text-slate-400 text-xs">{{ $u->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
