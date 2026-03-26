<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.courses.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }}</h2>
                        @php $badge = $course->statusBadge; @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $course->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('tenant.teaching-plan.show', [app('current_tenant')->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-xl hover:bg-teal-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Teaching Plan
                </a>
                <a href="{{ route('tenant.courses.edit', [app('current_tenant')->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 border border-slate-300 rounded-xl hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        {{-- Course Details --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Course Details</h3>
            </div>
            <div class="p-6">
                <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <dt class="text-xs font-medium text-slate-400 uppercase">Credit Hours</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $course->credit_hours ?? '--' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400 uppercase">Weeks</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $course->num_weeks }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400 uppercase">Teaching Mode</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ str_replace('_', ' ', ucfirst($course->teaching_mode)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400 uppercase">Term</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $course->academicTerm?->name ?? 'Not set' }}</dd>
                    </div>
                </dl>
                @if($course->description)
                    <p class="mt-4 text-sm text-slate-600">{{ $course->description }}</p>
                @endif
            </div>
        </div>

        {{-- CLOs --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Learning Outcomes ({{ $course->learningOutcomes->count() }})</h3>
                <form method="POST" action="{{ route('tenant.courses.clos.store', [app('current_tenant')->slug, $course]) }}" class="flex items-center gap-2" x-data="{ show: false }">
                    @csrf
                    <template x-if="show">
                        <div class="flex items-center gap-2">
                            <input type="text" name="code" placeholder="CLO#" required class="w-20 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" name="description" placeholder="Description..." required class="w-64 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Add</button>
                            <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </template>
                    <button type="button" x-show="!show" @click="show = true" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add
                    </button>
                </form>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($course->learningOutcomes as $clo)
                    <div class="px-6 py-3 flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-12 h-7 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-md">{{ $clo->code }}</span>
                            <span class="text-sm text-slate-700">{{ $clo->description }}</span>
                        </div>
                        <form method="POST" action="{{ route('tenant.courses.clos.destroy', [app('current_tenant')->slug, $course, $clo]) }}" class="opacity-0 group-hover:opacity-100 transition">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500" onclick="return confirm('Remove this CLO?')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-slate-400">No CLOs defined yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Weekly Topics --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Weekly Topics ({{ $course->topics->count() }})</h3>
                <form method="POST" action="{{ route('tenant.courses.topics.store', [app('current_tenant')->slug, $course]) }}" class="flex items-center gap-2" x-data="{ show: false }">
                    @csrf
                    <template x-if="show">
                        <div class="flex items-center gap-2">
                            <input type="number" name="week_number" placeholder="Wk" min="1" required class="w-16 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" name="title" placeholder="Topic title..." required class="w-64 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Add</button>
                            <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </template>
                    <button type="button" x-show="!show" @click="show = true" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add
                    </button>
                </form>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($course->topics as $topic)
                    <div class="px-6 py-3 flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-12 h-7 bg-teal-50 text-teal-700 text-xs font-bold rounded-md">W{{ $topic->week_number }}</span>
                            <span class="text-sm text-slate-700">{{ $topic->title }}</span>
                        </div>
                        <form method="POST" action="{{ route('tenant.courses.topics.destroy', [app('current_tenant')->slug, $course, $topic]) }}" class="opacity-0 group-hover:opacity-100 transition">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500" onclick="return confirm('Remove this topic?')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-slate-400">No weekly topics defined yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Sections --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Sections ({{ $course->sections->count() }})</h3>
                <div x-data="{ open: false }">
                    <button @click="open = !open" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Section
                    </button>
                    <div x-show="open" x-cloak class="mt-3">
                        <form method="POST" action="{{ route('tenant.courses.sections.store', [app('current_tenant')->slug, $course]) }}" class="flex items-center gap-2 bg-slate-50 rounded-xl p-3">
                            @csrf
                            <input type="text" name="name" placeholder="Section 01" required class="w-32 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" name="code" placeholder="SEC01" required class="w-24 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="number" name="capacity" placeholder="Cap" min="1" class="w-16 px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Create</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($course->sections as $section)
                    <a href="{{ route('tenant.courses.sections.show', [app('current_tenant')->slug, $course, $section]) }}" class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                                <span class="text-sm font-bold text-indigo-700">{{ substr($section->name, 0, 2) }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $section->name }}</p>
                                <p class="text-xs text-slate-400">Code: {{ $section->code }} &middot; Invite: <code class="bg-slate-100 px-1 rounded">{{ $section->invite_code }}</code></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-sm font-semibold text-slate-900">{{ $section->activeStudents->count() }}</p>
                                <p class="text-xs text-slate-400">students</p>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-slate-400">No sections created yet. Add a section to start enrolling students.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-tenant-layout>
