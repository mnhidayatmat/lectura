<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.materials.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $course->code }} — Course Materials</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="{ addingSection: false, newTitle: '' }">

        {{-- Top bar --}}
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Sections</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $sections->count() }} {{ Str::plural('section', $sections->count()) }}</p>
            </div>
            <button
                @click="addingSection = !addingSection; $nextTick(() => { if (addingSection) $refs.newTitleInput.focus() })"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Section
            </button>
        </div>

        {{-- Add section form --}}
        <div x-show="addingSection" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mb-5">
            <form method="POST" action="{{ route('tenant.materials.sections.store', [$tenant->slug, $course]) }}" class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-2xl px-5 py-4">
                @csrf
                <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-400 uppercase tracking-wide mb-3">New Section</p>
                <div class="flex gap-3">
                    <input
                        x-ref="newTitleInput"
                        type="text"
                        name="title"
                        x-model="newTitle"
                        placeholder="e.g. Week 1: Introduction, Chapter 3, Midterm Review…"
                        required
                        class="flex-1 px-3 py-2 rounded-xl border border-indigo-300 dark:border-indigo-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Create</button>
                    <button type="button" @click="addingSection = false; newTitle = ''" class="px-4 py-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 text-sm rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">Cancel</button>
                </div>
            </form>
        </div>

        {{-- Empty state --}}
        @if($sections->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
                <div class="w-16 h-16 bg-slate-50 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-300 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No sections yet</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Create your first section to start organising course materials.</p>
                <button @click="addingSection = true; $nextTick(() => $refs.newTitleInput.focus())" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add First Section
                </button>
            </div>
        @endif

        {{-- Section cards --}}
        <div class="space-y-3">
            @foreach($sections as $section)
                <div
                    x-data="{
                        open: {{ $section->files->isNotEmpty() ? 'true' : 'false' }},
                        renaming: false,
                        editTitle: '{{ addslashes($section->title) }}',
                        showUpload: false,
                        showLink: false,
                        saveRename() { this.$refs.renameForm.submit(); }
                    }"
                    class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">

                    {{-- Section header --}}
                    <div class="px-5 py-4 flex items-center gap-3">
                        {{-- Icon --}}
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0 cursor-pointer" @click="open = !open">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>

                        {{-- Title / Rename input --}}
                        <div class="flex-1 min-w-0">
                            <div x-show="!renaming" class="cursor-pointer" @click="open = !open">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section->title }}</h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ $section->files->count() }} {{ Str::plural('item', $section->files->count()) }}
                                    @if(!$section->is_visible)
                                        &middot; <span class="text-amber-500">Hidden from students</span>
                                    @endif
                                </p>
                            </div>
                            <div x-show="renaming" x-cloak @click.stop>
                                <form x-ref="renameForm" method="POST" action="{{ route('tenant.materials.sections.update', [$tenant->slug, $course, $section]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="title" :value="editTitle">
                                </form>
                                <input
                                    x-ref="titleInput"
                                    x-model="editTitle"
                                    @keydown.enter.prevent="saveRename()"
                                    @keydown.escape="renaming = false; editTitle = '{{ addslashes($section->title) }}'"
                                    class="w-full px-3 py-1.5 rounded-lg border border-indigo-400 bg-white dark:bg-slate-700 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <div class="flex gap-2 mt-1.5">
                                    <button @click="saveRename()" type="button" class="text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1 rounded-lg transition">Save</button>
                                    <button @click="renaming = false; editTitle = '{{ addslashes($section->title) }}'" type="button" class="text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition">Cancel</button>
                                </div>
                            </div>
                        </div>

                        {{-- Action buttons --}}
                        <div class="flex items-center gap-0.5 flex-shrink-0" @click.stop>
                            {{-- Move up --}}
                            @if(!$loop->first)
                                <form method="POST" action="{{ route('tenant.materials.sections.move', [$tenant->slug, $course, $section]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" title="Move up" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-center transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                </form>
                            @else
                                <div class="w-8 h-8"></div>
                            @endif

                            {{-- Move down --}}
                            @if(!$loop->last)
                                <form method="POST" action="{{ route('tenant.materials.sections.move', [$tenant->slug, $course, $section]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" title="Move down" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-center transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </form>
                            @else
                                <div class="w-8 h-8"></div>
                            @endif

                            {{-- Rename --}}
                            <button @click="renaming = !renaming; $nextTick(() => { if (renaming) $refs.titleInput.focus() })" title="Rename" class="w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 flex items-center justify-center transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>

                            {{-- Delete --}}
                            <form method="POST" action="{{ route('tenant.materials.sections.destroy', [$tenant->slug, $course, $section]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" title="Delete section" onclick="return confirm('Delete &quot;{{ addslashes($section->title) }}&quot; and all its materials? This cannot be undone.')" class="w-8 h-8 rounded-lg text-slate-400 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>

                            {{-- Chevron --}}
                            <button @click="open = !open" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-center transition">
                                <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Expanded content --}}
                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="border-t border-slate-100 dark:border-slate-700">

                        {{-- Materials list --}}
                        @if($section->files->isNotEmpty())
                            <div class="divide-y divide-slate-50 dark:divide-slate-700/50">
                                @foreach($section->files as $material)
                                    <div class="px-5 py-3 flex items-center justify-between gap-4 group hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if($material->isLink())
                                                <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                </div>
                                            @elseif($material->isDriveFile())
                                                <div class="w-9 h-9 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-green-600" viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/><path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/><path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 27h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg>
                                                </div>
                                            @elseif(str_contains($material->file_type ?? '', 'pdf'))
                                                <div class="w-9 h-9 rounded-lg bg-red-50 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                </div>
                                            @elseif(str_contains($material->file_type ?? '', 'image'))
                                                <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            @elseif(str_contains($material->file_type ?? '', 'presentation') || str_contains($material->file_name ?? '', '.pptx') || str_contains($material->file_name ?? '', '.ppt'))
                                                <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                </div>
                                            @else
                                                <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $material->file_name }}</p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    @if($material->isLink())
                                                        <span class="text-[10px] font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-1.5 py-0.5 rounded">Link</span>
                                                    @elseif($material->isDriveFile())
                                                        <span class="text-[10px] font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-1.5 py-0.5 rounded">Drive</span>
                                                        <span class="text-xs text-slate-400">{{ $material->formattedSize() }}</span>
                                                    @else
                                                        <span class="text-xs text-slate-400">{{ $material->formattedSize() }}</span>
                                                    @endif
                                                    @if($material->description)
                                                        <span class="text-slate-300 dark:text-slate-600">&middot;</span>
                                                        <span class="text-xs text-slate-400 truncate">{{ Str::limit($material->description, 60) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                            @if($material->isLink())
                                                <a href="{{ $material->url }}" target="_blank" title="Open link" class="p-2 rounded-lg text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            @elseif($material->isDriveFile())
                                                <a href="{{ $material->url }}" target="_blank" title="Open in Google Drive" class="p-2 rounded-lg text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            @else
                                                <a href="{{ route('tenant.materials.download', [$tenant->slug, $course, $material]) }}" title="Download" class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                </a>
                                            @endif
                                            <form method="POST" action="{{ route('tenant.materials.destroy', [$tenant->slug, $course, $material]) }}" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" onclick="return confirm('Remove this material?')" title="Delete" class="p-2 rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="px-5 py-8 text-center">
                                <p class="text-sm text-slate-400 dark:text-slate-500">No content yet — add files or links below.</p>
                            </div>
                        @endif

                        {{-- Add content buttons --}}
                        <div class="px-5 py-3 bg-slate-50/70 dark:bg-slate-700/30 border-t border-slate-100 dark:border-slate-700 flex items-center gap-2">
                            <button @click="showUpload = !showUpload; showLink = false" :class="showUpload ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:border-indigo-300 hover:text-indigo-600'" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Upload File
                            </button>
                            <button @click="showLink = !showLink; showUpload = false" :class="showLink ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:border-blue-300 hover:text-blue-600'" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                Add Link
                            </button>
                        </div>

                        {{-- Upload form --}}
                        <div x-show="showUpload" x-cloak x-transition class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/20">
                            <form method="POST" action="{{ route('tenant.materials.upload', [$tenant->slug, $course]) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <input type="hidden" name="material_section_id" value="{{ $section->id }}">
                                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Upload Files</p>
                                <div>
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Files <span class="text-slate-400">(max 25 MB each)</span></label>
                                    <input type="file" name="files[]" multiple required class="mt-1 w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-400">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Description <span class="text-slate-400">(optional)</span></label>
                                    <input type="text" name="description" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white" placeholder="e.g. Lecture slides for this topic">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">Upload</button>
                                    <button type="button" @click="showUpload = false" class="px-4 py-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 text-xs rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition">Cancel</button>
                                </div>
                            </form>
                        </div>

                        {{-- Link form --}}
                        <div x-show="showLink" x-cloak x-transition class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/20">
                            <form method="POST" action="{{ route('tenant.materials.store-link', [$tenant->slug, $course]) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="material_section_id" value="{{ $section->id }}">
                                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Add External Link</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Title</label>
                                        <input type="text" name="title" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white" placeholder="e.g. Lecture Recording">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">URL</label>
                                        <input type="url" name="url" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white" placeholder="https://youtube.com/watch?v=...">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Description <span class="text-slate-400">(optional)</span></label>
                                    <input type="text" name="description" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white" placeholder="Brief description of this resource">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition">Add Link</button>
                                    <button type="button" @click="showLink = false" class="px-4 py-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 text-xs rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition">Cancel</button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        {{-- Bottom add section button (when sections exist) --}}
        @if($sections->isNotEmpty())
            <div class="mt-4 text-center">
                <button @click="addingSection = true; $nextTick(() => { $refs.newTitleInput.focus(); window.scrollTo({ top: 0, behavior: 'smooth' }) })" class="inline-flex items-center gap-2 px-4 py-2 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-dashed border-slate-300 dark:border-slate-600 hover:border-indigo-400 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Another Section
                </button>
            </div>
        @endif

    </div>
</x-tenant-layout>
