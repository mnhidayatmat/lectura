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
