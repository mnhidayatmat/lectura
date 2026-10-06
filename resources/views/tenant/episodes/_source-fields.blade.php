@php
    $input = 'w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $maxVideoMb = $maxVideoMb ?? (int) config('lectura.episodes.max_video_mb');
@endphp
<fieldset>
    <legend class="{{ $label }}">Video source</legend>
    <div class="inline-flex rounded-xl border border-slate-300 dark:border-slate-600 p-0.5 text-sm">
        <label class="px-3 py-1.5 rounded-lg cursor-pointer" :class="source === 'youtube' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300'">
            <input type="radio" name="source" value="youtube" x-model="source" class="sr-only"> YouTube link
        </label>
        <label class="px-3 py-1.5 rounded-lg cursor-pointer" :class="source === 'upload' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300'">
            <input type="radio" name="source" value="upload" x-model="source" class="sr-only"> Upload a file
        </label>
    </div>
</fieldset>

<div x-show="source === 'youtube'" class="grid grid-cols-4 gap-3">
    <div class="col-span-3">
        <label for="{{ $prefix }}_youtube_url" class="{{ $label }}">YouTube link</label>
        <input id="{{ $prefix }}_youtube_url" name="youtube_url" value="{{ $youtubeUrl }}" :required="source === 'youtube'" maxlength="255" placeholder="https://youtu.be/…" class="{{ $input }}">
    </div>
    <div>
        <label for="{{ $prefix }}_length" class="{{ $label }}">Length</label>
        <input id="{{ $prefix }}_length" name="length" value="{{ $length }}" maxlength="10" placeholder="4:35" class="{{ $input }} tabular-nums">
    </div>
    <p class="col-span-4 text-xs text-slate-500 dark:text-slate-400">Set the video to Unlisted (or Public) with embedding allowed; Private videos won't play in the app. Length is optional, the app fills it in on first play.</p>
</div>

<div x-show="source === 'upload'" x-cloak>
    <label for="{{ $prefix }}_video" class="{{ $label }}">{{ $videoLabel }}</label>
    <input id="{{ $prefix }}_video" type="file" name="video" :required="source === 'upload' && {{ $videoRequired ? 'true' : 'false' }}" accept="video/mp4,video/quicktime,video/x-m4v" @change="read($event)" class="block w-full text-xs text-slate-500 dark:text-slate-400">
    <input type="hidden" name="duration_seconds" :value="duration">
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">MP4 (H.264), up to {{ $maxVideoMb }} MB. Export with "fast start" so playback begins before the whole file loads.</p>
    <p x-show="duration" x-cloak class="mt-1 text-xs text-slate-500 dark:text-slate-400">Length <span x-text="label"></span></p>
    <p x-show="tooBig" x-cloak class="mt-1 text-xs text-red-600 dark:text-red-400">This file is over {{ $maxVideoMb }} MB and will be rejected.</p>
</div>
