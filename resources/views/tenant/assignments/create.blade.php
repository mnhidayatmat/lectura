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

    <form method="POST" action="{{ route('tenant.assignments.store', app('current_tenant')->slug) }}" enctype="multipart/form-data" x-data="assignmentForm()" class="space-y-6">
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

            {{-- Submission Type --}}
            <div x-data="{ subType: 'file' }">
                <label class="block text-sm font-medium text-slate-700 mb-2">Submission Type *</label>
                <div class="grid sm:grid-cols-3 gap-3">
                    <label @click="subType = 'file'" class="relative cursor-pointer">
                        <input type="radio" name="submission_type" value="file" x-model="subType" class="peer sr-only" checked />
                        <div class="flex items-center gap-3 p-4 rounded-xl border-2 transition peer-checked:border-indigo-500 peer-checked:bg-indigo-50 border-slate-200 hover:border-slate-300">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="subType === 'file' ? 'bg-indigo-100' : 'bg-slate-100'">
                                <svg class="w-5 h-5" :class="subType === 'file' ? 'text-indigo-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" :class="subType === 'file' ? 'text-indigo-900' : 'text-slate-700'">File Upload</p>
                                <p class="text-xs text-slate-500">Students upload files</p>
                            </div>
                        </div>
                    </label>
                    <label @click="subType = 'text'" class="relative cursor-pointer">
                        <input type="radio" name="submission_type" value="text" x-model="subType" class="peer sr-only" />
                        <div class="flex items-center gap-3 p-4 rounded-xl border-2 transition peer-checked:border-indigo-500 peer-checked:bg-indigo-50 border-slate-200 hover:border-slate-300">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="subType === 'text' ? 'bg-indigo-100' : 'bg-slate-100'">
                                <svg class="w-5 h-5" :class="subType === 'text' ? 'text-indigo-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" :class="subType === 'text' ? 'text-indigo-900' : 'text-slate-700'">Text Input</p>
                                <p class="text-xs text-slate-500">Students type their answer</p>
                            </div>
                        </div>
                    </label>
                    <label @click="subType = 'both'" class="relative cursor-pointer">
                        <input type="radio" name="submission_type" value="both" x-model="subType" class="peer sr-only" />
                        <div class="flex items-center gap-3 p-4 rounded-xl border-2 transition peer-checked:border-indigo-500 peer-checked:bg-indigo-50 border-slate-200 hover:border-slate-300">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="subType === 'both' ? 'bg-indigo-100' : 'bg-slate-100'">
                                <svg class="w-5 h-5" :class="subType === 'both' ? 'text-indigo-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" :class="subType === 'both' ? 'text-indigo-900' : 'text-slate-700'">Both</p>
                                <p class="text-xs text-slate-500">Files and/or text</p>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Answer Scheme --}}
            <div class="space-y-3" x-data="{ schemeMode: 'text' }">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-slate-700">Answer Scheme <span class="text-slate-400 font-normal">(for AI marking)</span></label>
                    <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
                        <button type="button" @click="schemeMode = 'text'" :class="schemeMode === 'text' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-2.5 py-1 text-[11px] font-medium rounded-md transition">
                            Text
                        </button>
                        <button type="button" @click="schemeMode = 'file'" :class="schemeMode === 'file' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-2.5 py-1 text-[11px] font-medium rounded-md transition">
                            Upload PDF
                        </button>
                        <button type="button" @click="schemeMode = 'both'" :class="schemeMode === 'both' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-2.5 py-1 text-[11px] font-medium rounded-md transition">
                            Both
                        </button>
                    </div>
                </div>

                {{-- Text input --}}
                <div x-show="schemeMode === 'text' || schemeMode === 'both'" x-transition>
                    <textarea name="answer_scheme" rows="4" placeholder="Paste your answer scheme or model answers here. AI will use this to grade student submissions..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                {{-- File upload --}}
                <div x-show="schemeMode === 'file' || schemeMode === 'both'" x-transition>
                    <div class="border-2 border-dashed border-slate-300 rounded-xl p-5 text-center hover:border-indigo-300 transition" x-data="{ fileName: null }">
                        <input type="file" name="answer_scheme_file" accept=".pdf" class="hidden" id="answer-scheme-upload"
                            @change="fileName = $event.target.files[0]?.name">
                        <label for="answer-scheme-upload" class="cursor-pointer">
                            <div x-show="!fileName" class="space-y-2">
                                <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center mx-auto">
                                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <p class="text-sm text-slate-600">Click to upload <span class="font-semibold text-indigo-600">PDF file</span></p>
                                <p class="text-xs text-slate-400">Answer scheme, model answers, marking guide (max 25MB)</p>
                            </div>
                            <div x-show="fileName" class="flex items-center justify-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="text-left">
                                    <p class="text-sm font-medium text-slate-900" x-text="fileName"></p>
                                    <p class="text-xs text-emerald-600">Ready to upload</p>
                                </div>
                                <button type="button" @click.prevent="fileName = null; document.getElementById('answer-scheme-upload').value = ''" class="p-1 text-slate-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </label>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">AI will extract and read the PDF content to grade student submissions against the answer scheme.</p>
                </div>
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
