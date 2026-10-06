<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Episode;
use App\Models\EpisodeCaption;
use App\Models\EpisodeCheck;
use App\Services\Episodes\EpisodeAnalytics;
use App\Services\Episodes\EpisodeMedia;
use App\Services\Episodes\EpisodeReminderSender;
use App\Services\Episodes\StoryboardParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * One episode's interactive layer: scene markers, Quick Checks and captions.
 */
class EpisodeContentController extends Controller
{
    use AuthorizesCourseAccess;

    public function show(string $tenantSlug, Course $course, Episode $episode, EpisodeAnalytics $analytics): View
    {
        $this->authorizeEpisode($course, $episode);

        $tenant = app('current_tenant');
        $episode->load([
            'scenes',
            'captions',
            'checks' => fn ($q) => $q->with('options')->withCount([
                'answers',
                'answers as first_try_correct_count' => fn ($q) => $q->where('first_is_correct', true),
            ]),
        ]);

        $scenesText = $episode->scenes
            ->map(fn ($scene) => trim(($scene->code ? $scene->code.' ' : '').self::clock($scene->start_seconds).' '.$scene->title))
            ->implode("\n");

        return view('tenant.episodes.show', [
            'tenant' => $tenant,
            'course' => $course,
            'episode' => $episode,
            'scenesText' => $scenesText,
            'suggestion' => session('check_suggestion'),
            'languages' => EpisodeCaption::LANGUAGES,
            'report' => $analytics->report($episode, EpisodeAnalytics::studentIds($this->lecturerSectionIds($course))),
        ]);
    }

    public function remind(Request $request, string $tenantSlug, Course $course, Episode $episode, EpisodeReminderSender $sender): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);

        $audience = $request->validate([
            'audience' => ['required', Rule::in(['not_started', 'not_finished'])],
        ])['audience'];

        try {
            $sent = $sender->send($episode, EpisodeAnalytics::studentIds($this->lecturerSectionIds($course)), $audience);
        } catch (RuntimeException $e) {
            return back()->withErrors(['audience' => $e->getMessage()]);
        }

        return back()->with('success', "Reminder sent to {$sent} ".str('student')->plural($sent).'.');
    }

    public function saveScenes(Request $request, string $tenantSlug, Course $course, Episode $episode, StoryboardParser $parser): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);

        $request->validate(['scenes' => ['nullable', 'string', 'max:50000']]);
        $parsed = $parser->parse((string) $request->input('scenes', ''));

        if (trim((string) $request->input('scenes')) !== '' && $parsed['scenes'] === []) {
            throw ValidationException::withMessages([
                'scenes' => 'No scenes found. Paste the storyboard table, or one scene per line such as "S01 0:00 Meet Titis".',
            ]);
        }

        DB::transaction(function () use ($episode, $parsed) {
            $episode->scenes()->delete();
            foreach ($parsed['scenes'] as $i => $scene) {
                $episode->scenes()->create([...$scene, 'sort_order' => $i]);
            }
        });

        $redirect = back()->with('success', count($parsed['scenes']).' '.str('scene')->plural(count($parsed['scenes'])).' saved.');

        // A storyboard's Quick Check row pre-fills the new-check form; the lecturer confirms it.
        if ($parsed['check'] !== null && ! $episode->checks()->exists()) {
            $redirect->with('check_suggestion', $parsed['check']);
        }

        return $redirect;
    }

    public function storeCheck(Request $request, string $tenantSlug, Course $course, Episode $episode): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);
        [$data, $options, $correct] = $this->validatedCheck($request);

        DB::transaction(function () use ($episode, $data, $options, $correct) {
            $check = $episode->checks()->create($data);
            $this->syncOptions($check, $options, $correct);
        });

        return back()->with('success', 'Quick Check added.');
    }

    public function updateCheck(Request $request, string $tenantSlug, Course $course, Episode $episode, EpisodeCheck $check): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);
        $this->ensureCheckOfEpisode($episode, $check);
        [$data, $options, $correct] = $this->validatedCheck($request);

        DB::transaction(function () use ($check, $data, $options, $correct) {
            $check->update($data);
            $this->syncOptions($check, $options, $correct);
        });

        return back()->with('success', 'Quick Check saved.');
    }

    public function destroyCheck(string $tenantSlug, Course $course, Episode $episode, EpisodeCheck $check): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);
        $this->ensureCheckOfEpisode($episode, $check);

        $check->delete();

        return back()->with('success', 'Quick Check deleted.');
    }

    public function storeCaption(Request $request, string $tenantSlug, Course $course, Episode $episode): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);

        $request->validate([
            'language' => ['required', Rule::in(array_keys(EpisodeCaption::LANGUAGES))],
            'file' => ['required', 'file', 'max:1024'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['vtt', 'srt'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload a WebVTT (.vtt) or SubRip (.srt) caption file.']);
        }

        $vtt = self::toWebVtt((string) file_get_contents($file->getRealPath()), $extension === 'srt');
        if ($vtt === null) {
            throw ValidationException::withMessages(['file' => 'That file has no caption cues. Check it opens as subtitles in a video player.']);
        }

        $disk = config('lectura.episodes.disk');
        $language = $request->input('language');
        $path = "episodes/{$course->id}/captions/{$episode->id}-{$language}.vtt";

        $existing = $episode->captions()->where('language', $language)->first();
        if ($existing && ($existing->disk !== $disk || $existing->path !== $path)) {
            EpisodeMedia::disk($existing->disk)->delete($existing->path);
        }

        EpisodeMedia::disk($disk)->put($path, $vtt);
        $episode->captions()->updateOrCreate(['language' => $language], ['disk' => $disk, 'path' => $path]);

        return back()->with('success', EpisodeCaption::LANGUAGES[$language].' captions saved.');
    }

    public function destroyCaption(string $tenantSlug, Course $course, Episode $episode, EpisodeCaption $caption): RedirectResponse
    {
        $this->authorizeEpisode($course, $episode);
        if ((int) $caption->episode_id !== $episode->id) {
            abort(404);
        }

        EpisodeMedia::disk($caption->disk)->delete($caption->path);
        $caption->delete();

        return back()->with('success', 'Captions removed.');
    }

    /**
     * SubRip differs from WebVTT in its header and its comma decimal separator.
     * Returns null when the text holds no cue timings at all.
     */
    public static function toWebVtt(string $text, bool $isSrt): ?string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $text));

        if ($isSrt) {
            $text = preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $text);
        }

        if (! preg_match('/\d{2}:\d{2}(?::\d{2})?\.\d{3}\s+-->\s+\d{2}:\d{2}(?::\d{2})?\.\d{3}/', $text)) {
            return null;
        }

        return str_starts_with(ltrim($text), 'WEBVTT') ? $text : "WEBVTT\n\n".ltrim($text);
    }

    public static function clock(int $seconds): string
    {
        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * @return array{0: array, 1: list<string>, 2: int}
     */
    private function validatedCheck(Request $request): array
    {
        $validated = $request->validate([
            'at' => ['required', 'string', 'max:10'],
            'prompt' => ['required', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:1000'],
            'options' => ['required', 'array', 'max:6'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'correct' => ['required', 'integer', 'min:0'],
        ]);

        $at = StoryboardParser::seconds($validated['at']);
        if ($at === null) {
            throw ValidationException::withMessages(['at' => 'Enter the time as minutes:seconds, for example 2:47.']);
        }

        // Keep the original positions so the "correct" radio still points at the right option.
        $filled = array_filter($validated['options'], fn ($o) => trim((string) $o) !== '');
        if (count($filled) < 2) {
            throw ValidationException::withMessages(['options' => 'Give at least two options.']);
        }
        if (! array_key_exists((int) $validated['correct'], $filled)) {
            throw ValidationException::withMessages(['correct' => 'Mark which of the filled-in options is correct.']);
        }

        $correct = array_search((int) $validated['correct'], array_keys($filled), true);

        return [
            ['at_seconds' => $at, 'prompt' => $validated['prompt'], 'explanation' => $validated['explanation'] ?? null],
            array_values(array_map('trim', $filled)),
            (int) $correct,
        ];
    }

    /**
     * Replace the options. Students' saved answers keep their is_correct verdict
     * but lose the option link, which is the honest outcome of rewriting a question.
     */
    private function syncOptions(EpisodeCheck $check, array $options, int $correct): void
    {
        $check->options()->delete();
        foreach ($options as $i => $label) {
            $check->options()->create(['label' => $label, 'is_correct' => $i === $correct, 'sort_order' => $i]);
        }
    }

    private function authorizeEpisode(Course $course, Episode $episode): void
    {
        $this->authorizeCourseAccess($course);
        if ((int) $episode->course_id !== $course->id) {
            abort(404);
        }
    }

    private function ensureCheckOfEpisode(Episode $episode, EpisodeCheck $check): void
    {
        if ((int) $check->episode_id !== $episode->id) {
            abort(404);
        }
    }
}
