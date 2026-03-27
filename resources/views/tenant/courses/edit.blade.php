<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Edit {{ $course->code }}</h2>
                <p class="mt-0.5 text-sm text-slate-500">{{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.courses.update', [app('current_tenant')->slug, $course]) }}" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Course Information</h3>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-700 mb-1.5">Course Code *</label>
                        <input type="text" name="code" id="code" value="{{ old('code', $course->code) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-700 mb-1.5">Course Title *</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $course->title) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                    <textarea name="description" id="description" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $course->description) }}</textarea>
                </div>

                <div class="grid sm:grid-cols-3 gap-5">
                    <div>
                        <label for="credit_hours" class="block text-sm font-medium text-slate-700 mb-1.5">Credit Hours</label>
                        <input type="number" name="credit_hours" value="{{ old('credit_hours', $course->credit_hours) }}" min="1" max="20" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label for="num_weeks" class="block text-sm font-medium text-slate-700 mb-1.5">Weeks *</label>
                        <input type="number" name="num_weeks" value="{{ old('num_weeks', $course->num_weeks) }}" required min="1" max="52" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label for="teaching_mode" class="block text-sm font-medium text-slate-700 mb-1.5">Teaching Mode</label>
                        <select name="teaching_mode" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="face_to_face" {{ $course->teaching_mode === 'face_to_face' ? 'selected' : '' }}>Face to Face</option>
                            <option value="online" {{ $course->teaching_mode === 'online' ? 'selected' : '' }}>Online</option>
                            <option value="hybrid" {{ $course->teaching_mode === 'hybrid' ? 'selected' : '' }}>Hybrid</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                    <select name="status" class="w-full sm:w-48 px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="draft" {{ $course->status === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ $course->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $course->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="archived" {{ $course->status === 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-400">Use <strong>Inactive</strong> for past semester courses. Students will still see their data but the course won't appear as current.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <form method="POST" action="{{ route('tenant.courses.destroy', [app('current_tenant')->slug, $course]) }}">
                @csrf @method('DELETE')
                <button type="submit" onclick="return confirm('Delete this course permanently?')" class="text-sm text-red-600 hover:text-red-700 font-medium">Delete Course</button>
            </form>

            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save Changes</button>
            </div>
        </div>
    </form>
</x-tenant-layout>
