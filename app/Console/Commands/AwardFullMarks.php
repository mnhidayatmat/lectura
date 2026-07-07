<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\QuizParticipant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AwardFullMarks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'quizzes:award-full-marks
        {course : Course name (partial match)}
        {--quiz=* : Limit to specific quiz titles (repeatable); omit for all quizzes in the course}
        {--dry-run : Preview what would change without writing}
        {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Award full marks to every active enrolled student for a course\'s quizzes';

    public function handle(): int
    {
        $courseName = (string) $this->argument('course');
        $titles = (array) $this->option('quiz');
        $dryRun = (bool) $this->option('dry-run');

        // Tenant global scopes are not bound in CLI context, so bypass them.
        $course = Course::withoutGlobalScopes()
            ->where('name', 'like', '%'.$courseName.'%')
            ->first();

        if (! $course) {
            $this->error("No course matching \"{$courseName}\".");

            return self::FAILURE;
        }

        $sessions = QuizSession::withoutGlobalScopes()
            ->whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->when($titles, fn ($q) => $q->whereIn('title', $titles))
            ->with(['sessionQuestions.question.options', 'section'])
            ->get();

        if ($sessions->isEmpty()) {
            $this->warn("No quiz sessions found for course #{$course->id} ({$course->name}).");

            return self::SUCCESS;
        }

        $this->info("Course #{$course->id}: {$course->name}");
        $this->line($dryRun ? '<comment>DRY RUN — no changes will be written.</comment>' : '');

        // Preview table
        $rows = $sessions->map(function (QuizSession $session) {
            $max = (float) $session->sessionQuestions->sum(fn ($sq) => (float) ($sq->question->points ?? 0));

            return [
                $session->title,
                $session->sessionQuestions->count(),
                number_format($max, 2),
                $session->section->activeStudents()->count(),
            ];
        })->all();

        $this->table(['Quiz', 'Questions', 'Full mark', 'Active students'], $rows);

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Award full marks to all listed students?', true)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $totalStudents = 0;

        DB::transaction(function () use ($sessions, &$totalStudents) {
            foreach ($sessions as $session) {
                $maxScore = (float) $session->sessionQuestions->sum(fn ($sq) => (float) ($sq->question->points ?? 0));

                foreach ($session->section->activeStudents()->get() as $student) {
                    $participant = QuizParticipant::firstOrCreate(
                        ['quiz_session_id' => $session->id, 'user_id' => $student->id],
                        ['display_name' => $student->name, 'joined_at' => now()]
                    );

                    foreach ($session->sessionQuestions as $sq) {
                        $correct = $sq->question->options->firstWhere('is_correct', true);

                        QuizResponse::updateOrCreate(
                            ['quiz_session_question_id' => $sq->id, 'quiz_participant_id' => $participant->id],
                            [
                                'is_correct' => true,
                                'points_earned' => (float) ($sq->question->points ?? 0),
                                'selected_option_id' => $correct->id ?? null,
                                'answer_text' => $correct ? null : 'Full marks awarded',
                            ]
                        );
                    }

                    $participant->update(['total_score' => $maxScore]);
                    $totalStudents++;
                }
            }
        });

        $this->info("Done. Awarded full marks to {$totalStudents} student record(s) across {$sessions->count()} quiz(zes).");

        return self::SUCCESS;
    }
}
