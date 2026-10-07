@php
    $input = 'w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5';
    $clock = fn (int $s) => \App\Http\Controllers\Tenant\EpisodeContentController::clock($s);
    // After a failed submit, only the form that was sent gets its input back
    $refill = fn (string $form, string $key, $fallback) => old('form') === $form ? old($key, $fallback) : $fallback;
@endphp
<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.episodes.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">EP {{ $episode->episode_number }} · {{ $episode->title }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} · scenes, Quick Checks and captions</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 text-sm rounded-xl">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $aud = $report['audience'];
        $tz = $tenant->timezone ?: config('app.timezone');
        $statusLabel = ['not_started' => 'Not started', 'watching' => 'Watching', 'finished' => 'Finished'];
        $statusTone = ['not_started' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'watching' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'finished' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'];
    @endphp
    <section class="{{ $card }} mb-6 space-y-5" aria-labelledby="watching-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 id="watching-heading" class="text-sm font-semibold text-slate-900 dark:text-white">How your students watched</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Active students in {{ $sectionId ? collect($sections)->firstWhere('id', $sectionId)['name'] : 'the sections you teach' }}.
                </p>
            </div>
            @if($episode->isAvailable())
                <form method="POST" action="{{ route('tenant.episodes.remind', [$tenant->slug, $course, $episode]) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @if($sectionId)<input type="hidden" name="section_id" value="{{ $sectionId }}">@endif
                    <label for="remind_audience" class="sr-only">Who to remind</label>
                    <select id="remind_audience" name="audience" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white">
                        <option value="not_started">Not started ({{ $aud['not_started'] }})</option>
                        <option value="not_finished">Not finished ({{ $aud['students'] - $aud['finished'] }})</option>
                    </select>
                    @php $nextReminder = $report['reminder']['available_at'] ? \Illuminate\Support\Carbon::parse($report['reminder']['available_at']) : null; @endphp
                    <button type="submit" @disabled($nextReminder) class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium rounded-xl">
                        {{ $nextReminder ? 'Next reminder after '.$nextReminder->copy()->timezone($tz)->format('g:i A') : 'Send reminder' }}
                    </button>
                </form>
            @endif
        </div>

        @if(count($sections) > 1)
            @php
                $pill = 'px-3 py-1.5 rounded-full text-xs font-semibold border transition';
                $pillOn = 'bg-indigo-600 border-indigo-600 text-white';
                $pillOff = 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700';
            @endphp
            <nav class="flex flex-wrap gap-2" aria-label="Filter by section">
                <a href="{{ route('tenant.episodes.show', [$tenant->slug, $course, $episode]) }}" @if(! $sectionId) aria-current="true" @endif class="{{ $pill }} {{ $sectionId ? $pillOff : $pillOn }}">
                    All sections <span class="tabular-nums opacity-80">{{ collect($sections)->sum('students') }}</span>
                </a>
                @foreach($sections as $option)
                    <a href="{{ route('tenant.episodes.show', [$tenant->slug, $course, $episode, 'section_id' => $option['id']]) }}" @if($sectionId === $option['id']) aria-current="true" @endif class="{{ $pill }} {{ $sectionId === $option['id'] ? $pillOn : $pillOff }}">
                        {{ $option['name'] }} <span class="tabular-nums opacity-80">{{ $option['students'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach([
                ['Started', $aud['started'].' / '.$aud['students']],
                ['Finished', $aud['finished']],
                ['Avg watched', $aud['average_watched_percent'].'%'],
                ['Not started', $aud['not_started']],
            ] as [$k, $v])
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $k }}</dt>
                    <dd class="text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $v }}</dd>
                </div>
            @endforeach
        </dl>

        @if(count($report['scenes']))
            <div>
                <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Still watching, by scene <span class="font-normal text-slate-500 dark:text-slate-400">· share of starters who reached it</span></h4>
                <div class="space-y-1">
                    @foreach($report['scenes'] as $scene)
                        <div class="grid grid-cols-[3rem_1fr_3rem_4.5rem] items-center gap-2 text-xs tabular-nums">
                            <span class="font-semibold text-slate-500 dark:text-slate-400" title="{{ $scene['title'] }}">{{ $scene['code'] ?? $clock($scene['start_seconds']) }}</span>
                            <span class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden"><span class="block h-full rounded-full bg-teal-500" style="width: {{ $scene['reached_percent'] }}%"></span></span>
                            <span class="text-right text-slate-700 dark:text-slate-200">{{ $scene['reached_percent'] }}%</span>
                            <span class="text-right text-slate-500 dark:text-slate-400">{{ $scene['rewinds'] }} {{ Str::plural('rewind', $scene['rewinds']) }}</span>
                        </div>
                    @endforeach
                </div>
                @if($report['most_rewound_scene'])
                    <p class="mt-3 px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-xs text-amber-800 dark:text-amber-300">
                        <strong>{{ $report['most_rewound_scene']['code'] }} "{{ $report['most_rewound_scene']['title'] }}"</strong> was rewound {{ $report['most_rewound_scene']['rewinds_per_viewer'] }} times per viewer. Worth a few minutes in the next lecture.
                    </p>
                @endif
            </div>
        @endif

        @if(count($report['students']))
            <details @if($sectionId) open @endif>
                <summary class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300">Students ({{ count($report['students']) }})</summary>
                <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($report['students'] as $row)
                        <li class="flex items-center justify-between gap-3 py-1.5 text-sm">
                            <span class="min-w-0 text-slate-800 dark:text-slate-200">
                                {{ $row['name'] }}
                                @if($row['section_name'] && count($sections) > 1)<span class="ml-1 text-xs text-slate-500 dark:text-slate-400">{{ $row['section_name'] }}</span>@endif
                            </span>
                            <span class="flex items-center gap-2 text-xs tabular-nums">
                                @if($row['status'] === 'watching')<span class="text-slate-500 dark:text-slate-400">{{ $row['watched_percent'] }}%</span>@endif
                                <span class="px-2 py-0.5 rounded-full font-semibold {{ $statusTone[$row['status']] }}">{{ $statusLabel[$row['status']] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-data="episodePreview('{{ $episode->isYouTube() ? $episode->youtube_video_id : '' }}')" x-init="init()">
        <div class="space-y-6">
            <div class="{{ $card }} space-y-3">
                @if($episode->isYouTube())
                    <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-black">
                        <div id="yt-preview" class="absolute inset-0 w-full h-full"></div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Plays from YouTube ·
                        <a href="{{ \App\Services\Episodes\YouTubeLink::watchUrl($episode->youtube_video_id) }}" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">Open on YouTube</a>
                    </p>
                @else
                    <video x-ref="player" controls preload="metadata" class="w-full rounded-xl bg-black" src="{{ \App\Services\Episodes\EpisodeMedia::streamUrl($episode) }}"></video>
                @endif
                @if($episode->scenes->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($episode->scenes as $scene)
                            <button type="button" @click="seek({{ $scene->start_seconds }})" class="px-2 py-1 text-xs rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                <span class="font-semibold tabular-nums">{{ $clock($scene->start_seconds) }}</span> {{ $scene->code ?? '' }}
                            </button>
                        @endforeach
                    </div>
                @endif
                @if($episode->duration_seconds && $episode->scenes->isNotEmpty() && $episode->scenes->last()->start_seconds > $episode->duration_seconds)
                    <p class="text-xs text-amber-700 dark:text-amber-400">The last scene starts after the end of the video ({{ $clock($episode->duration_seconds) }}). Check the storyboard timings against this cut.</p>
                @endif
            </div>

            <form method="POST" action="{{ route('tenant.episodes.scenes', [$tenant->slug, $course, $episode]) }}" class="{{ $card }} space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Scenes</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Paste the storyboard table, or write one scene per line: <code>S01 0:00 Meet Titis</code>. Students see these as chapter marks. Saving replaces the list.</p>
                </div>
                <label for="scenes" class="sr-only">Scenes</label>
                <textarea id="scenes" name="scenes" rows="10" class="{{ $input }} font-mono text-xs">{{ old('scenes', $scenesText) }}</textarea>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">Save scenes</button>
            </form>
        </div>

        <div class="space-y-6">
            <div class="{{ $card }} space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Quick Checks</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">The video pauses at this time and the student answers before carrying on.</p>
                </div>

                @foreach($episode->checks as $check)
                    @php $correctIndex = $check->options->search(fn ($o) => $o->is_correct); @endphp
                    <div x-data="{ editing: false }" class="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 tabular-nums">{{ $clock($check->at_seconds) }}</p>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $check->prompt }}</p>
                                <ul class="mt-1 text-xs text-slate-600 dark:text-slate-300 space-y-0.5">
                                    @foreach($check->options as $option)
                                        <li class="{{ $option->is_correct ? 'font-semibold text-emerald-700 dark:text-emerald-400' : '' }}">{{ $option->is_correct ? '✓' : '·' }} {{ $option->label }}</li>
                                    @endforeach
                                </ul>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $check->answers_count }} answered
                                    @if($check->answers_count) · {{ round($check->first_try_correct_count / $check->answers_count * 100) }}% right first try @endif
                                </p>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <button type="button" @click="editing = !editing" class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">Edit</button>
                                <form method="POST" action="{{ route('tenant.episodes.checks.destroy', [$tenant->slug, $course, $episode, $check]) }}" x-data="{ sure: false }" @submit="if (!sure) { $event.preventDefault(); sure = true }">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" x-text="sure ? 'Click to confirm' : 'Delete'">Delete</button>
                                </form>
                            </div>
                        </div>
                        <div x-show="editing" x-cloak class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                            @include('tenant.episodes._check-form', [
                                'action' => route('tenant.episodes.checks.update', [$tenant->slug, $course, $episode, $check]),
                                'method' => 'PATCH',
                                'prefix' => 'check'.$check->id,
                                'at' => $clock($check->at_seconds),
                                'prompt' => $check->prompt,
                                'explanation' => $check->explanation,
                                'options' => $check->options->pluck('label')->all(),
                                'correct' => $correctIndex === false ? null : $correctIndex,
                                'submit' => 'Save Quick Check',
                            ])
                        </div>
                    </div>
                @endforeach

                @foreach($suggestions as $i => $suggestion)
                    @php $form = 'draft'.$suggestion['at_seconds']; @endphp
                    <div class="rounded-xl border border-dashed border-amber-300 dark:border-amber-700 bg-amber-50/40 dark:bg-amber-900/10 p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    From the storyboard · <span class="tabular-nums">{{ $clock($suggestion['at_seconds']) }}</span>
                                    @if(count($suggestions) > 1)<span class="font-normal text-slate-500 dark:text-slate-400">· {{ $i + 1 }} of {{ count($suggestions) }}</span>@endif
                                </h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Check the question, options and answer as students should read them, then add it.</p>
                            </div>
                            <form method="POST" action="{{ route('tenant.episodes.check-suggestions.dismiss', [$tenant->slug, $course, $episode, $suggestion['at_seconds']]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">Dismiss</button>
                            </form>
                        </div>
                        @include('tenant.episodes._check-form', [
                            'action' => route('tenant.episodes.checks.store', [$tenant->slug, $course, $episode]),
                            'method' => 'POST',
                            'prefix' => $form,
                            'at' => $refill($form, 'at', $clock($suggestion['at_seconds'])),
                            'prompt' => $refill($form, 'prompt', $suggestion['prompt'] ?? ''),
                            'explanation' => $refill($form, 'explanation', ''),
                            'options' => $refill($form, 'options', $suggestion['options']),
                            'correct' => $refill($form, 'correct', $suggestion['correct_index']),
                            'submit' => 'Add Quick Check',
                        ])
                    </div>
                @endforeach

                <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-4">
                    <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-3">Add a Quick Check</h4>
                    @include('tenant.episodes._check-form', [
                        'action' => route('tenant.episodes.checks.store', [$tenant->slug, $course, $episode]),
                        'method' => 'POST',
                        'prefix' => 'newcheck',
                        'at' => $refill('newcheck', 'at', ''),
                        'prompt' => $refill('newcheck', 'prompt', ''),
                        'explanation' => $refill('newcheck', 'explanation', ''),
                        'options' => $refill('newcheck', 'options', []),
                        'correct' => $refill('newcheck', 'correct', null),
                        'submit' => 'Add Quick Check',
                    ])
                </div>
            </div>

            <div class="{{ $card }} space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Captions</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">One WebVTT (.vtt) or SubRip (.srt) file per language. Uploading again replaces it.</p>
                </div>
                @foreach($episode->captions as $caption)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-700 dark:text-slate-200">{{ $caption->label() }}</span>
                        <form method="POST" action="{{ route('tenant.episodes.captions.destroy', [$tenant->slug, $course, $episode, $caption]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">Remove</button>
                        </form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('tenant.episodes.captions.store', [$tenant->slug, $course, $episode]) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="caption_language" class="{{ $label }}">Language</label>
                        <select id="caption_language" name="language" class="{{ $input }}">
                            @foreach($languages as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-48">
                        <label for="caption_file" class="{{ $label }}">File</label>
                        <input id="caption_file" type="file" name="file" required accept=".vtt,.srt,text/vtt" class="block w-full text-xs text-slate-500 dark:text-slate-400">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">Upload</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        function episodePreview(youtubeId) {
            return {
                yt: null,
                init() {
                    if (!youtubeId) return;
                    const make = () => {
                        this.yt = new YT.Player('yt-preview', {
                            host: 'https://www.youtube-nocookie.com',
                            videoId: youtubeId,
                            width: '100%',
                            height: '100%',
                            playerVars: { rel: 0, playsinline: 1 },
                        });
                    };
                    if (window.YT && window.YT.Player) { make(); return; }
                    const previous = window.onYouTubeIframeAPIReady;
                    window.onYouTubeIframeAPIReady = () => { if (previous) previous(); make(); };
                    const tag = document.createElement('script');
                    tag.src = 'https://www.youtube.com/iframe_api';
                    document.head.appendChild(tag);
                },
                seek(s) {
                    if (this.yt && this.yt.seekTo) { this.yt.seekTo(s, true); this.yt.playVideo(); return; }
                    const v = this.$refs.player;
                    if (v) { v.currentTime = s; v.play(); }
                },
            };
        }
    </script>
</x-tenant-layout>
