<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.materials.student-index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }} — Materials</h2>
                <p class="text-sm text-slate-500">{{ $course->title }} &middot; {{ $course->lecturer->name ?? '' }}</p>
            </div>
        </div>
    </x-slot>

    @if($materials->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 mb-1">No materials available yet</h3>
            <p class="text-sm text-slate-500">Your lecturer hasn't uploaded any materials for this course yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @for($week = 1; $week <= $course->num_weeks; $week++)
                @php
                    $topic = $topics[$week] ?? null;
                    $weekMaterials = $materials[$week] ?? collect();
                @endphp
                @if($weekMaterials->isNotEmpty())
                    <div x-data="{ open: true }" class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                        {{-- Week Header --}}
                        <div class="px-5 py-4 flex items-center justify-between cursor-pointer" @click="open = !open">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700">W{{ $week }}</span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $topic?->title ?? 'Week ' . $week }}</h3>
                                    <p class="text-xs text-slate-400">{{ $weekMaterials->count() }} {{ Str::plural('material', $weekMaterials->count()) }}</p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>

                        {{-- Materials --}}
                        <div x-show="open" x-cloak x-transition class="border-t border-slate-100 divide-y divide-slate-50">
                            @foreach($weekMaterials as $material)
                                <div class="px-5 py-3 flex items-center justify-between gap-4 hover:bg-slate-50/50 transition">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($material->isLink())
                                            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                            </div>
                                        @elseif(str_contains($material->file_type ?? '', 'pdf'))
                                            <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            </div>
                                        @elseif(str_contains($material->file_type ?? '', 'image'))
                                            <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @else
                                            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-slate-900 truncate">{{ $material->file_name }}</p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                @if($material->isLink())
                                                    <span class="text-[10px] font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">Link</span>
                                                @else
                                                    <span class="text-xs text-slate-400">{{ $material->formattedSize() }}</span>
                                                @endif
                                                @if($material->description)
                                                    <span class="text-slate-300">&middot;</span>
                                                    <span class="text-xs text-slate-400 truncate">{{ Str::limit($material->description, 60) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0">
                                        @if($material->isLink())
                                            <a href="{{ $material->url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                Open
                                            </a>
                                        @else
                                            <a href="{{ route('tenant.materials.download', [$tenant->slug, $course, $material]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Download
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endfor
        </div>
    @endif
</x-tenant-layout>
