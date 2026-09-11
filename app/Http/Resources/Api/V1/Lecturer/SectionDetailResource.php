<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** @mixin Section */
class SectionDetailResource extends SectionResource
{
    /** @var array<int, string|null> */
    private array $studentIdNumbers = [];

    public function withStudentIdNumbers(array $numbers): static
    {
        $this->studentIdNumbers = $numbers;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'code' => $this->course->code,
                'title' => $this->course->title,
            ]),
            'students' => $this->activeStudents
                ->sortBy(fn (User $student) => mb_strtolower($student->name))
                ->map(fn (User $student) => self::rosterEntry(
                    $student,
                    $this->studentIdNumbers[$student->id] ?? null,
                    $student->pivot->enrollment_method,
                    $student->pivot->enrolled_at,
                ))
                ->values(),
        ]);
    }

    public static function rosterEntry(User $student, ?string $studentIdNumber, ?string $method, mixed $enrolledAt): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'student_id_number' => $studentIdNumber,
            'enrollment_method' => $method,
            'enrolled_at' => $enrolledAt ? Carbon::parse($enrolledAt)->toIso8601String() : null,
        ];
    }
}
