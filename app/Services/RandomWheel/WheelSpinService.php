<?php

declare(strict_types=1);

namespace App\Services\RandomWheel;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\RandomWheelSpin;
use App\Models\SectionStudent;
use App\Models\User;
use App\Notifications\RandomWheelPicked;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Records the lecturer's spins so enrolled students can watch the wheel land,
 * and alerts their phones with who was picked once it stops.
 */
class WheelSpinService
{
    /** How long after a spin students still see it as the current wheel. */
    public const LIVE_WINDOW_MINUTES = 180;

    /**
     * @param  list<int>  $candidateIds  the wheel's names in segment order
     */
    public function record(
        AttendanceSession $session,
        User $lecturer,
        array $candidateIds,
        int $winnerId,
        int $turns,
        int $durationMs,
    ): RandomWheelSpin {
        $candidateIds = array_values(array_unique(array_map('intval', $candidateIds)));

        // Only students checked in to this session may appear on a wheel others can see.
        $names = AttendanceRecord::where('attendance_session_id', $session->id)
            ->whereIn('status', ['present', 'late'])
            ->whereIn('user_id', $candidateIds)
            ->with('user:id,name')
            ->get()
            ->filter(fn (AttendanceRecord $record) => $record->user !== null)
            ->mapWithKeys(fn (AttendanceRecord $record) => [$record->user_id => $record->user->name]);

        if ($names->count() !== count($candidateIds)) {
            throw ValidationException::withMessages(['candidate_ids' => 'Every student on the wheel must be checked in to this session.']);
        }

        $winnerIndex = array_search($winnerId, $candidateIds, true);
        if ($winnerIndex === false) {
            throw ValidationException::withMessages(['winner_id' => 'The winner must be on the wheel.']);
        }

        $spin = RandomWheelSpin::create([
            'tenant_id' => $session->tenant_id,
            'attendance_session_id' => $session->id,
            'section_id' => $session->section_id,
            'spun_by' => $lecturer->id,
            'winner_id' => $winnerId,
            'candidates' => array_map(fn (int $id) => ['id' => $id, 'name' => $names[$id]], $candidateIds),
            'winner_index' => $winnerIndex,
            'turns' => $turns,
            'duration_ms' => $durationMs,
            'spun_at' => now(),
        ]);

        $this->notifyClass($spin->load('section.course'));

        return $spin;
    }

    /**
     * The newest spin in any section the student is enrolled in, if it is recent enough to be this class's.
     */
    public function latestFor(User $student): ?RandomWheelSpin
    {
        $sectionIds = SectionStudent::where('user_id', $student->id)
            ->where('is_active', true)
            ->pluck('section_id');

        if ($sectionIds->isEmpty()) {
            return null;
        }

        return RandomWheelSpin::whereIn('section_id', $sectionIds)
            ->where('spun_at', '>=', now()->subMinutes(self::LIVE_WINDOW_MINUTES))
            ->with('section.course')
            ->orderByDesc('spun_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * What a student's wheel needs to replay the spin. `elapsed_ms` comes from the server clock
     * so a phone with the wrong time still joins the animation at the right point.
     *
     * @return array<string, mixed>
     */
    public function present(RandomWheelSpin $spin, User $viewer): array
    {
        $course = $spin->section?->course;
        $winner = $spin->candidates[$spin->winner_index] ?? ['id' => $spin->winner_id, 'name' => ''];

        return [
            'id' => $spin->id,
            'course' => $course ? ['id' => $course->id, 'code' => $course->code, 'title' => $course->title] : null,
            'section_name' => $spin->section?->name,
            'candidates' => array_values($spin->candidates),
            'winner' => $winner,
            'winner_index' => $spin->winner_index,
            'turns' => $spin->turns,
            'duration_ms' => $spin->duration_ms,
            'spun_at' => $spin->spun_at->toIso8601String(),
            'elapsed_ms' => max(0, (int) $spin->spun_at->diffInMilliseconds(now())),
            'is_me' => $spin->winner_id === $viewer->id,
        ];
    }

    private function notifyClass(RandomWheelSpin $spin): void
    {
        $students = User::whereIn('id', SectionStudent::where('section_id', $spin->section_id)
            ->where('is_active', true)
            ->select('user_id'))
            ->orWhere('id', $spin->winner_id)
            ->get();

        $course = $spin->section?->course;
        $winner = $spin->candidates[$spin->winner_index]['name'] ?? '';

        // Held back until the wheel stops, so the phones don't spoil the result.
        Notification::send($students, (new RandomWheelPicked(
            spinId: $spin->id,
            winnerId: $spin->winner_id,
            winnerName: $winner,
            courseId: $course?->id,
            courseCode: $course?->code ?? '',
        ))->delay(now()->addMilliseconds($spin->duration_ms)));
    }
}
