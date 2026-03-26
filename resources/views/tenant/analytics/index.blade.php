<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Analytics</h2>
            <p class="mt-1 text-sm text-slate-500">Student performance and attendance insights</p>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-20 h-20 bg-violet-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
            <p class="text-sm text-slate-500">Create courses and collect data to see analytics.</p>
        </div>
    @else
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($courses as $course)
                <a href="{{ route('tenant.analytics.course', [app('current_tenant')->slug, $course]) }}" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md hover:border-indigo-200 transition group">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 group-hover:text-indigo-700 transition">{{ $course->code }}</p>
                            <p class="text-sm text-slate-500">{{ $course->title }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span>{{ $course->sections->sum(fn($s) => $s->activeStudents->count()) }} students</span>
                        <span>&middot;</span>
                        <span>{{ $course->sections->count() }} sections</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
