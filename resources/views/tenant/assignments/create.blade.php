<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assignments.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Create Assignment</h2>
                <p class="mt-1 text-sm text-slate-500">Define the assignment and build a rubric</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.assignments.store', app('current_tenant')->slug) }}" x-data="assignmentForm()" class="space-y-6">
        @csrf

        {{-- Basic Info --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Assignment 1: Linked Lists" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Course *</label>
                    <select name="course_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                <textarea name="description" rows="3" placeholder="Instructions for students..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            </div>

            <div class="grid sm:grid-cols-4 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Total Marks *</label>
                    <input type="number" name="total_marks" required value="100" min="1" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Deadline</label>
                    <input type="datetime-local" name="deadline" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Type</label>
                    <select name="type" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="individual">Individual</option>
                        <option value="group">Group</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Marking</label>
                    <select name="marking_mode" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="manual">Manual</option>
                        <option value="ai_assisted">AI-Assisted</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Answer Scheme (for AI marking)</label>
                <textarea name="answer_scheme" rows="3" placeholder="Paste your answer scheme or model answers here..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            </div>
        </div>

        {{-- Rubric Builder --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Rubric Criteria</h3>
                <button type="button" @click="addCriteria()" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Criteria
                </button>
            </div>
            <div class="p-6 space-y-4">
                <template x-if="criteria.length === 0">
                    <p class="text-sm text-slate-400 text-center py-4">Add rubric criteria to define how submissions will be evaluated.</p>
                </template>

                <template x-for="(c, ci) in criteria" :key="ci">
                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="flex-1 grid sm:grid-cols-3 gap-3">
                                <div class="sm:col-span-2">
                                    <input type="text" :name="'criteria['+ci+'][title]'" x-model="c.title" required placeholder="Criteria name (e.g. Content Accuracy)" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm font-medium focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <input type="number" :name="'criteria['+ci+'][max_marks]'" x-model="c.max_marks" required min="0" placeholder="Max marks" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <button type="button" @click="criteria.splice(ci, 1)" class="p-1.5 text-slate-400 hover:text-red-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Levels --}}
                        <div class="ml-2 space-y-2">
                            <template x-for="(l, li) in c.levels" :key="li">
                                <div class="flex items-center gap-2">
                                    <input type="text" :name="'criteria['+ci+'][levels]['+li+'][label]'" x-model="l.label" placeholder="Level" class="w-24 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <input type="text" :name="'criteria['+ci+'][levels]['+li+'][description]'" x-model="l.description" placeholder="Description..." class="flex-1 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <input type="number" :name="'criteria['+ci+'][levels]['+li+'][marks]'" x-model="l.marks" min="0" placeholder="Marks" class="w-16 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <button type="button" @click="c.levels.splice(li, 1)" class="text-slate-400 hover:text-red-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                </div>
                            </template>
                            <button type="button" @click="addLevel(ci)" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">+ Add level</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tenant.assignments.index', app('current_tenant')->slug) }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Create Assignment</button>
        </div>
    </form>

    @push('scripts')
    <script>
        function assignmentForm() {
            return {
                criteria: [],
                addCriteria() {
                    this.criteria.push({
                        title: '',
                        max_marks: 25,
                        levels: [
                            { label: 'Excellent', description: '', marks: 25 },
                            { label: 'Good', description: '', marks: 18 },
                            { label: 'Average', description: '', marks: 12 },
                            { label: 'Poor', description: '', marks: 5 },
                        ]
                    });
                },
                addLevel(ci) {
                    this.criteria[ci].levels.push({ label: '', description: '', marks: 0 });
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
