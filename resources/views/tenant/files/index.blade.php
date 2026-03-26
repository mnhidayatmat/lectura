<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Course Files</h2>
            <p class="mt-1 text-sm text-slate-500">Select a course to manage its files and folders</p>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-20 h-20 bg-violet-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
            <p class="text-sm text-slate-500">Create a course first to manage files.</p>
        </div>
    @else
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($courses as $course)
                <a href="{{ route('tenant.files.manage', [app('current_tenant')->slug, $course]) }}" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md hover:border-violet-200 transition group">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition">
                            <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 group-hover:text-violet-700 transition">{{ $course->code }}</p>
                            <p class="text-sm text-slate-500">{{ $course->title }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">{{ $course->sections_count }} sections</p>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
