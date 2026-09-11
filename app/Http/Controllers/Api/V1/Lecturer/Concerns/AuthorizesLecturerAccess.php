<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer\Concerns;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\TenantUser;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lecturer-area authorization for the mobile API, built on the web's AuthorizesCourseAccess rules
 * but always aborting with a human-readable message.
 */
trait AuthorizesLecturerAccess
{
    use AuthorizesCourseAccess;

    protected function ensureLecturer(): void
    {
        if (auth()->user()->roleInTenant(app('current_tenant')->id) === 'student') {
            abort(403, 'This area is for lecturers.');
        }
    }

    protected function authorizeCourse(Course $course): void
    {
        try {
            $this->authorizeCourseAccess($course);
        } catch (HttpException $e) {
            abort(
                $e->getStatusCode(),
                $e->getStatusCode() === 404 ? 'Course not found.' : 'You do not have access to this course.'
            );
        }
    }

    /**
     * Same rule as AttendanceController@start: section lecturer, course owner or tenant admin.
     */
    protected function canAccessSection(Section $section): bool
    {
        $user = auth()->user();

        return $section->lecturers->contains('id', $user->id)
            || $section->course?->lecturer_id === $user->id
            || $user->hasRoleInTenant(app('current_tenant')->id, ['admin']);
    }

    protected function authorizeSection(Course $course, Section $section): void
    {
        $this->authorizeCourse($course);

        if ($section->course_id !== $course->id) {
            abort(404, 'Section not found.');
        }

        if (! $this->canAccessSection($section)) {
            abort(403, 'You do not have access to this section.');
        }
    }

    /**
     * Mirrors AttendanceController::authorizeSession on the web.
     */
    protected function authorizeSession(AttendanceSession $session): void
    {
        $user = auth()->user();

        if ($session->lecturer_id === $user->id) {
            return;
        }

        $session->loadMissing('section.course');
        $section = $session->section;
        $course = $section?->course;

        $canAccess = ($section && $section->lecturers->contains('id', $user->id))
            || ($course && $course->lecturer_id === $user->id)
            || $user->hasRoleInTenant(app('current_tenant')->id, ['admin']);

        if (! $canAccess) {
            abort(403, 'You do not have access to this attendance session.');
        }
    }

    /**
     * @return Collection<int, string|null> student ID numbers keyed by user id
     */
    protected function studentIdNumbers(iterable $userIds): Collection
    {
        return TenantUser::where('tenant_id', app('current_tenant')->id)
            ->where('role', 'student')
            ->whereIn('user_id', collect($userIds)->unique()->values()->all())
            ->pluck('student_id_number', 'user_id');
    }
}
