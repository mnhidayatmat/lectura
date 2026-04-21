<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.portfolio.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $course->code }} Portfolio</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->title }} &middot; {{ $photos->count() }} photo(s)</p>
                </div>
            </div>
            <div class="flex items-center gap-2" x-data="{ showUpload: false, showBatch: false }">
                <button @click="showBatch = true" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium rounded-xl transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Batch Upload
                </button>
                <button @click="showUpload = true" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Add Photo
                </button>

                {{-- Single Upload Modal --}}
                <div x-show="showUpload" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" @keydown.escape.window="showUpload = false">
                    <div @click.outside="showUpload = false" class="bg-white dark:bg-[#242d3d] rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-[#354158] flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900 dark:text-white">Add Photo</h3>
                            <button @click="showUpload = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('tenant.portfolio.store', [app('current_tenant')->slug, $course]) }}" enctype="multipart/form-data" class="p-6 space-y-4" x-data="photoUpload()">
                            @csrf

                            {{-- Photo input: camera or file --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Photo *</label>
                                <div class="space-y-3">
                                    {{-- Preview --}}
                                    <div x-show="preview" class="relative rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800">
                                        <img :src="preview" class="w-full max-h-64 object-contain" />
                                        <button type="button" @click="clearPhoto()" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-black/50 hover:bg-black/70 flex items-center justify-center text-white">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    {{-- Capture/Upload buttons --}}
                                    <div x-show="!preview" class="grid grid-cols-2 gap-3">
                                        <label class="flex flex-col items-center justify-center gap-2 p-6 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500 transition bg-slate-50 dark:bg-slate-800">
                                            <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Take Photo</span>
                                            <input type="file" name="photo" accept="image/*" capture="environment" class="hidden" @change="onFileSelect($event)" />
                                        </label>
                                        <label class="flex flex-col items-center justify-center gap-2 p-6 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500 transition bg-slate-50 dark:bg-slate-800">
                                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span class="text-xs font-medium text-slate-600 dark:text-slate-300">From Gallery</span>
                                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFileSelect($event)" />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Category --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category *</label>
                                <select name="category" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                {{-- Section --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Section</label>
                                    <select name="section_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">All sections</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Week --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Week</label>
                                    <input type="number" name="week_number" min="1" max="20" placeholder="e.g. 3" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                            </div>

                            {{-- Caption --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Caption</label>
                                <input type="text" name="caption" maxlength="255" placeholder="Brief description of the activity..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>

                            {{-- Description --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
                                <textarea name="description" rows="2" maxlength="1000" placeholder="Detailed notes about this activity..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>

                            {{-- Date --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Date taken</label>
                                <input type="date" name="taken_at" value="{{ date('Y-m-d') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>

                            <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition">
                                Save to Portfolio
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Batch Upload Modal --}}
                <div x-show="showBatch" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" @keydown.escape.window="showBatch = false">
                    <div @click.outside="showBatch = false" class="bg-white dark:bg-[#242d3d] rounded-2xl shadow-xl max-w-lg w-full">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-[#354158] flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900 dark:text-white">Batch Upload Photos</h3>
                            <button @click="showBatch = false" class="text-slate-400 hover:text-slate-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('tenant.portfolio.store-batch', [app('current_tenant')->slug, $course]) }}" enctype="multipart/form-data" class="p-6 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Photos * (max 20)</label>
                                <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center" x-data="{ count: 0 }">
                                    <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <input type="file" name="photos[]" multiple required accept="image/jpeg,image/png,image/webp" @change="count = $event.target.files.length"
                                        class="text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700" />
                                    <p class="text-xs text-slate-400 mt-2" x-show="count > 0" x-text="count + ' file(s) selected'"></p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category *</label>
                                    <select name="category" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                                        @foreach($categories as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Section</label>
                                    <select name="section_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">All sections</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Week</label>
                                <input type="number" name="week_number" min="1" max="20" placeholder="e.g. 3" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition">
                                Upload All Photos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        @if($errors->any())
            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400">
                @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        {{-- Stats bar --}}
        @if($photos->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white dark:bg-[#242d3d] rounded-xl border border-slate-200 dark:border-[#354158] p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $photos->count() }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Total Photos</p>
                </div>
                <div class="bg-white dark:bg-[#242d3d] rounded-xl border border-slate-200 dark:border-[#354158] p-4 text-center">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $photosByCategory->count() }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Categories</p>
                </div>
                <div class="bg-white dark:bg-[#242d3d] rounded-xl border border-slate-200 dark:border-[#354158] p-4 text-center">
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $photosByWeek->count() }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Weeks Covered</p>
                </div>
                <div class="bg-white dark:bg-[#242d3d] rounded-xl border border-slate-200 dark:border-[#354158] p-4 text-center">
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ round($photos->sum('file_size_bytes') / 1048576, 1) }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">MB Used</p>
                </div>
            </div>
        @endif

        {{-- Photos by Category --}}
        @if($photos->isEmpty())
            <div class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-12 text-center">
                <svg class="w-16 h-16 text-slate-200 dark:text-slate-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p class="text-lg font-semibold text-slate-700 dark:text-slate-300 mb-1">No photos yet</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Start capturing your teaching activities to build your portfolio.</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">Click "Add Photo" above to snap a photo or upload from your gallery.</p>
            </div>
        @else
            @foreach($photosByCategory as $category => $categoryPhotos)
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 uppercase">{{ \App\Models\PortfolioPhoto::CATEGORIES[$category] ?? ucfirst($category) }}</span>
                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $categoryPhotos->count() }} photo(s)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        @foreach($categoryPhotos as $photo)
                            <div class="group relative aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800" x-data="{ showDetail: false, confirmDelete: false }">
                                <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->caption }}" loading="lazy"
                                     class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />

                                {{-- Hover overlay --}}
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <div class="absolute bottom-0 left-0 right-0 p-2.5">
                                        @if($photo->caption)
                                            <p class="text-xs text-white truncate">{{ $photo->caption }}</p>
                                        @endif
                                        <p class="text-[9px] text-white/60">
                                            {{ $photo->taken_at?->format('d M Y') }}
                                            @if($photo->week_number) &middot; W{{ $photo->week_number }} @endif
                                        </p>
                                    </div>
                                    {{-- Delete button --}}
                                    <button @click.stop="confirmDelete = true" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-red-600/80 hover:bg-red-600 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>

                                {{-- Click to open --}}
                                <button @click="showDetail = true" class="absolute inset-0 cursor-pointer"></button>

                                {{-- Lightbox --}}
                                <div x-show="showDetail" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
                                     @click.self="showDetail = false" @keydown.escape.window="showDetail = false" x-transition>
                                    <div class="bg-white dark:bg-[#242d3d] rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden" @click.stop>
                                        <div class="relative">
                                            <img src="{{ $photo->public_url }}" alt="{{ $photo->caption }}" class="w-full max-h-[60vh] object-contain bg-black" />
                                            <button @click="showDetail = false" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-black/50 hover:bg-black/70 flex items-center justify-center text-white transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="p-5 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 uppercase">{{ $photo->category_label }}</span>
                                                @if($photo->section)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $photo->section->name }}</span>
                                                @endif
                                                @if($photo->week_number)
                                                    <span class="text-xs text-slate-400 dark:text-slate-500">Week {{ $photo->week_number }}</span>
                                                @endif
                                            </div>
                                            @if($photo->caption)
                                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $photo->caption }}</p>
                                            @endif
                                            @if($photo->description)
                                                <p class="text-sm text-slate-600 dark:text-slate-300">{{ $photo->description }}</p>
                                            @endif
                                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $photo->taken_at?->format('d M Y, H:i') }} &middot; {{ number_format($photo->file_size_bytes / 1024, 0) }} KB</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Delete confirmation --}}
                                <div x-show="confirmDelete" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="confirmDelete = false">
                                    <div class="bg-white dark:bg-[#242d3d] rounded-2xl shadow-xl p-6 max-w-sm w-full mx-4" @click.stop>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Delete this photo?</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">This action cannot be undone.</p>
                                        <div class="flex justify-end gap-2">
                                            <button @click="confirmDelete = false" class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl">Cancel</button>
                                            <form method="POST" action="{{ route('tenant.portfolio.destroy', [app('current_tenant')->slug, $photo]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-xl">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @push('scripts')
    <script>
        function photoUpload() {
            return {
                preview: null,
                onFileSelect(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (ev) => { this.preview = ev.target.result; };
                    reader.readAsDataURL(file);
                },
                clearPhoto() {
                    this.preview = null;
                    // Reset all file inputs in the form
                    this.$el.closest('form').querySelectorAll('input[type="file"]').forEach(i => i.value = '');
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
