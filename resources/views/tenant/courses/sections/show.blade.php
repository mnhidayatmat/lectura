<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $section->name }}</h2>
                <p class="mt-0.5 text-sm text-slate-500">{{ $course->code }} — {{ $course->title }} &middot; Invite code: <code class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded font-bold">{{ $section->invite_code }}</code></p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ $section->activeStudents->count() }}</p>
                <p class="text-sm text-slate-500">Students</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ $section->capacity ?? '∞' }}</p>
                <p class="text-sm text-slate-500">Capacity</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ $section->is_active ? 'Active' : 'Inactive' }}</p>
                <p class="text-sm text-slate-500">Status</p>
            </div>
        </div>

        {{-- Add Students --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Add Students</h3>
            </div>
            <div class="p-6" x-data="{ tab: 'manual' }">
                <div class="flex gap-2 mb-5">
                    <button @click="tab = 'manual'" :class="tab === 'manual' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-4 py-2 rounded-lg text-sm font-medium transition">Manual Add</button>
                    <button @click="tab = 'csv'" :class="tab === 'csv' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-4 py-2 rounded-lg text-sm font-medium transition">CSV Import</button>
                </div>

                {{-- Manual --}}
                <form x-show="tab === 'manual'" method="POST" action="{{ route('tenant.courses.sections.students.add', [app('current_tenant')->slug, $course, $section]) }}" class="grid sm:grid-cols-4 gap-3">
                    @csrf
                    <div>
                        <input type="text" name="name" placeholder="Full name" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <input type="email" name="email" placeholder="Email address" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <input type="text" name="student_id_number" placeholder="Student ID (optional)" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <button type="submit" class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Add Student</button>
                    </div>
                </form>

                {{-- CSV --}}
                <form x-show="tab === 'csv'" x-cloak method="POST" action="{{ route('tenant.courses.sections.students.import', [app('current_tenant')->slug, $course, $section]) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center">
                        <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-sm text-slate-600 mb-2">Upload CSV with columns: <code class="bg-slate-100 px-1 rounded text-xs">name, email, student_id</code></p>
                        <input type="file" name="csv_file" accept=".csv,.txt" required class="text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                    </div>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Import CSV</button>
                </form>
            </div>
        </div>

        {{-- Student Roster --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Student Roster ({{ $section->activeStudents->count() }})</h3>
            </div>
            @if($section->activeStudents->isEmpty())
                <div class="p-10 text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <p class="text-sm text-slate-500">No students enrolled yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Add students manually, import from CSV, or share invite code <code class="bg-indigo-50 text-indigo-700 px-1 rounded font-bold">{{ $section->invite_code }}</code></p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-6 py-3 font-medium text-slate-500">#</th>
                                <th class="text-left px-6 py-3 font-medium text-slate-500">Student</th>
                                <th class="text-left px-6 py-3 font-medium text-slate-500">Email</th>
                                <th class="text-center px-6 py-3 font-medium text-slate-500">Method</th>
                                <th class="text-right px-6 py-3 font-medium text-slate-500">Enrolled</th>
                                <th class="text-right px-6 py-3 font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($section->activeStudents as $i => $student)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-3 text-slate-400">{{ $i + 1 }}</td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">{{ strtoupper(substr($student->name, 0, 1)) }}</div>
                                            <span class="font-medium text-slate-900">{{ $student->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-slate-500">{{ $student->email }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $student->pivot->enrollment_method === 'csv' ? 'bg-teal-50 text-teal-700' : ($student->pivot->enrollment_method === 'invite_code' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600') }}">
                                            {{ ucfirst($student->pivot->enrollment_method) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-right text-slate-400 text-xs">{{ $student->pivot->enrolled_at ? \Carbon\Carbon::parse($student->pivot->enrolled_at)->format('d M Y') : '--' }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <form method="POST" action="{{ route('tenant.courses.sections.students.remove', [app('current_tenant')->slug, $course, $section, $student->id]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500 transition" onclick="return confirm('Remove this student?')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
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
