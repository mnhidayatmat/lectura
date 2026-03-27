<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.active-learning.index', [app('current_tenant')->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('active_learning.new_plan') }}</h2>
                <p class="text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('tenant.active-learning.store', [app('current_tenant')->slug, $course]) }}" class="space-y-6">
            @csrf

            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Plan Details</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Set up the basic information for your active learning plan</p>
                </div>
                <div class="p-6 space-y-5">
                    {{-- Title --}}
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-700">{{ __('active_learning.plan_title') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" required
                            class="mt-1.5 w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            placeholder="{{ __('active_learning.plan_title_placeholder') }}">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Topic Link --}}
                    <div>
                        <label for="course_topic_id" class="block text-sm font-medium text-slate-700">{{ __('active_learning.linked_topic') }}</label>
                        <select name="course_topic_id" id="course_topic_id"
                            class="mt-1.5 w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 transition"
                            onchange="document.getElementById('week_number').value = this.options[this.selectedIndex].dataset.week || ''">
                            <option value="">-- {{ __('active_learning.standalone') }} --</option>
                            @foreach($course->topics as $topic)
                                <option value="{{ $topic->id }}" data-week="{{ $topic->week_number }}" {{ old('course_topic_id') == $topic->id ? 'selected' : '' }}>
                                    W{{ $topic->week_number }}: {{ $topic->title }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Link to a course topic to auto-fill week number</p>
                    </div>

                    {{-- Week + Duration Row --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="week_number" class="block text-sm font-medium text-slate-700">{{ __('active_learning.week_number') }}</label>
                            <input type="number" name="week_number" id="week_number" value="{{ old('week_number') }}" min="1" max="52"
                                class="mt-1.5 w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 transition"
                                placeholder="e.g. 3">
                        </div>
                        <div>
                            <label for="duration_minutes" class="block text-sm font-medium text-slate-700">{{ __('active_learning.duration') }}</label>
                            <div class="relative mt-1.5">
                                <input type="number" name="duration_minutes" id="duration_minutes" value="{{ old('duration_minutes', 90) }}" min="5" max="480"
                                    class="w-full px-4 py-2.5 pr-16 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{ __('active_learning.min') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-sm font-medium text-slate-700">{{ __('active_learning.description') }}</label>
                        <textarea name="description" id="description" rows="3"
                            class="mt-1.5 w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 transition"
                            placeholder="{{ __('active_learning.description_placeholder') }}">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('active_learning.create_and_build') }}
                </button>
                <a href="{{ route('tenant.active-learning.index', [app('current_tenant')->slug, $course]) }}" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 transition">
                    {{ __('active_learning.cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-tenant-layout>
