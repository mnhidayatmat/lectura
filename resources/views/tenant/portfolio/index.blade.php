<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Teaching Portfolio</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $totalPhotos }} photo(s) across {{ $courses->count() }} course(s)</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        {{-- Course Cards --}}
        <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">By Course</h3>
            @if($courses->isEmpty())
                <div class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-10 text-center">
                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="text-sm text-slate-500 dark:text-slate-400">No courses found. Create a course first to start building your portfolio.</p>
                </div>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($courses as $course)
                        <a href="{{ route('tenant.portfolio.course', [app('current_tenant')->slug, $course]) }}"
                           class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-5 hover:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-500/50 transition group">
                            <div class="flex items-start justify-between mb-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $course->portfolio_photos_count }}</span>
                            </div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ $course->code }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $course->title }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Category Filter --}}
        @if($totalPhotos > 0)
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">All Photos</h3>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('tenant.portfolio.index', app('current_tenant')->slug) }}"
                           class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ !request('category') ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">
                            All ({{ $totalPhotos }})
                        </a>
                        @foreach($categoryCounts as $cat => $count)
                            <a href="{{ route('tenant.portfolio.index', [app('current_tenant')->slug, 'category' => $cat]) }}"
                               class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ request('category') === $cat ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">
                                {{ \App\Models\PortfolioPhoto::CATEGORIES[$cat] ?? ucfirst($cat) }} ({{ $count }})
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Photo Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    @foreach($allPhotos as $photo)
                        <div class="group relative aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800" x-data="{ showDetail: false }">
                            <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->caption }}" loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <div class="absolute bottom-0 left-0 right-0 p-2.5">
                                    <p class="text-[10px] font-semibold text-white/80">{{ $photo->course->code }}</p>
                                    @if($photo->caption)
                                        <p class="text-xs text-white truncate">{{ $photo->caption }}</p>
                                    @endif
                                    <p class="text-[9px] text-white/60 mt-0.5">{{ $photo->taken_at?->format('d M Y') }}</p>
                                </div>
                            </div>
                            {{-- Click to open lightbox --}}
                            <button @click="showDetail = true" class="absolute inset-0 cursor-pointer"></button>

                            {{-- Lightbox modal --}}
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
                                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $photo->course->code }}</span>
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
                                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $photo->taken_at?->format('d M Y, H:i') }} &middot; {{ $photo->section?->name ?? 'No section' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">{{ $allPhotos->links() }}</div>
            </div>
        @endif
    </div>
</x-tenant-layout>
