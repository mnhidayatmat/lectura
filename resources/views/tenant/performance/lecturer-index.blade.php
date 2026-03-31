<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ __('performance.tracking_title') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('performance.tracking_subtitle') }}</p>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-20 h-20 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('performance.no_courses') }}</h3>
            <p class="text-sm text-slate-500 max-w-sm mx-auto">{{ __('performance.no_courses_hint') }}</p>
        </div>
    @else
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($courses as $course)
                <a href="{{ route('tenant.performance.course', [app('current_tenant')->slug, $course]) }}" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md hover:border-indigo-200 transition group">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition">
                            <span class="text-sm font-bold text-indigo-700">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 group-hover:text-indigo-700 transition">{{ $course->code }}</p>
                            <p class="text-sm text-slate-500 truncate">{{ $course->title }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $course->totalStudents() }} {{ __('performance.students') }}
                        </span>
                        <span>&middot;</span>
                        <span>{{ $course->sections->count() }} {{ __('performance.sections') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
