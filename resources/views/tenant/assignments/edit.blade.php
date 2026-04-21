<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assignments.show', [app('current_tenant')->slug, $assignment]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Edit Assessment</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $assignment->title }}</p>
            </div>
        </div>
    </x-slot>

    <form method="POST"
          action="{{ route('tenant.assignments.update', [app('current_tenant')->slug, $assignment]) }}"
          enctype="multipart/form-data"
          x-data="assignmentForm({
              subType: @js($assignment->submission_type ?? 'file'),
              assignmentType: @js($assignment->type),
              selectedGroupSetId: @js($assignment->student_group_set_id),
          })"
          class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Basic Info --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Title *</label>
                    <input type="text" name="title" required value="{{ old('title', $assignment->title) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Course *</label>
                    <select name="course_id" required @change="onCourseChange($event.target.value)" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}" @selected(old('course_id', $assignment->course_id) == $c->id)>{{ $c->code }} — {{ $c->title }}</option>
                        @endforeach
                    </select>
                    @error('course_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $assignment->description) }}</textarea>
            </div>

            <div class="grid sm:grid-cols-4 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Total Marks *</label>
                    <input type="number" name="total_marks" required value="{{ old('total_marks', $assignment->total_marks) }}" min="1" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Deadline</label>
                    <input type="datetime-local" name="deadline" value="{{ old('deadline', optional($assignment->deadline)->format('Y-m-d\TH:i')) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Type</label>
                    <select name="type" x-model="assignmentType" @change="onTypeChange()" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="individual">Individual</option>
                        <option value="group">Group</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Marking</label>
                    <select name="marking_mode" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="manual" @selected(old('marking_mode', $assignment->marking_mode) === 'manual')>Manual</option>
                        <option value="ai_assisted" @selected(old('marking_mode', $assignment->marking_mode) === 'ai_assisted')>AI-Assisted</option>
                    </select>
                </div>
            </div>

            {{-- Submission Type --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Submission Type *</label>
                <input type="hidden" name="submission_type" :value="subType" />
                <div class="grid sm:grid-cols-3 gap-3">
                    <button type="button" @click="subType = 'file'"
                        :class="subType === 'file' ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'"
                        class="flex items-center gap-3 p-4 rounded-xl border-2 transition text-left">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="subType === 'file' ? 'bg-indigo-100' : 'bg-slate-100'">
                            <svg class="w-5 h-5" :class="subType === 'file' ? 'text-indigo-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" :class="subType === 'file' ? 'text-indigo-900' : 'text-slate-700'">File Upload</p>
                            <p class="text-xs text-slate-500">Students upload files</p>
                        </div>
                    </button>
                    <button type="button" @click="subType = 'text'"
                        :class="subType === 'text' ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'"
                        class="flex items-center gap-3 p-4 rounded-xl border-2 transition text-left">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="subType === 'text' ? 'bg-indigo-100' : 'bg-slate-100'">
                            <svg class="w-5 h-5" :class="subType === 'text' ? 'text-indigo-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" :class="subType === 'text' ? 'text-indigo-900' : 'text-slate-700'">Text Input</p>
                            <p class="text-xs text-slate-500">Students type their answer</p>
                        </div>
                    </button>
                    <button type="button" @click="subType = 'both'"
                        :class="subType === 'both' ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'"
                        class="flex items-center gap-3 p-4 rounded-xl border-2 transition text-left">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="subType === 'both' ? 'bg-indigo-100' : 'bg-slate-100'">
                            <svg class="w-5 h-5" :class="subType === 'both' ? 'text-indigo-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" :class="subType === 'both' ? 'text-indigo-900' : 'text-slate-700'">Both</p>
                            <p class="text-xs text-slate-500">Files and/or text</p>
                        </div>
                    </button>
                </div>
            </div>

            {{-- Answer Scheme --}}
            <div class="space-y-3" x-data="{ schemeMode: 'text' }">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-slate-700">Answer Scheme <span class="text-slate-400 font-normal">(for AI marking)</span></label>
                    <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
                        <button type="button" @click="schemeMode = 'text'" :class="schemeMode === 'text' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-2.5 py-1 text-[11px] font-medium rounded-md transition">Text</button>
                        <button type="button" @click="schemeMode = 'file'" :class="schemeMode === 'file' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-2.5 py-1 text-[11px] font-medium rounded-md transition">Upload PDF</button>
                        <button type="button" @click="schemeMode = 'both'" :class="schemeMode === 'both' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-2.5 py-1 text-[11px] font-medium rounded-md transition">Both</button>
                    </div>
                </div>

                <div x-show="schemeMode === 'text' || schemeMode === 'both'" x-transition>
                    <textarea name="answer_scheme" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('answer_scheme', $assignment->answer_scheme) }}</textarea>
                </div>

                <div x-show="schemeMode === 'file' || schemeMode === 'both'" x-transition>
                    @if($assignment->answer_scheme_filename)
                        <p class="text-xs text-emerald-600 mb-2">Current: {{ $assignment->answer_scheme_filename }} (upload a new file to replace)</p>
                    @endif
                    <input type="file" name="answer_scheme_file" accept=".pdf" class="w-full text-sm" />
                </div>
            </div>
        </div>

        {{-- Group Set Picker --}}
        <div x-show="assignmentType === 'group'" x-transition class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Group Submission</h3>
                <p class="text-xs text-slate-400 mt-0.5">Pick an existing group set for this course. Submissions are made by the group leader — students elect the leader through the in-group voting system.</p>
            </div>
            <div class="p-6 space-y-3">
                <div x-show="loadingGroupSets" class="text-center py-6">
                    <svg class="animate-spin h-6 w-6 text-indigo-500 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <p class="text-sm text-slate-500">Loading group sets...</p>
                </div>

                <template x-if="!loadingGroupSets && groupSets.length === 0">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <p class="font-medium">No group sets found for this course.</p>
                        <p class="text-xs mt-1">Create groups first at <span class="font-mono">Courses → Groups</span>, then return to edit this assessment.</p>
                    </div>
                </template>

                @error('student_group_set_id')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div x-show="!loadingGroupSets && groupSets.length > 0" class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Select Group Set *</label>
                    <template x-for="set in groupSets" :key="set.id">
                        <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                            :class="String(selectedGroupSetId) === String(set.id) ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'">
                            <input type="radio" name="student_group_set_id" :value="set.id" x-model="selectedGroupSetId" required class="mt-1" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-slate-900" x-text="set.name"></p>
                                    <span class="text-[10px] font-medium uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-600" x-text="set.type"></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <span x-text="set.groups_count"></span> groups &middot;
                                    <span x-text="set.total_members"></span> members
                                </p>
                                <template x-if="set.description">
                                    <p class="text-xs text-slate-400 mt-1" x-text="set.description"></p>
                                </template>
                            </div>
                        </label>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tenant.assignments.show', [app('current_tenant')->slug, $assignment]) }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save Changes</button>
        </div>
    </form>

    @push('scripts')
    <script>
        function assignmentForm(initial = {}) {
            return {
                subType: initial.subType || 'file',
                assignmentType: initial.assignmentType || 'individual',
                groupSets: [],
                selectedGroupSetId: initial.selectedGroupSetId || '',
                loadingGroupSets: false,
                groupSetsUrl: '{{ route("tenant.assignments.course-group-sets", [app("current_tenant")->slug, ":courseId"]) }}',

                init() {
                    if (this.assignmentType === 'group') {
                        const courseSelect = document.querySelector('select[name="course_id"]');
                        if (courseSelect && courseSelect.value) {
                            this.fetchGroupSets(courseSelect.value);
                        }
                    }
                },

                onCourseChange(courseId) {
                    if (this.assignmentType === 'group' && courseId) {
                        this.selectedGroupSetId = '';
                        this.fetchGroupSets(courseId);
                    }
                },
                onTypeChange() {
                    if (this.assignmentType === 'group') {
                        const courseSelect = document.querySelector('select[name="course_id"]');
                        if (courseSelect && courseSelect.value) {
                            this.fetchGroupSets(courseSelect.value);
                        }
                    }
                },
                async fetchGroupSets(courseId) {
                    this.loadingGroupSets = true;
                    try {
                        const url = this.groupSetsUrl.replace(':courseId', courseId);
                        const res = await fetch(url);
                        this.groupSets = await res.json();
                    } catch (e) {
                        this.groupSets = [];
                    }
                    this.loadingGroupSets = false;
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
