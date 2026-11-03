<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\PortfolioPhoto;
use App\Models\Section;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Teaching portfolio photos: storing them (web and mobile API), the week-by-week timeline and the
 * generated PDF portfolio.
 */
class PortfolioService
{
    /** Attendance session type → portfolio category. */
    public const SESSION_CATEGORIES = [
        'lecture' => 'lecture',
        'tutorial' => 'tutorial',
        'lab' => 'lab',
        'extra' => 'lecture',
        'replacement' => 'lecture',
    ];

    /**
     * @param  array{category?: ?string, caption?: ?string, description?: ?string, section_id?: ?int,
     *               attendance_session_id?: ?int, week_number?: ?int, taken_at?: mixed}  $attributes
     */
    public function store(UploadedFile $file, Course $course, User $user, array $attributes): PortfolioPhoto
    {
        $path = $file->store("portfolio/{$course->id}/".date('Y-m'), 'media');
        $takenAt = Carbon::parse($attributes['taken_at'] ?? now());
        $section = isset($attributes['section_id']) ? Section::find($attributes['section_id']) : null;

        return PortfolioPhoto::create([
            'tenant_id' => app('current_tenant')->id,
            'course_id' => $course->id,
            'section_id' => $attributes['section_id'] ?? null,
            'attendance_session_id' => $attributes['attendance_session_id'] ?? null,
            'user_id' => $user->id,
            'category' => $attributes['category'] ?? 'other',
            'caption' => $attributes['caption'] ?? null,
            'description' => $attributes['description'] ?? null,
            'file_path' => $path,
            'thumbnail_path' => $this->generateThumbnail($file, $course->id, $path),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
            'week_number' => $attributes['week_number'] ?? $this->weekFor($section, $takenAt, $course),
            'taken_at' => $takenAt,
        ]);
    }

    /**
     * Fills section, week, time and category from the class session a photo was taken in, keeping
     * anything the lecturer chose. A photo taken while the session runs is dated now; one added
     * afterwards takes the session's start.
     */
    public function fromSession(AttendanceSession $session, array $attributes): array
    {
        return array_merge($attributes, [
            'attendance_session_id' => $session->id,
            'section_id' => $session->section_id,
            'week_number' => $attributes['week_number'] ?? $session->week_number
                ?? $this->weekFor($session->section, $session->started_at ?? now()),
            'category' => $attributes['category'] ?? self::SESSION_CATEGORIES[$session->session_type] ?? 'lecture',
            'taken_at' => $attributes['taken_at'] ?? ($session->isActive() ? now() : ($session->started_at ?? now())),
        ]);
    }

    /**
     * Teaching week of a date, counted from the section's (or course's) semester start.
     */
    public function weekFor(?Section $section, CarbonInterface $date, ?Course $course = null): ?int
    {
        $start = ($section?->term() ?? $course?->academicTerm)?->start_date;
        if (! $start || $date->lt($start->copy()->startOfDay())) {
            return null;
        }

        $week = intdiv((int) $start->copy()->startOfDay()->diffInDays($date->copy()->startOfDay()), 7) + 1;

        return $week <= 30 ? $week : null;
    }

    /**
     * Photos grouped by teaching week, oldest week first; photos with no week come last.
     *
     * @param  Collection<int, PortfolioPhoto>  $photos
     * @return Collection<int, array{week: ?int, label: string, from: ?CarbonInterface, to: ?CarbonInterface, photos: Collection<int, PortfolioPhoto>}>
     */
    public function timeline(Collection $photos): Collection
    {
        return $photos
            ->groupBy(fn (PortfolioPhoto $photo) => $photo->week_number ?? 0)
            ->sortKeys()
            ->map(function (Collection $group, int $week) {
                $sorted = $group->sortBy(fn (PortfolioPhoto $p) => $p->taken_at?->timestamp ?? 0)->values();

                return [
                    'week' => $week ?: null,
                    'label' => $week ? "Week {$week}" : 'No week set',
                    'from' => $sorted->first()->taken_at,
                    'to' => $sorted->last()->taken_at,
                    'photos' => $sorted,
                ];
            })
            ->sortBy(fn (array $group) => $group['week'] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * The lecturer's photos of a course, for the timeline and the PDF.
     *
     * @return Collection<int, PortfolioPhoto>
     */
    public function photosFor(Course $course, User $user): Collection
    {
        return PortfolioPhoto::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->with('section', 'attendanceSession')
            ->latest('taken_at')
            ->get();
    }

    /**
     * The generated portfolio: a cover with totals, then every week with its photos and captions.
     */
    public function pdf(Course $course, User $user): \Barryvdh\DomPDF\PDF
    {
        $course->loadMissing('academicTerm');
        $photos = $this->photosFor($course, $user);

        $pdf = Pdf::loadView('tenant.portfolio.pdf', [
            'course' => $course,
            'lecturer' => $user,
            'tenant' => app('current_tenant'),
            'timeline' => $this->timeline($photos),
            'photoCount' => $photos->count(),
            'categoryCounts' => $photos->countBy('category')->sortDesc(),
            'images' => $photos->mapWithKeys(fn (PortfolioPhoto $p) => [$p->id => $this->dataUri($p)]),
        ]);

        return $pdf->setPaper('a4', 'portrait');
    }

    public function pdfFilename(Course $course): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '_', $course->code).'_teaching_portfolio.pdf';
    }

    public function delete(PortfolioPhoto $photo): void
    {
        $disk = Storage::disk('media');
        foreach (array_filter([$photo->file_path, $photo->thumbnail_path]) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $photo->delete();
    }

    /**
     * The thumbnail, embedded so dompdf never fetches remote files.
     */
    private function dataUri(PortfolioPhoto $photo): ?string
    {
        $path = $photo->thumbnail_path ?: $photo->file_path;

        try {
            $bytes = Storage::disk('media')->get($path);
        } catch (\Throwable) {
            return null;
        }

        if (! $bytes) {
            return null;
        }

        $mime = $photo->thumbnail_path ? 'image/jpeg' : $photo->mime_type;

        return "data:{$mime};base64,".base64_encode($bytes);
    }

    /**
     * A 600 px wide JPEG, turned upright from the camera's EXIF orientation.
     */
    private function generateThumbnail(UploadedFile $file, int $courseId, string $originalPath): ?string
    {
        try {
            $mime = $file->getClientMimeType();
            $source = match ($mime) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($file->getRealPath()),
                'image/png' => imagecreatefrompng($file->getRealPath()),
                'image/webp' => imagecreatefromwebp($file->getRealPath()),
                default => null,
            };

            if (! $source) {
                return null;
            }

            if (in_array($mime, ['image/jpeg', 'image/jpg'], true) && function_exists('exif_read_data')) {
                $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
                $angle = match ($orientation) {
                    3 => 180,
                    6 => -90,
                    8 => 90,
                    default => 0,
                };
                if ($angle !== 0 && ($rotated = imagerotate($source, $angle, 0))) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }

            $origW = imagesx($source);
            $origH = imagesy($source);
            $thumbW = min(600, $origW);
            $thumbH = (int) round($origH * ($thumbW / $origW));

            $thumb = imagecreatetruecolor($thumbW, $thumbH);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

            $thumbnailPath = "portfolio/{$courseId}/thumbs/".pathinfo($originalPath, PATHINFO_FILENAME).'_thumb.jpg';

            ob_start();
            imagejpeg($thumb, null, 80);
            $jpeg = ob_get_clean();
            imagedestroy($source);
            imagedestroy($thumb);

            Storage::disk('media')->put($thumbnailPath, $jpeg);

            return $thumbnailPath;
        } catch (\Throwable) {
            return null;
        }
    }
}
