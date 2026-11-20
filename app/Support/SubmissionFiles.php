<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AssessmentSubmissionFile;
use App\Services\GoogleDriveService;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a student may upload to an assessment, on the web and the mobile API.
 */
class SubmissionFiles
{
    // `mod` is a CAEPIPE model (BTG3333 labs).
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'mod'];

    public const MAX_KB = 25600;

    // A CAEPIPE model has no registered content type, so only its name is checked;
    // every other file must also look like what its extension says.
    private const NAME_ONLY = ['mod'];

    public static function rules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'extensions:'.implode(',', self::EXTENSIONS), self::contentRule()];
    }

    private static function contentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile
                || in_array(strtolower($value->getClientOriginalExtension()), self::NAME_ONLY, true)) {
                return;
            }

            $checked = array_diff(self::EXTENSIONS, self::NAME_ONLY);
            if (! in_array($value->guessExtension(), $checked, true)) {
                $fail('The file does not match its extension.');
            }
        };
    }

    /**
     * Write every upload to the uploads disk before anything else changes. The
     * disk does not throw, so a failed write returns false; on any failure the
     * files already written are removed and null is returned.
     *
     * @param  array<int|string, UploadedFile>  $files
     * @return array<int|string, string>|null
     */
    public static function store(array $files, int $assessmentId): ?array
    {
        $disk = Storage::disk('uploads');
        $paths = [];

        foreach ($files as $key => $file) {
            try {
                $path = $file->store('assessment_submissions/'.$assessmentId, 'uploads');
            } catch (\Throwable $e) {
                Log::error('submission file store threw', ['assessment_id' => $assessmentId, 'error' => $e->getMessage()]);
                $path = false;
            }

            if (! is_string($path) || $path === '') {
                Log::error('submission file could not be stored', [
                    'assessment_id' => $assessmentId,
                    'file_name' => $file->getClientOriginalName(),
                ]);
                foreach ($paths as $stored) {
                    $disk->delete($stored);
                }

                return null;
            }

            $paths[$key] = $path;
        }

        return $paths;
    }

    /**
     * Serve a submission file from the uploads disk, falling back to the copy in
     * the course lecturer's Google Drive when the original is missing there.
     */
    public static function serve(AssessmentSubmissionFile $file, string $path, bool $inline = false): StreamedResponse
    {
        $disk = Storage::disk('uploads');
        $headers = ['Content-Type' => $file->file_type ?: 'application/octet-stream'];

        if ($path !== '' && $disk->exists($path)) {
            return $inline
                ? $disk->response($path, $file->file_name, $headers)
                : $disk->download($path, $file->file_name);
        }

        $lecturer = $file->submission?->assessment?->course?->lecturer;
        if ($path === $file->storage_path && $file->drive_file_id && $lecturer?->isDriveConnected()) {
            try {
                $contents = app(GoogleDriveService::class)->downloadFile($lecturer, $file->drive_file_id);

                return response()->streamDownload(
                    function () use ($contents): void {
                        echo $contents;
                    },
                    $file->file_name,
                    $headers,
                    $inline ? 'inline' : 'attachment',
                );
            } catch (\Throwable $e) {
                Log::warning('submission file drive fallback failed', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        abort(404, 'File not found.');
    }
}
