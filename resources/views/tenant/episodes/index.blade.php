@php
    $tz = $tenant->timezone ?: config('app.timezone');
    $input = 'w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $fmt = fn (?int $s) => $s ? sprintf('%d:%02d', intdiv($s, 60), $s % 60) : '—';
@endphp
<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.materials.manage', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $course->code }} — Episodes</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Animated videos students watch in the Lectura Go app</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 text-sm rounded-xl">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: series + upload --}}
        <div class="space-y-6 lg:col-span-1">
            <form method="POST" action="{{ route('tenant.episodes.series', [$tenant->slug, $course]) }}" enctype="multipart/form-data"
                  class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Series</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">The show students see. One per course.</p>
                </div>
                @if($series?->cover_path)
                    <img src="{{ \App\Services\Episodes\EpisodeMedia::coverUrl($series) }}" alt="" class="w-full aspect-video object-cover rounded-xl">
                @endif
                <div>
                    <label for="series_title" class="{{ $label }}">Title</label>
                    <input id="series_title" name="title" required maxlength="255" value="{{ old('title', $series?->title ?? $course->title) }}" class="{{ $input }}" placeholder="Titis: A Piping Story">
                </div>
                <div>
                    <label for="series_tagline" class="{{ $label }}">Tagline</label>
                    <input id="series_tagline" name="tagline" maxlength="255" value="{{ old('tagline', $series?->tagline) }}" class="{{ $input }}" placeholder="One drop of oil, fourteen weeks of piping.">
                </div>
                <div>
                    <label for="series_description" class="{{ $label }}">Description</label>
                    <textarea id="series_description" name="description" rows="3" maxlength="2000" class="{{ $input }}">{{ old('description', $series?->description) }}</textarea>
                </div>
                <div>
                    <label for="series_cover" class="{{ $label }}">Cover image (16:9)</label>
                    <input id="series_cover" type="file" name="cover" accept="image/*" class="block w-full text-xs text-slate-500 dark:text-slate-400">
                    @if($series?->cover_path)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"><input type="checkbox" name="remove_cover" value="1" class="rounded"> Remove current cover</label>
                    @endif
                </div>
                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Save series</button>
            </form>

            <form method="POST" action="{{ route('tenant.episodes.store', [$tenant->slug, $course]) }}" enctype="multipart/form-data"
                  x-data="episodeUpload('{{ old('source', 'youtube') }}')" @submit="uploading = true"
                  class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 space-y-3">
                @csrf
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add an episode</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Link a YouTube video, or upload the video file.</p>
                </div>
                @include('tenant.episodes._source-fields', ['prefix' => 'new', 'youtubeUrl' => old('youtube_url'), 'length' => old('length'), 'videoRequired' => true, 'videoLabel' => 'Video file'])
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="new_number" class="{{ $label }}">Episode</label>
                        <input id="new_number" type="number" name="episode_number" min="1" max="999" required value="{{ old('episode_number', $nextNumber) }}" class="{{ $input }}">
                    </div>
                    <div class="col-span-2">
                        <label for="new_title" class="{{ $label }}">Title</label>
                        <input id="new_title" name="title" required maxlength="255" value="{{ old('title') }}" x-ref="title" class="{{ $input }}" placeholder="Titis Leaves Home">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="new_week" class="{{ $label }}">Week</label>
                        <input id="new_week" type="number" name="week_number" min="1" max="52" value="{{ old('week_number') }}" class="{{ $input }}">
                    </div>
                    <div class="col-span-2">
                        <label for="new_topic" class="{{ $label }}">Topic</label>
                        <select id="new_topic" name="course_topic_id" class="{{ $input }}">
                            <option value="">Match by week</option>
                            @foreach($topics as $topic)
                                <option value="{{ $topic->id }}" @selected(old('course_topic_id') == $topic->id)>W{{ $topic->week_number }} · {{ $topic->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="new_synopsis" class="{{ $label }}">Synopsis</label>
                    <textarea id="new_synopsis" name="synopsis" rows="3" maxlength="2000" class="{{ $input }}" placeholder="One or two sentences students see under the title.">{{ old('synopsis') }}</textarea>
                </div>
                <div>
                    <label for="new_poster" class="{{ $label }}">Poster still (16:9, optional)</label>
                    <input id="new_poster" type="file" name="poster" accept="image/*" class="block w-full text-xs text-slate-500 dark:text-slate-400">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="new_status" class="{{ $label }}">Visibility</label>
                        <select id="new_status" name="status" class="{{ $input }}">
                            <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft (hidden)</option>
                            <option value="locked" @selected(old('status') === 'locked')>Locked (coming soon)</option>
                            <option value="published" @selected(old('status') === 'published')>Published</option>
                        </select>
                    </div>
                    <div>
                        <label for="new_publish_at" class="{{ $label }}">Release at (optional)</label>
                        <input id="new_publish_at" type="datetime-local" name="publish_at" value="{{ old('publish_at') }}" class="{{ $input }}">
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Locked shows students the episode as "Coming soon" until you set a release time; it unlocks then. Published with a future release time works the same way.</p>
                <div>
                    <label for="new_required_by" class="{{ $label }}">Watch before (optional)</label>
                    <input id="new_required_by" type="datetime-local" name="required_by" value="{{ old('required_by') }}" class="{{ $input }}">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Usually the lecture that builds on it. Students see the deadline on the episode.</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="hidden" name="notify_students" value="0">
                    <input type="checkbox" name="notify_students" value="1" class="rounded" @checked(old('notify_students', '1') === '1')>
                    Notify students when it's released
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="hidden" name="allow_download" value="0">
                    <input type="checkbox" name="allow_download" value="1" class="rounded" @checked(old('allow_download', '1') === '1') :disabled="source === 'youtube'">
                    <span x-text="source === 'youtube' ? 'YouTube episodes can only be watched online' : 'Let students download it to watch offline'">Let students download it to watch offline</span>
                </label>
                <button type="submit" :disabled="uploading || tooBig" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium rounded-xl transition">
                    <span x-show="!uploading">Add episode</span>
                    <span x-show="uploading" x-cloak x-text="source === 'upload' ? 'Uploading… keep this tab open' : 'Saving…'"></span>
                </button>
            </form>
        </div>

        {{-- Right: episode list --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="flex items-baseline justify-between">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $series?->title ?? 'No series yet' }}</h3>
                <p class="text-xs text-slate-400">{{ $episodes->count() }} {{ Str::plural('episode', $episodes->count()) }}</p>
            </div>

            @if($episodes->isEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No episodes yet</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Upload the first video. It stays a draft until you publish it.</p>
                </div>
            @endif

            @foreach($episodes as $episode)
                @php
                    $live = $episode->isAvailable();
                    $scheduled = $episode->isVisible() && ! $live && $episode->publish_at !== null;
                    $comingSoon = $episode->isVisible() && ! $live && $episode->publish_at === null;
                @endphp
                <div x-data="{ editing: false, preview: false }" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="p-4 flex gap-4">
                        <button type="button" @click="preview = !preview" class="relative w-36 shrink-0 aspect-video rounded-lg overflow-hidden bg-slate-800 group" aria-label="Preview episode {{ $episode->episode_number }}">
                            @if($poster = \App\Services\Episodes\EpisodeMedia::posterUrl($episode))
                                <img src="{{ $poster }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="absolute inset-0 bg-gradient-to-br from-teal-700 to-slate-900"></span>
                                <span class="absolute left-2 bottom-1 text-3xl font-extrabold text-white/90">{{ $episode->episode_number }}</span>
                            @endif
                            <span class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/30 transition">
                                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </button>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $episode->episode_number }}. {{ $episode->title }}</h4>
                                @if($live)
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Published</span>
                                @elseif($scheduled)
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Releases {{ $episode->publish_at->copy()->timezone($tz)->format('j M, g:i A') }}</span>
                                @elseif($comingSoon)
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">Locked · coming soon</span>
                                @else
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">Draft</span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $episode->week_number ? 'Week '.$episode->week_number : 'No week' }}
                                @if($episode->topic) · {{ $episode->topic->title }} @endif
                                · {{ $fmt($episode->duration_seconds) }}
                                @if($episode->isYouTube()) · YouTube @elseif($episode->video_size_bytes) · {{ number_format($episode->video_size_bytes / 1048576, 1) }} MB @endif
                            </p>
                            @if($episode->synopsis)
                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 line-clamp-2">{{ $episode->synopsis }}</p>
                            @endif
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $episode->progress_count }} started · {{ $episode->completed_count }} finished
                                @if($episode->required_by) · Watch before {{ $episode->required_by->copy()->timezone($tz)->format('D j M, g:i A') }} @endif
                            </p>
                            <a href="{{ route('tenant.episodes.show', [$tenant->slug, $course, $episode]) }}" class="inline-block mt-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $episode->scenes_count }} {{ Str::plural('scene', $episode->scenes_count) }} · {{ $episode->checks_count }} Quick {{ Str::plural('Check', $episode->checks_count) }} · {{ $episode->captions_count }} caption {{ Str::plural('track', $episode->captions_count) }} →
                            </a>
                        </div>
                        <div class="flex flex-col gap-1 shrink-0">
                            <button type="button" @click="editing = !editing" class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">Edit</button>
                            <form method="POST" action="{{ route('tenant.episodes.destroy', [$tenant->slug, $course, $episode]) }}" x-data="{ sure: false }" @submit="if (!sure) { $event.preventDefault(); sure = true }">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" x-text="sure ? 'Click to confirm' : 'Delete'">Delete</button>
                            </form>
                        </div>
                    </div>

                    <template x-if="preview">
                        <div class="px-4 pb-4">
                            @if($episode->isYouTube())
                                <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-black">
                                    <iframe class="absolute inset-0 w-full h-full" src="{{ \App\Services\Episodes\YouTubeLink::embedUrl($episode->youtube_video_id) }}" title="{{ $episode->title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                                </div>
                            @else
                                <video controls preload="metadata" class="w-full rounded-xl bg-black" src="{{ \App\Services\Episodes\EpisodeMedia::streamUrl($episode) }}"></video>
                            @endif
                        </div>
                    </template>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('tenant.episodes.update', [$tenant->slug, $course, $episode]) }}" enctype="multipart/form-data"
                          x-data="episodeUpload('{{ $episode->source ?? 'upload' }}')" @submit="uploading = true"
                          class="border-t border-slate-200 dark:border-slate-700 p-4 grid grid-cols-1 sm:grid-cols-6 gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="sm:col-span-1">
                            <label for="ep{{ $episode->id }}_number" class="{{ $label }}">Episode</label>
                            <input id="ep{{ $episode->id }}_number" type="number" name="episode_number" min="1" max="999" required value="{{ $episode->episode_number }}" class="{{ $input }}">
                        </div>
                        <div class="sm:col-span-5">
                            <label for="ep{{ $episode->id }}_title" class="{{ $label }}">Title</label>
                            <input id="ep{{ $episode->id }}_title" name="title" required maxlength="255" value="{{ $episode->title }}" class="{{ $input }}">
                        </div>
                        <div class="sm:col-span-1">
                            <label for="ep{{ $episode->id }}_week" class="{{ $label }}">Week</label>
                            <input id="ep{{ $episode->id }}_week" type="number" name="week_number" min="1" max="52" value="{{ $episode->week_number }}" class="{{ $input }}">
                        </div>
                        <div class="sm:col-span-5">
                            <label for="ep{{ $episode->id }}_topic" class="{{ $label }}">Topic</label>
                            <select id="ep{{ $episode->id }}_topic" name="course_topic_id" class="{{ $input }}">
                                <option value="">Match by week</option>
                                @foreach($topics as $topic)
                                    <option value="{{ $topic->id }}" @selected($episode->course_topic_id === $topic->id)>W{{ $topic->week_number }} · {{ $topic->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-6">
                            <label for="ep{{ $episode->id }}_synopsis" class="{{ $label }}">Synopsis</label>
                            <textarea id="ep{{ $episode->id }}_synopsis" name="synopsis" rows="2" maxlength="2000" class="{{ $input }}">{{ $episode->synopsis }}</textarea>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="ep{{ $episode->id }}_status" class="{{ $label }}">Visibility</label>
                            <select id="ep{{ $episode->id }}_status" name="status" class="{{ $input }}">
                                <option value="draft" @selected($episode->status === 'draft')>Draft (hidden)</option>
                                <option value="locked" @selected($episode->status === 'locked')>Locked (coming soon)</option>
                                <option value="published" @selected($episode->status === 'published')>Published</option>
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="ep{{ $episode->id }}_publish_at" class="{{ $label }}">Release at (optional)</label>
                            <input id="ep{{ $episode->id }}_publish_at" type="datetime-local" name="publish_at" value="{{ $episode->publish_at?->copy()->timezone($tz)->format('Y-m-d\TH:i') }}" class="{{ $input }}">
                        </div>
                        <div class="sm:col-span-3">
                            <label for="ep{{ $episode->id }}_required_by" class="{{ $label }}">Watch before (optional)</label>
                            <input id="ep{{ $episode->id }}_required_by" type="datetime-local" name="required_by" value="{{ $episode->required_by?->copy()->timezone($tz)->format('Y-m-d\TH:i') }}" class="{{ $input }}">
                        </div>
                        <div class="sm:col-span-3 flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="hidden" name="notify_students" value="0">
                                <input type="checkbox" name="notify_students" value="1" class="rounded" @checked($episode->notify_students) @disabled($episode->announced_at)>
                                {{ $episode->announced_at ? 'Students were notified '.$episode->announced_at->copy()->timezone($tz)->format('j M') : "Notify students when it's released" }}
                            </label>
                        </div>
                        <div class="sm:col-span-6">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="hidden" name="allow_download" value="0">
                                <input type="checkbox" name="allow_download" value="1" class="rounded" @checked($episode->allow_download) @disabled($episode->isYouTube())>
                                {{ $episode->isYouTube() ? 'YouTube episodes can only be watched online' : 'Let students download it to watch offline' }}
                            </label>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="ep{{ $episode->id }}_poster" class="{{ $label }}">Replace poster</label>
                            <input id="ep{{ $episode->id }}_poster" type="file" name="poster" accept="image/*" class="block w-full text-xs text-slate-500 dark:text-slate-400">
                            @if($episode->poster_path)
                                <label class="mt-1 inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"><input type="checkbox" name="remove_poster" value="1" class="rounded"> Remove poster</label>
                            @endif
                        </div>
                        <div class="sm:col-span-6 space-y-3">
                            @include('tenant.episodes._source-fields', [
                                'prefix' => 'ep'.$episode->id,
                                'youtubeUrl' => $episode->youtube_video_id ? \App\Services\Episodes\YouTubeLink::watchUrl($episode->youtube_video_id) : '',
                                'length' => $episode->isYouTube() && $episode->duration_seconds ? $fmt($episode->duration_seconds) : '',
                                'videoRequired' => ! $episode->video_path,
                                'videoLabel' => $episode->video_path ? 'Replace video file' : 'Video file',
                            ])
                        </div>
                        <div class="sm:col-span-6 flex justify-end gap-2">
                            <button type="button" @click="editing = false" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700">Cancel</button>
                            <button type="submit" :disabled="uploading || tooBig" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium rounded-xl">
                                <span x-show="!uploading">Save episode</span>
                                <span x-show="uploading" x-cloak>Saving…</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        function episodeUpload(source) {
            return {
                source: source || 'youtube',
                duration: '',
                label: '',
                tooBig: false,
                uploading: false,
                read(event) {
                    const file = event.target.files[0];
                    this.duration = '';
                    this.label = '';
                    this.tooBig = !!file && file.size > {{ $maxVideoMb }} * 1024 * 1024;
                    if (!file) return;
                    const video = document.createElement('video');
                    video.preload = 'metadata';
                    video.onloadedmetadata = () => {
                        URL.revokeObjectURL(video.src);
                        if (!isFinite(video.duration)) return;
                        const s = Math.round(video.duration);
                        this.duration = s;
                        this.label = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
                    };
                    video.src = URL.createObjectURL(file);
                },
            };
        }
    </script>
</x-tenant-layout>
