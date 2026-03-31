<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ __('performance.my_performance') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('performance.my_performance_subtitle') }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Overall Summary Cards --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-emerald-200 p-5">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <p class="text-2xl font-bold {{ $data['overall_avg_mark'] !== null && $data['overall_avg_mark'] >= 50 ? 'text-emerald-600' : ($data['overall_avg_mark'] !== null ? 'text-red-600' : 'text-slate-900') }}">
                    {{ $data['overall_avg_mark'] !== null ? number_format($data['overall_avg_mark'], 1) . '%' : '--' }}
                </p>
                <p class="text-sm text-slate-500">{{ __('performance.overall_avg_mark') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-teal-200 p-5">
                <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <p class="text-2xl font-bold {{ $data['overall_attendance'] !== null && $data['overall_attendance'] >= 80 ? 'text-teal-600' : ($data['overall_attendance'] !== null ? 'text-amber-600' : 'text-slate-900') }}">
                    {{ $data['overall_attendance'] !== null ? number_format($data['overall_attendance'], 1) . '%' : '--' }}
                </p>
                <p class="text-sm text-slate-500">{{ __('performance.overall_attendance') }}</p>
            </div>
        </div>

        {{-- Course Cards --}}
        @if($data['courses']->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900 mb-1">{{ __('performance.no_courses_enrolled') }}</h3>
                <p class="text-sm text-slate-500">{{ __('performance.no_courses_enrolled_hint') }}</p>
            </div>
        @else
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                @foreach($data['courses'] as $courseData)
                    @php
                        $course = $courseData['course'];
                        $composite = $courseData['composite_score'];
                    @endphp
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md hover:border-indigo-200 transition group">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-200 transition">
                                    <span class="text-sm font-bold text-indigo-700">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 group-hover:text-indigo-700 transition">{{ $course->code }}</p>
                                    <p class="text-sm text-slate-500 truncate">{{ $course->title }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Mini Stats --}}
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <div class="text-center bg-slate-50 rounded-lg p-2">
                                <p class="text-xs font-bold {{ $courseData['avg_mark'] !== null && $courseData['avg_mark'] >= 50 ? 'text-emerald-600' : ($courseData['avg_mark'] !== null ? 'text-red-600' : 'text-slate-400') }}">
                                    {{ $courseData['avg_mark'] !== null ? number_format($courseData['avg_mark'], 0) . '%' : '--' }}
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ __('performance.mark') }}</p>
                            </div>
                            <div class="text-center bg-slate-50 rounded-lg p-2">
                                <p class="text-xs font-bold {{ $courseData['avg_quiz'] !== null && $courseData['avg_quiz'] >= 50 ? 'text-violet-600' : ($courseData['avg_quiz'] !== null ? 'text-red-600' : 'text-slate-400') }}">
                                    {{ $courseData['avg_quiz'] !== null ? number_format($courseData['avg_quiz'], 0) . '%' : '--' }}
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ __('performance.quiz') }}</p>
                            </div>
                            <div class="text-center bg-slate-50 rounded-lg p-2">
                                <p class="text-xs font-bold {{ $courseData['attendance_rate'] !== null && $courseData['attendance_rate'] >= 80 ? 'text-teal-600' : ($courseData['attendance_rate'] !== null ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ $courseData['attendance_rate'] !== null ? number_format($courseData['attendance_rate'], 0) . '%' : '--' }}
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ __('performance.attendance') }}</p>
                            </div>
                        </div>

                        {{-- Composite Score --}}
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full {{ $composite !== null && $composite >= 70 ? 'bg-emerald-500' : ($composite !== null && $composite >= 50 ? 'bg-amber-500' : ($composite !== null ? 'bg-red-500' : 'bg-slate-300')) }}"></div>
                                <span class="text-sm font-bold {{ $composite !== null && $composite >= 70 ? 'text-emerald-600' : ($composite !== null && $composite >= 50 ? 'text-amber-600' : ($composite !== null ? 'text-red-600' : 'text-slate-400')) }}">
                                    {{ $composite !== null ? number_format($composite, 1) . '%' : '--' }}
                                </span>
                                <span class="text-xs text-slate-400">{{ __('performance.composite') }}</span>
                            </div>
                            <a href="{{ route('tenant.my-performance.course', [app('current_tenant')->slug, $course]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition">
                                {{ __('performance.view_details') }}
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
