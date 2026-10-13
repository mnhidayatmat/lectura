<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Episode;
use App\Models\EpisodeClip;
use App\Services\Episodes\EpisodeMedia;
use Illuminate\Console\Command;
use Illuminate\Http\File;

/**
 * Imports portrait clips cut from a course's episodes. The folder holds the
 * MP4s, their poster JPEGs and a manifest.json listing one object per clip:
 * episode_number, clip_number, title, topic, takeaway, start_seconds,
 * duration_seconds, file, poster (file and poster relative to the folder).
 * Re-running updates the text and skips media already stored, so a folder can
 * grow as more clips are rendered.
 */
class ImportEpisodeClips extends Command
{
    protected $signature = 'watch:import-clips
        {course : Course id}
        {dir : Folder holding manifest.json, the clips and their posters}
        {--replace-media : Upload the video and poster again for clips already imported}
        {--dry-run : Show what would happen without writing}';

    protected $description = 'Import portrait clips of a course\'s Watch episodes from a manifest';

    public function handle(): int
    {
        $course = Course::withoutGlobalScopes()->find((int) $this->argument('course'));
        if (! $course) {
            $this->error('Course not found.');

            return self::FAILURE;
        }

        $dir = rtrim((string) $this->argument('dir'), '/');
        $manifest = json_decode((string) @file_get_contents("{$dir}/manifest.json"), true);
        if (! is_array($manifest)) {
            $this->error("No readable {$dir}/manifest.json.");

            return self::FAILURE;
        }

        $episodes = Episode::withoutGlobalScopes()->where('course_id', $course->id)->get()->keyBy('episode_number');
        $disk = config('lectura.episodes.disk');
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($manifest as $row) {
            $label = sprintf('EP%02d S%d', $row['episode_number'] ?? 0, $row['clip_number'] ?? 0);
            $episode = $episodes->get((int) ($row['episode_number'] ?? 0));
            $video = "{$dir}/".($row['file'] ?? '');
            $poster = filled($row['poster'] ?? null) ? "{$dir}/{$row['poster']}" : null;

            if (! $episode || ! is_file($video)) {
                $this->warn("{$label}: skipped (".(! $episode ? 'no such episode' : 'video missing').')');
                $counts['skipped']++;

                continue;
            }

            $clip = EpisodeClip::withoutGlobalScopes()
                ->where('episode_id', $episode->id)
                ->where('clip_number', (int) $row['clip_number'])
                ->first();
            $isNew = $clip === null;
            $clip ??= new EpisodeClip([
                'tenant_id' => $course->tenant_id,
                'episode_id' => $episode->id,
                'course_id' => $course->id,
                'clip_number' => (int) $row['clip_number'],
            ]);

            $clip->fill([
                'title' => $row['title'],
                'topic' => $row['topic'] ?? null,
                'takeaway' => $row['takeaway'] ?? null,
                'start_seconds' => isset($row['start_seconds']) ? (int) floor((float) $row['start_seconds']) : null,
                'duration_seconds' => isset($row['duration_seconds']) ? (int) round((float) $row['duration_seconds']) : null,
            ]);

            if ($dryRun) {
                $this->line("{$label}: would be ".($isNew ? 'created' : 'updated')." ({$clip->title})");
                $counts[$isNew ? 'created' : 'updated']++;

                continue;
            }

            if ($isNew || $this->option('replace-media')) {
                $storage = EpisodeMedia::disk($disk);
                $clip->fill([
                    'video_disk' => $disk,
                    'video_path' => $storage->putFile("episodes/{$course->id}/clips", new File($video)),
                    'video_mime' => 'video/mp4',
                    'video_size_bytes' => filesize($video),
                    'poster_path' => $poster && is_file($poster)
                        ? $storage->putFile("episodes/{$course->id}/clips/posters", new File($poster))
                        : $clip->poster_path,
                ]);
            }

            $clip->save();
            $counts[$isNew ? 'created' : 'updated']++;
            $this->line("{$label}: ".($isNew ? 'created' : 'updated')." #{$clip->id} {$clip->title}");
        }

        $this->info(sprintf('%d created, %d updated, %d skipped%s.', $counts['created'], $counts['updated'], $counts['skipped'], $dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
