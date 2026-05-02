<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionAnnotationController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * Stream the original submission file inline so the client-side canvas
     * (PDF.js or <img>) can render it. Lecturer-only.
     */
    public function raw(
        string $tenantSlug,
        Assignment $assignment,
        Submission $submission,
        SubmissionFile $file,
    ): Response {
        $this->authorizeCourseAccess($assignment->course);
        $this->ensureFileBelongsToSubmission($assignment, $submission, $file);

        if (! $file->storage_path || ! Storage::disk('local')->exists($file->storage_path)) {
            abort(404);
        }

        return response(
            Storage::disk('local')->get($file->storage_path),
            200,
            [
                'Content-Type' => Storage::disk('local')->mimeType($file->storage_path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($file->file_name) . '"',
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }

    /**
     * Persist the lecturer's pen annotations.
     *
     *   strokes: vector JSON the canvas re-loads on next visit so marks
     *            stay editable.
     *   image:   flattened PNG data URL ("data:image/png;base64,...") that
     *            the student sees alongside the original.
     */
    public function store(
        Request $request,
        string $tenantSlug,
        Assignment $assignment,
        Submission $submission,
        SubmissionFile $file,
    ): JsonResponse {
        $this->authorizeCourseAccess($assignment->course);
        $this->ensureFileBelongsToSubmission($assignment, $submission, $file);

        $data = $request->validate([
            'strokes' => ['required', 'array'],
            'image' => ['nullable', 'string'],
        ]);

        $imagePath = $file->annotated_image_path;

        if (! empty($data['image'])) {
            $imagePath = $this->writeFlattenedImage($file, $data['image'], $imagePath);
        }

        $file->update([
            'annotations' => $data['strokes'],
            'annotated_image_path' => $imagePath,
            'annotated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'annotated_at' => $file->annotated_at?->toIso8601String(),
        ]);
    }

    /**
     * Serve the flattened annotated image so it can be embedded in the
     * student-facing feedback view.
     */
    public function annotatedImage(
        string $tenantSlug,
        Assignment $assignment,
        Submission $submission,
        SubmissionFile $file,
    ): Response {
        $user = auth()->user();
        $tenant = app('current_tenant');

        // Lecturer (course access) OR the submitting student.
        $isLecturer = $user->id === $assignment->course->lecturer_id
            || $user->hasRoleInTenant($tenant->id, ['admin'])
            || $assignment->course->sections()
                ->whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))
                ->exists();

        $isOwner = (int) $submission->user_id === (int) $user->id
            || $this->userBelongsToSubmissionGroup($submission, (int) $user->id);

        if (! $isLecturer && ! $isOwner) {
            abort(403);
        }

        $this->ensureFileBelongsToSubmission($assignment, $submission, $file);

        if (! $file->annotated_image_path || ! Storage::disk('local')->exists($file->annotated_image_path)) {
            abort(404);
        }

        return response(
            Storage::disk('local')->get($file->annotated_image_path),
            200,
            [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, max-age=120',
            ]
        );
    }

    /**
     * Wipe the lecturer's annotations for this file.
     */
    public function destroy(
        string $tenantSlug,
        Assignment $assignment,
        Submission $submission,
        SubmissionFile $file,
    ): RedirectResponse {
        $this->authorizeCourseAccess($assignment->course);
        $this->ensureFileBelongsToSubmission($assignment, $submission, $file);

        if ($file->annotated_image_path && Storage::disk('local')->exists($file->annotated_image_path)) {
            Storage::disk('local')->delete($file->annotated_image_path);
        }

        $file->update([
            'annotations' => null,
            'annotated_image_path' => null,
            'annotated_at' => null,
        ]);

        return back()->with('success', 'Annotations cleared.');
    }

    private function ensureFileBelongsToSubmission(
        Assignment $assignment,
        Submission $submission,
        SubmissionFile $file,
    ): void {
        if ($submission->assignment_id !== $assignment->id || $file->submission_id !== $submission->id) {
            abort(404);
        }
    }

    private function userBelongsToSubmissionGroup(Submission $submission, int $userId): bool
    {
        if ($submission->student_group_id) {
            return Submission::where('assignment_id', $submission->assignment_id)
                ->where('student_group_id', $submission->student_group_id)
                ->where('user_id', $userId)
                ->exists();
        }

        if ($submission->assignment_group_id) {
            return Submission::where('assignment_id', $submission->assignment_id)
                ->where('assignment_group_id', $submission->assignment_group_id)
                ->where('user_id', $userId)
                ->exists();
        }

        return false;
    }

    private function writeFlattenedImage(SubmissionFile $file, string $dataUrl, ?string $existingPath): string
    {
        if (! preg_match('#^data:image/png;base64,#', $dataUrl)) {
            abort(422, 'Annotated image must be a PNG data URL.');
        }

        $payload = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($payload === false) {
            abort(422, 'Could not decode annotated image.');
        }

        if ($existingPath && Storage::disk('local')->exists($existingPath)) {
            Storage::disk('local')->delete($existingPath);
        }

        $path = sprintf(
            'submission-annotations/%d/%d-%s.png',
            $file->submission_id,
            $file->id,
            Str::random(8),
        );

        Storage::disk('local')->put($path, $payload);

        return $path;
    }
}
