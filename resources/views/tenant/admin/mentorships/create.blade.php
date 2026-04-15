<x-tenant-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('tenant.admin.mentorships.index', $tenant->slug) }}" class="text-xs text-indigo-600 hover:underline">← Back</a>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Assign Mentorship</h2>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.admin.mentorships.store', $tenant->slug) }}" class="max-w-2xl">
        @csrf

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Lecturer</label>
                <select name="lecturer_id" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option value="">Select lecturer…</option>
                    @foreach($lecturers as $lecturer)
                        <option value="{{ $lecturer->id }}" @selected(old('lecturer_id') == $lecturer->id)>{{ $lecturer->name }} — {{ $lecturer->email }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Role</label>
                <select name="role" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option value="academic_tutor" @selected(old('role') === 'academic_tutor')>Academic Tutor</option>
                    <option value="li_supervisor" @selected(old('role') === 'li_supervisor')>LI Supervisor</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Academic Term <span class="text-slate-400">(optional)</span></label>
                <select name="academic_term_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option value="">— No specific term —</option>
                    @foreach($terms as $term)
                        <option value="{{ $term->id }}" @selected(old('academic_term_id') == $term->id)>{{ $term->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Students <span class="text-slate-400">(select one or more)</span></label>
                <select name="student_ids[]" multiple required size="10"
                    class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">{{ $student->name }} — {{ $student->email }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Hold Ctrl/Cmd to select multiple.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Notes <span class="text-slate-400">(optional)</span></label>
                <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm">{{ old('notes') }}</textarea>
            </div>

            @if($errors->any())
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-xs text-red-700 dark:text-red-300">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="pt-5 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                <a href="{{ route('tenant.admin.mentorships.index', $tenant->slug) }}"
                   class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Assign</button>
            </div>
        </div>
    </form>
</x-tenant-layout>
