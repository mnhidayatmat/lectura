<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.materials.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }} — Materials</h2>
                    <p class="text-sm text-slate-500">{{ $course->title }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-4">
        @for($week = 1; $week <= $course->num_weeks; $week++)
            @php
                $topic = $topics[$week] ?? null;
                $weekMaterials = $materials[$week] ?? collect();
            @endphp
            <div x-data="{ open: {{ $weekMaterials->isNotEmpty() ? 'true' : 'false' }}, showUpload: false, showLink: false }" class="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:border-slate-300 transition">
                {{-- Week Header --}}
                <div class="px-5 py-4 flex items-center justify-between cursor-pointer" @click="open = !open">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl {{ $weekMaterials->isNotEmpty() ? 'bg-indigo-100' : 'bg-slate-100' }} flex items-center justify-center text-sm font-bold {{ $weekMaterials->isNotEmpty() ? 'text-indigo-700' : 'text-slate-400' }}">W{{ $week }}</span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                {{ $topic?->title ?? 'Week ' . $week }}
                            </h3>
                            <p class="text-xs text-slate-400">{{ $weekMaterials->count() }} {{ Str::plural('material', $weekMaterials->count()) }}</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>

                {{-- Week Content --}}
                <div x-show="open" x-cloak x-transition class="border-t border-slate-100">
                    {{-- Materials List --}}
                    @if($weekMaterials->isNotEmpty())
                        <div class="divide-y divide-slate-50">
                            @foreach($weekMaterials as $material)
                                <div class="px-5 py-3 flex items-center justify-between gap-4 group hover:bg-slate-50/50 transition">
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
                                        @elseif(str_contains($material->file_type ?? '', 'presentation') || str_contains($material->file_type ?? '', 'pptx'))
                                            <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
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
                                                    <span class="text-xs text-slate-400 truncate">{{ Str::limit($material->description, 50) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                        @if($material->isLink())
                                            <a href="{{ $material->url }}" target="_blank" class="p-2 rounded-lg text-blue-500 hover:bg-blue-50 transition" title="Open Link">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        @else
                                            <a href="{{ route('tenant.materials.download', [$tenant->slug, $course, $material]) }}" class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 transition" title="Download">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('tenant.materials.destroy', [$tenant->slug, $course, $material]) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" onclick="return confirm('Remove this material?')" class="p-2 rounded-lg text-red-400 hover:bg-red-50 transition" title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="px-5 py-3 bg-slate-50/50 flex items-center gap-2">
                        <button @click="showUpload = !showUpload; showLink = false" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Upload File
                        </button>
                        <button @click="showLink = !showLink; showUpload = false" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            Add Link
                        </button>
                    </div>

                    {{-- Upload Form --}}
                    <div x-show="showUpload" x-cloak x-transition class="px-5 py-4 border-t border-slate-100 bg-slate-50">
                        <form method="POST" action="{{ route('tenant.materials.upload', [$tenant->slug, $course]) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="week_number" value="{{ $week }}">
                            <div>
                                <label class="text-xs font-medium text-slate-600">Files</label>
                                <input type="file" name="files[]" multiple required class="mt-1 w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600">Description (optional)</label>
                                <input type="text" name="description" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm" placeholder="e.g. Lecture slides for Chapter 3">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">Upload</button>
                        </form>
                    </div>

                    {{-- Link Form --}}
                    <div x-show="showLink" x-cloak x-transition class="px-5 py-4 border-t border-slate-100 bg-slate-50">
                        <form method="POST" action="{{ route('tenant.materials.store-link', [$tenant->slug, $course]) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="week_number" value="{{ $week }}">
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Title</label>
                                    <input type="text" name="title" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm" placeholder="e.g. Lecture Recording - Week {{ $week }}">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600">URL</label>
                                    <input type="url" name="url" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm" placeholder="https://youtube.com/watch?v=...">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600">Description (optional)</label>
                                <input type="text" name="description" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm" placeholder="Brief description of the resource">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition">Add Link</button>
                        </form>
                    </div>
                </div>
            </div>
        @endfor
    </div>
</x-tenant-layout>
