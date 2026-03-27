<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Institutions</h2>
                <p class="mt-1 text-sm text-slate-500">Manage all registered institutions on the platform</p>
            </div>
            <button onclick="document.getElementById('create-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Institution
            </button>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Institutions</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $tenants->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Total Users</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $tenants->sum('tenant_users_count') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-indigo-200 p-5">
            <p class="text-sm text-indigo-600">Lecturers</p>
            <p class="text-2xl font-bold text-indigo-700 mt-1">{{ $tenants->sum('lecturers_count') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-teal-200 p-5">
            <p class="text-sm text-teal-600">Students</p>
            <p class="text-2xl font-bold text-teal-700 mt-1">{{ $tenants->sum('students_count') }}</p>
        </div>
    </div>

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
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Lecturers</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Students</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Total Users</th>
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
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium">{{ $t->lecturers_count }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-teal-50 text-teal-700 rounded-full text-xs font-medium">{{ $t->students_count }}</span>
                                </td>
                                <td class="px-6 py-4 text-center font-medium text-slate-700">{{ $t->tenant_users_count }}</td>
                                <td class="px-6 py-4 text-right text-slate-400 text-xs">{{ $t->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Create Institution Modal --}}
    <div id="create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50" onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Add Institution</h3>
                <button onclick="document.getElementById('create-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.tenants.store') }}" class="p-6 space-y-4" x-data="{
                name: '',
                get slug() { return this.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
            }">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Institution Name</label>
                    <input type="text" name="name" id="name" x-model="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                        placeholder="e.g. University of Technology">
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700 mb-1">URL Slug</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-400">/</span>
                        <input type="text" name="slug" id="slug" x-bind:value="slug" value="{{ old('slug') }}" required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                            placeholder="university-of-technology"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Lowercase letters, numbers, and hyphens only</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-slate-700 mb-1">Timezone</label>
                        <select name="timezone" id="timezone" required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                            <option value="Asia/Kuala_Lumpur" {{ old('timezone', 'Asia/Kuala_Lumpur') === 'Asia/Kuala_Lumpur' ? 'selected' : '' }}>Asia/Kuala_Lumpur (GMT+8)</option>
                            <option value="Asia/Singapore" {{ old('timezone') === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (GMT+8)</option>
                            <option value="Asia/Jakarta" {{ old('timezone') === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (GMT+7)</option>
                            <option value="Asia/Bangkok" {{ old('timezone') === 'Asia/Bangkok' ? 'selected' : '' }}>Asia/Bangkok (GMT+7)</option>
                            <option value="Asia/Tokyo" {{ old('timezone') === 'Asia/Tokyo' ? 'selected' : '' }}>Asia/Tokyo (GMT+9)</option>
                            <option value="Europe/London" {{ old('timezone') === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT+0)</option>
                            <option value="America/New_York" {{ old('timezone') === 'America/New_York' ? 'selected' : '' }}>America/New_York (GMT-5)</option>
                            <option value="UTC" {{ old('timezone') === 'UTC' ? 'selected' : '' }}>UTC</option>
                        </select>
                    </div>
                    <div>
                        <label for="locale" class="block text-sm font-medium text-slate-700 mb-1">Language</label>
                        <select name="locale" id="locale" required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                            <option value="en" {{ old('locale', 'en') === 'en' ? 'selected' : '' }}>English</option>
                            <option value="ms" {{ old('locale') === 'ms' ? 'selected' : '' }}>Bahasa Melayu</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" onclick="document.getElementById('create-modal').classList.add('hidden')"
                        class="px-4 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-sm transition">
                        Create Institution
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
