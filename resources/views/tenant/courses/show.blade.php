<x-tenant-layout>
    @php $tenant = app('current_tenant'); @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                <a href="{{ route('tenant.courses.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-2xl font-bold text-slate-900 break-all">{{ $course->code }}</h2>
                        @php $badge = $course->statusBadge; @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700 flex-shrink-0">{{ $badge['label'] }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500 break-words">{{ $course->title }}
                        @if($course->invite_code)
                            &middot; <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono text-slate-600 break-all">{{ $course->invite_code }}</code>
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('tenant.courses.edit', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 shadow-sm transition flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit Course
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Quick Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 overflow-hidden">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Credit Hours</p>
                <p class="text-xl font-bold text-slate-900 mt-1 truncate">{{ $course->credit_hours ?? '--' }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 overflow-hidden">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Weeks</p>
                <p class="text-xl font-bold text-slate-900 mt-1 truncate">{{ $course->num_weeks }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 overflow-hidden">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Students</p>
                <p class="text-xl font-bold text-indigo-700 mt-1 truncate">{{ $course->totalStudents() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 overflow-hidden">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Sections</p>
                <p class="text-xl font-bold text-slate-900 mt-1 truncate">{{ $course->sections->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 overflow-hidden">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Topics</p>
                <p class="text-xl font-bold text-slate-900 mt-1 truncate">{{ $course->topics->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 overflow-hidden">
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">CLOs</p>
                <p class="text-xl font-bold text-slate-900 mt-1 truncate">{{ $course->learningOutcomes->count() }}</p>
            </div>
            @if($course->invite_code)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 col-span-2 sm:col-span-1 overflow-hidden">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Invite Code</p>
                    <p class="text-lg font-bold text-indigo-700 mt-1 font-mono tracking-wider break-all">{{ $course->invite_code }}</p>
                </div>
            @endif
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            <a href="{{ route('tenant.materials.manage', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-200 transition">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Materials</p>
                    <p class="text-[11px] text-slate-400 truncate">Upload & manage</p>
                </div>
            </a>
            <a href="{{ route('tenant.active-learning.index', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-teal-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center flex-shrink-0 group-hover:bg-teal-200 transition">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Active Learning</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ $course->activeLearningPlans->count() }} plans</p>
                </div>
            </a>
            <a href="{{ route('tenant.attendance.index', $tenant->slug) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-emerald-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-200 transition">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Attendance</p>
                    <p class="text-[11px] text-slate-400 truncate">QR check-in</p>
                </div>
            </a>
            <a href="{{ route('tenant.quizzes.index', $tenant->slug) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-amber-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 group-hover:bg-amber-200 transition">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Quizzes</p>
                    <p class="text-[11px] text-slate-400 truncate">Live quizzes</p>
                </div>
            </a>
            <a href="{{ route('tenant.student-groups.index', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-violet-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0 group-hover:bg-violet-200 transition">
                    <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Groups</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ $course->studentGroupSets->count() }} {{ Str::plural('set', $course->studentGroupSets->count()) }}</p>
                </div>
            </a>
            <a href="{{ route('tenant.attendance.report', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-rose-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center flex-shrink-0 group-hover:bg-rose-200 transition">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Att. Report</p>
                    <p class="text-[11px] text-slate-400 truncate">PDF & Excel</p>
                </div>
            </a>
            <a href="{{ route('tenant.courses.attendance-policy.edit', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-cyan-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-cyan-100 flex items-center justify-center flex-shrink-0 group-hover:bg-cyan-200 transition">
                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Att. Policy</p>
                    <p class="text-[11px] text-slate-400 truncate">Warning rules</p>
                </div>
            </a>
            <a href="{{ route('tenant.whiteboards.index', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-fuchsia-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-fuchsia-100 flex items-center justify-center flex-shrink-0 group-hover:bg-fuchsia-200 transition">
                    <svg class="w-5 h-5 text-fuchsia-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Whiteboards</p>
                    <p class="text-[11px] text-slate-400 truncate">Collaborative</p>
                </div>
            </a>
        </div>

        {{-- Course Info Card --}}
        @if($course->description || $course->teaching_mode || $course->academicTerm)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 overflow-hidden">
                <div class="flex items-center gap-x-6 gap-y-2 flex-wrap text-sm text-slate-600">
                    @if($course->teaching_mode)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span class="break-words">{{ str_replace('_', ' ', ucfirst($course->teaching_mode)) }}</span>
                        </span>
                    @endif
                    @if($course->academicTerm)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="break-words">{{ $course->academicTerm->name }}</span>
                        </span>
                    @endif
                    @if($course->format)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span class="break-words">{{ collect($course->format)->filter()->keys()->map(fn($f) => ucfirst($f))->implode(', ') }}</span>
                        </span>
                    @endif
                </div>
                @if($course->description)
                    <p class="mt-3 text-sm text-slate-600 leading-relaxed break-words whitespace-pre-line">{{ $course->description }}</p>
                @endif
            </div>
        @endif

        {{-- Two Column: CLOs + Sections --}}
        <div class="grid lg:grid-cols-2 gap-6">
            {{-- CLOs --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-slate-900 text-sm">Learning Outcomes</h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold">{{ $course->learningOutcomes->count() }}</span>
                    </div>
                    <form method="POST" action="{{ route('tenant.courses.clos.store', [$tenant->slug, $course]) }}" class="flex items-center gap-2" x-data="{ show: false }">
                        @csrf
                        <template x-if="show">
                            <div class="flex items-center gap-2">
                                <input type="text" name="code" placeholder="CLO#" required class="w-16 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                <input type="text" name="description" placeholder="Description..." required class="w-40 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                <button type="submit" class="px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Add</button>
                                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                        </template>
                        <button type="button" x-show="!show" @click="show = true" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add
                        </button>
                    </form>
                </div>
                <div class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                    @forelse($course->learningOutcomes as $clo)
                        <div class="px-5 py-3 flex items-center justify-between group hover:bg-slate-50/50 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-bold rounded-md flex-shrink-0">{{ $clo->code }}</span>
                                <span class="text-sm text-slate-700 truncate">{{ $clo->description }}</span>
                            </div>
                            <form method="POST" action="{{ route('tenant.courses.clos.destroy', [$tenant->slug, $course, $clo]) }}" class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-slate-400 hover:text-red-500" onclick="return confirm('Remove this CLO?')">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">No CLOs defined yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Sections --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-slate-900 text-sm">Sections</h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">{{ $course->sections->count() }}</span>
                    </div>
                    @if($isOwner)
                    <div x-data="{ open: false }">
                        <button @click="open = !open" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add
                        </button>
                        <div x-show="open" x-cloak x-transition class="absolute right-6 mt-2 z-20">
                            <form method="POST" action="{{ route('tenant.courses.sections.store', [$tenant->slug, $course]) }}" class="bg-white rounded-xl border border-slate-200 shadow-lg p-4 space-y-3 w-80">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" name="name" placeholder="Section 01" required class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <input type="text" name="code" placeholder="SEC01" required class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" name="capacity" placeholder="Capacity" min="1" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <select name="academic_term_id" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500 bg-white">
                                        <option value="">Semester</option>
                                        @foreach($terms as $term)
                                            <option value="{{ $term->id }}" {{ $term->is_default ? 'selected' : '' }}>{{ $term->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <label class="block text-[10px] font-medium text-slate-500 mb-1">Assign lecturers (optional)</label>
                                <div class="space-y-1 max-h-32 overflow-y-auto border border-slate-300 rounded-lg p-2 bg-white">
                                    @foreach($lecturers as $lecturer)
                                        <label class="flex items-center gap-2 px-1 py-0.5 rounded hover:bg-slate-50 cursor-pointer">
                                            <input type="checkbox" name="lecturer_ids[]" value="{{ $lecturer->id }}" {{ $lecturer->id === $course->lecturer_id ? 'checked' : '' }}
                                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5" />
                                            <span class="text-xs text-slate-700">{{ $lecturer->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <button type="submit" class="w-full px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Create Section</button>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                    @forelse($course->sections as $section)
                        <a href="{{ route('tenant.courses.sections.show', [$tenant->slug, $course, $section]) }}" class="flex items-start justify-between gap-3 px-5 py-3 hover:bg-slate-50/50 transition group {{ !$section->is_active ? 'opacity-50' : '' }}">
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-xl {{ $section->is_active ? 'bg-emerald-100' : 'bg-slate-100' }} flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold {{ $section->is_active ? 'text-emerald-700' : 'text-slate-400' }}">{{ substr($section->name, 0, 2) }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-sm font-medium text-slate-900 break-words">{{ $section->name }}</p>
                                        @unless($section->is_active)
                                            <span class="bg-red-50 text-red-600 px-1 py-0.5 rounded text-[10px] font-medium flex-shrink-0">Inactive</span>
                                        @endunless
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] text-slate-400">
                                        <span class="break-all">{{ $section->code }}</span>
                                        <code class="bg-slate-100 px-1 rounded text-[10px] break-all">{{ $section->invite_code }}</code>
                                        @if($section->academicTerm)
                                            <span class="bg-amber-50 text-amber-700 px-1 py-0.5 rounded text-[10px] font-medium break-words">{{ $section->academicTerm->name }}</span>
                                        @endif
                                        @if($section->lecturers->isNotEmpty())
                                            @foreach($section->lecturers as $sectionLecturer)
                                                <span class="bg-indigo-50 text-indigo-600 px-1 py-0.5 rounded text-[10px] font-medium break-words">{{ $sectionLecturer->name }}</span>
                                            @endforeach
                                        @elseif($isOwner)
                                            <span class="bg-slate-50 text-slate-400 px-1 py-0.5 rounded text-[10px] font-medium italic">Unassigned</span>
                                        @endif
                                    </div>
                                    @if($section->schedule)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($section->schedule as $slot)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium break-words
                                                    {{ $slot['type'] === 'lecture' ? 'bg-blue-50 text-blue-600' : ($slot['type'] === 'tutorial' ? 'bg-amber-50 text-amber-600' : ($slot['type'] === 'lab' ? 'bg-purple-50 text-purple-600' : 'bg-slate-100 text-slate-500')) }}">
                                                    {{ ucfirst(substr($slot['day'], 0, 3)) }}
                                                    {{ $slot['start_time'] }}-{{ $slot['end_time'] }}
                                                    @if(!empty($slot['location']))
                                                        &middot; {{ $slot['location'] }}
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0 pt-1">
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-900 leading-tight">{{ $section->activeStudents->count() }}</p>
                                    <p class="text-[10px] text-slate-400 leading-tight">students</p>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">No sections yet. Add one to start enrolling students.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Weekly Topics --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-slate-900 text-sm">Weekly Topics</h3>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-teal-100 text-teal-700 text-[10px] font-bold">{{ $course->topics->count() }}</span>
                </div>
                <form method="POST" action="{{ route('tenant.courses.topics.store', [$tenant->slug, $course]) }}" class="flex items-center gap-2" x-data="{ show: false }">
                    @csrf
                    <template x-if="show">
                        <div class="flex items-center gap-2">
                            <input type="number" name="week_number" placeholder="Wk" min="1" required class="w-14 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" name="title" placeholder="Topic title..." required class="w-48 sm:w-64 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <button type="submit" class="px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Add</button>
                            <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </template>
                    <button type="button" x-show="!show" @click="show = true" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add
                    </button>
                </form>
            </div>
            @if($course->topics->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-400">No weekly topics defined yet.</div>
            @else
                <div class="grid sm:grid-cols-2 gap-px bg-slate-100">
                    @foreach($course->topics as $topic)
                        <div class="px-5 py-3 flex items-center justify-between bg-white group hover:bg-slate-50/50 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center justify-center w-9 h-7 bg-teal-50 text-teal-700 text-[10px] font-bold rounded-md flex-shrink-0">W{{ $topic->week_number }}</span>
                                <span class="text-sm text-slate-700 truncate">{{ $topic->title }}</span>
                            </div>
                            <form method="POST" action="{{ route('tenant.courses.topics.destroy', [$tenant->slug, $course, $topic]) }}" class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-slate-400 hover:text-red-500" onclick="return confirm('Remove this topic?')">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
