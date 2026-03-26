<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.files.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }} Files</h2>
                    <p class="text-sm text-slate-500">{{ $totalFiles }} files &middot; {{ number_format($totalSize / 1024 / 1024, 1) }} MB</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="grid lg:grid-cols-4 gap-6">
        {{-- Left: Folder tree --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">Folders</h3>
                    <div x-data="{ open: false }">
                        <button @click="open = !open" class="text-indigo-600 hover:text-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="mt-2">
                            <form method="POST" action="{{ route('tenant.files.create-folder', [app('current_tenant')->slug, $course]) }}" class="flex gap-1">
                                @csrf
                                <input type="text" name="name" placeholder="New folder" required class="flex-1 px-2 py-1 text-xs rounded border border-slate-300 focus:ring-1 focus:ring-indigo-500" />
                                <button class="px-2 py-1 bg-indigo-600 text-white text-xs rounded">Add</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="py-1">
                    @foreach($folders as $folder)
                        <a href="{{ route('tenant.files.manage', [app('current_tenant')->slug, $course, 'folder' => $folder->id]) }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50 transition {{ $selectedFolder && $selectedFolder->id === $folder->id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600' }}">
                            <svg class="w-4 h-4 flex-shrink-0 {{ $selectedFolder && $selectedFolder->id === $folder->id ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <span class="truncate">{{ $folder->name }}</span>
                            @php $fc = \App\Models\CourseFile::where('course_folder_id', $folder->id)->count(); @endphp
                            @if($fc > 0)
                                <span class="ml-auto text-xs text-slate-400">{{ $fc }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right: Files --}}
        <div class="lg:col-span-3 space-y-4">
            @if($selectedFolder)
                {{-- Folder header with actions --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <h3 class="font-semibold text-slate-900">{{ $selectedFolder->name }}</h3>
                    </div>
                    <div class="flex items-center gap-2" x-data="{ renaming: false }">
                        <template x-if="!renaming">
                            <div class="flex gap-2">
                                <button @click="renaming = true" class="text-xs text-slate-500 hover:text-indigo-600">Rename</button>
                                @if($files->isEmpty())
                                    <form method="POST" action="{{ route('tenant.files.delete-folder', [app('current_tenant')->slug, $course, $selectedFolder]) }}">
                                        @csrf @method('DELETE')
                                        <button onclick="return confirm('Delete this folder?')" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </template>
                        <template x-if="renaming">
                            <form method="POST" action="{{ route('tenant.files.rename-folder', [app('current_tenant')->slug, $course, $selectedFolder]) }}" class="flex gap-1">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $selectedFolder->name }}" class="px-2 py-1 text-xs rounded border border-slate-300 focus:ring-1 focus:ring-indigo-500" />
                                <button class="px-2 py-1 bg-indigo-600 text-white text-xs rounded">Save</button>
                                <button type="button" @click="renaming = false" class="text-xs text-slate-400">Cancel</button>
                            </form>
                        </template>
                    </div>
                </div>

                {{-- Upload --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <form method="POST" action="{{ route('tenant.files.upload', [app('current_tenant')->slug, $course]) }}" enctype="multipart/form-data" class="p-4">
                        @csrf
                        <input type="hidden" name="folder_id" value="{{ $selectedFolder->id }}">
                        <div class="flex items-center gap-3">
                            <input type="file" name="files[]" multiple required class="flex-1 text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100" />
                            <input type="text" name="tags" placeholder="Tags (e.g. week:3, clo:CLO1)" class="w-64 px-3 py-2 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-indigo-500 placeholder-slate-400" />
                            <button class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">Upload</button>
                        </div>
                    </form>
                </div>

                {{-- File list --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    @if($files->isEmpty())
                        <div class="p-10 text-center">
                            <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </div>
                            <p class="text-sm text-slate-500">No files in this folder</p>
                            <p class="text-xs text-slate-400 mt-1">Upload files using the form above</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                                    <th class="text-left px-6 py-3 font-medium text-slate-500">File</th>
                                    <th class="text-left px-6 py-3 font-medium text-slate-500">Tags</th>
                                    <th class="text-right px-6 py-3 font-medium text-slate-500">Size</th>
                                    <th class="text-right px-6 py-3 font-medium text-slate-500">Uploaded</th>
                                    <th class="text-right px-6 py-3 font-medium text-slate-500"></th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($files as $file)
                                        <tr class="hover:bg-slate-50/50 group">
                                            <td class="px-6 py-3">
                                                <div class="flex items-center gap-3">
                                                    @php
                                                        $icon = match(true) {
                                                            str_contains($file->file_type, 'pdf') => 'text-red-500',
                                                            str_contains($file->file_type, 'image') => 'text-blue-500',
                                                            str_contains($file->file_type, 'word') || str_contains($file->file_type, 'doc') => 'text-indigo-500',
                                                            default => 'text-slate-400',
                                                        };
                                                    @endphp
                                                    <svg class="w-5 h-5 {{ $icon }} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    <span class="font-medium text-slate-900 truncate max-w-[200px]">{{ $file->file_name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($file->tags as $tag)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-violet-50 text-violet-700">{{ $tag->tag_type }}:{{ $tag->tag_value }}</span>
                                                    @endforeach
                                                    <form method="POST" action="{{ route('tenant.files.add-tag', [app('current_tenant')->slug, $course, $file]) }}" class="inline-flex" x-data="{ show: false }">
                                                        @csrf
                                                        <button type="button" x-show="!show" @click="show = true" class="text-[10px] text-violet-500 hover:text-violet-700">+tag</button>
                                                        <div x-show="show" x-cloak class="flex gap-1">
                                                            <select name="tag_type" class="text-[10px] px-1 py-0.5 rounded border border-slate-300">
                                                                <option value="week">week</option>
                                                                <option value="clo">clo</option>
                                                                <option value="topic">topic</option>
                                                                <option value="evidence_type">evidence</option>
                                                            </select>
                                                            <input type="text" name="tag_value" placeholder="value" class="w-16 text-[10px] px-1 py-0.5 rounded border border-slate-300" />
                                                            <button type="submit" class="text-[10px] text-indigo-600">Add</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3 text-right text-xs text-slate-400">{{ number_format($file->file_size_bytes / 1024, 1) }} KB</td>
                                            <td class="px-6 py-3 text-right text-xs text-slate-400">{{ $file->created_at->format('d M Y') }}</td>
                                            <td class="px-6 py-3 text-right">
                                                <form method="POST" action="{{ route('tenant.files.delete-file', [app('current_tenant')->slug, $course, $file]) }}" class="opacity-0 group-hover:opacity-100 transition">
                                                    @csrf @method('DELETE')
                                                    <button onclick="return confirm('Delete this file?')" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
                    <p class="text-sm text-slate-500">Select a folder from the left to view and upload files.</p>
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
