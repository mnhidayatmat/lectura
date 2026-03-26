<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.courses') }}</h2>
                <p class="mt-1 text-sm text-slate-500">Manage your courses, sections, and students</p>
            </div>
            <a href="#" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm shadow-indigo-500/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Course
            </a>
        </div>
    </x-slot>

    {{-- Empty state --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="p-12 flex flex-col items-center justify-center text-center">
            <div class="w-20 h-20 bg-indigo-50 rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
            <p class="text-sm text-slate-500 max-w-sm mb-6">Create your first course to start managing sections, students, teaching plans, and assessments.</p>

            <a href="#" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Your First Course
            </a>

            {{-- Hint cards --}}
            <div class="mt-10 grid sm:grid-cols-3 gap-4 w-full max-w-2xl">
                <div class="text-left bg-slate-50 rounded-xl p-4 border border-slate-100">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center mb-3">
                        <span class="text-sm font-bold text-indigo-600">1</span>
                    </div>
                    <p class="text-sm font-medium text-slate-700">Create a course</p>
                    <p class="text-xs text-slate-400 mt-1">Add course code, title, CLOs, and weekly topics</p>
                </div>
                <div class="text-left bg-slate-50 rounded-xl p-4 border border-slate-100">
                    <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center mb-3">
                        <span class="text-sm font-bold text-teal-600">2</span>
                    </div>
                    <p class="text-sm font-medium text-slate-700">Add sections & students</p>
                    <p class="text-xs text-slate-400 mt-1">Import via CSV or share an invite code</p>
                </div>
                <div class="text-left bg-slate-50 rounded-xl p-4 border border-slate-100">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center mb-3">
                        <span class="text-sm font-bold text-amber-600">3</span>
                    </div>
                    <p class="text-sm font-medium text-slate-700">Start teaching</p>
                    <p class="text-xs text-slate-400 mt-1">Generate AI plans, take attendance, run quizzes</p>
                </div>
            </div>
        </div>
    </div>
</x-tenant-layout>
