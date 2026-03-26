<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.courses.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Create Course</h2>
                <p class="mt-1 text-sm text-slate-500">Set up a new course with CLOs and weekly topics</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.courses.store', app('current_tenant')->slug) }}" x-data="courseForm()" class="space-y-8">
        @csrf

        {{-- Basic Info --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Course Information</h3>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-700 mb-1.5">Course Code *</label>
                        <input type="text" name="code" id="code" value="{{ old('code') }}" required placeholder="e.g. CS201" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-700 mb-1.5">Course Title *</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" required placeholder="e.g. Data Structures & Algorithms" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                    <textarea name="description" id="description" rows="3" placeholder="Brief course description..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description') }}</textarea>
                </div>

                <div class="grid sm:grid-cols-3 gap-5">
                    <div>
                        <label for="credit_hours" class="block text-sm font-medium text-slate-700 mb-1.5">Credit Hours</label>
                        <input type="number" name="credit_hours" id="credit_hours" value="{{ old('credit_hours', 3) }}" min="1" max="20" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label for="num_weeks" class="block text-sm font-medium text-slate-700 mb-1.5">Number of Weeks *</label>
                        <input type="number" name="num_weeks" id="num_weeks" value="{{ old('num_weeks', 14) }}" required min="1" max="52" x-model="numWeeks" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label for="teaching_mode" class="block text-sm font-medium text-slate-700 mb-1.5">Teaching Mode *</label>
                        <select name="teaching_mode" id="teaching_mode" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="face_to_face" {{ old('teaching_mode') === 'face_to_face' ? 'selected' : '' }}>Face to Face</option>
                            <option value="online" {{ old('teaching_mode') === 'online' ? 'selected' : '' }}>Online</option>
                            <option value="hybrid" {{ old('teaching_mode') === 'hybrid' ? 'selected' : '' }}>Hybrid</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Format</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2"><input type="checkbox" name="format[lecture]" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><span class="text-sm text-slate-600">Lecture</span></label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="format[tutorial]" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><span class="text-sm text-slate-600">Tutorial</span></label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="format[lab]" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><span class="text-sm text-slate-600">Lab</span></label>
                    </div>
                </div>

                @if($faculties->isNotEmpty())
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="faculty_id" class="block text-sm font-medium text-slate-700 mb-1.5">Faculty</label>
                            <select name="faculty_id" id="faculty_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Optional --</option>
                                @foreach($faculties as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($terms->isNotEmpty())
                            <div>
                                <label for="academic_term_id" class="block text-sm font-medium text-slate-700 mb-1.5">Academic Term</label>
                                <select name="academic_term_id" id="academic_term_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">-- Optional --</option>
                                    @foreach($terms as $t)
                                        <option value="{{ $t->id }}" {{ $t->is_default ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- CLOs --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Course Learning Outcomes (CLOs)</h3>
                <button type="button" @click="addClo()" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add CLO
                </button>
            </div>
            <div class="p-6">
                <template x-if="clos.length === 0">
                    <p class="text-sm text-slate-400 text-center py-4">No CLOs added yet. Click "Add CLO" to define learning outcomes.</p>
                </template>
                <div class="space-y-3">
                    <template x-for="(clo, index) in clos" :key="index">
                        <div class="flex items-start gap-3 bg-slate-50 rounded-xl p-4">
                            <input type="text" :name="'clos['+index+'][code]'" x-model="clo.code" placeholder="CLO1" class="w-20 px-3 py-2 rounded-lg border border-slate-300 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            <input type="text" :name="'clos['+index+'][description]'" x-model="clo.description" placeholder="Describe the learning outcome..." class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            <button type="button" @click="clos.splice(index, 1)" class="p-2 text-slate-400 hover:text-red-500 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Weekly Topics --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Weekly Topics</h3>
                <button type="button" @click="generateWeeks()" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Generate Weeks
                </button>
            </div>
            <div class="p-6">
                <template x-if="topics.length === 0">
                    <p class="text-sm text-slate-400 text-center py-4">Click "Generate Weeks" to create topic slots for each week.</p>
                </template>
                <div class="space-y-3">
                    <template x-for="(topic, index) in topics" :key="index">
                        <div class="flex items-center gap-3">
                            <input type="hidden" :name="'topics['+index+'][week_number]'" :value="topic.week">
                            <span class="w-16 text-xs font-semibold text-slate-500 text-right flex-shrink-0" x-text="'Week ' + topic.week"></span>
                            <input type="text" :name="'topics['+index+'][title]'" x-model="topic.title" placeholder="Topic title..." class="flex-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            <button type="button" @click="topics.splice(index, 1)" class="p-1.5 text-slate-400 hover:text-red-500 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tenant.courses.index', app('current_tenant')->slug) }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                Create Course
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function courseForm() {
            return {
                numWeeks: {{ old('num_weeks', 14) }},
                clos: [],
                topics: [],
                addClo() {
                    const num = this.clos.length + 1;
                    this.clos.push({ code: 'CLO' + num, description: '' });
                },
                generateWeeks() {
                    this.topics = [];
                    for (let i = 1; i <= this.numWeeks; i++) {
                        this.topics.push({ week: i, title: '' });
                    }
                }
            }
        }
    </script>
    @endpush
</x-tenant-layout>
