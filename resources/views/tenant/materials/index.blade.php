<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Course Materials</h2>
            <p class="mt-1 text-sm text-slate-500">Manage learning materials for your courses</p>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
            <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
            <p class="text-sm text-slate-500 max-w-md mx-auto">Create a course first to start uploading materials.</p>
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($courses as $course)
                <a href="{{ route('tenant.materials.manage', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-md p-5 transition-all">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-indigo-600">{{ $course->code }}</p>
                            <h3 class="text-sm font-semibold text-slate-900 truncate group-hover:text-indigo-700 transition">{{ $course->title }}</h3>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-xs text-slate-400">{{ $course->num_weeks }} weeks</span>
                                <span class="text-slate-300">&middot;</span>
                                <span class="text-xs text-slate-400">{{ $course->files_count }} {{ Str::plural('file', $course->files_count) }}</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-300 group-hover:text-indigo-400 transition flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
