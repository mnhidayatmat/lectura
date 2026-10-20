<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Http\Resources\Api\V1\Live\QuizQuestionResource;
use App\Models\QuestionOption;
use App\Models\QuizParticipant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The live quiz game shared by the web board, the web player and the mobile API:
 * opening and closing questions, speed-based game points, nicknames and rankings.
 *
 * Marks (`points_earned` / `total_score`) stay the question's points for a correct
 * answer, so results and grading are unchanged. The game score is separate: a correct
 * answer earns 500–1000 points, fewer the longer it took, timed from when the server
 * opened the question.
 */
class LiveQuizService
{
    public const MAX_POINTS = 1000;

    public const MIN_POINTS = 500;

    /** Answers are still accepted this long after the timer ends, to cover polling lag. */
    public const GRACE_MS = 3000;

    public const NICKNAME_MAX = 30;

    private const STATE_CACHE_SECONDS = 2;

    public function start(QuizSession $session): bool
    {
        if ($session->status !== 'waiting') {
            return false;
        }

        $session->update(['status' => 'active', 'started_at' => now()]);

        $first = $session->sessionQuestions()->orderBy('sort_order')->first();
        if ($first) {
            $this->open($first);
        }

        $this->forget($session);

        return true;
    }

    public function closeActive(QuizSession $session): ?QuizSessionQuestion
    {
        $current = $session->activeQuestion();
        $current?->update(['status' => 'closed', 'closed_at' => now()]);

        $this->forget($session);

        return $current;
    }

    /**
     * Open the next pending question, or move the session to `reviewing` (final standings)
     * when none is left. Refused while a question is still open.
     */
    public function advance(QuizSession $session): ?QuizSessionQuestion
    {
        if ($session->status !== 'active' || $session->activeQuestion()) {
            return null;
        }

        $next = $session->sessionQuestions()->where('status', 'pending')->orderBy('sort_order')->first();

        if ($next) {
            $this->open($next);
        } else {
            $session->update(['status' => 'reviewing']);
        }

        $this->forget($session);

        return $next;
    }

    public function finish(QuizSession $session): void
    {
        $session->sessionQuestions()->where('status', 'active')->update(['status' => 'closed', 'closed_at' => now()]);

        $totals = QuizResponse::whereIn('quiz_participant_id', $session->participants()->select('id'))
            ->groupBy('quiz_participant_id')
            ->selectRaw('quiz_participant_id, SUM(points_earned) as marks, SUM(game_points) as game')
            ->get()
            ->keyBy('quiz_participant_id');

        foreach ($session->participants()->get() as $participant) {
            $row = $totals->get($participant->id);
            $participant->update([
                'total_score' => (float) ($row->marks ?? 0),
                'game_score' => (int) ($row->game ?? 0),
            ]);
        }

        $session->update(['status' => 'ended', 'ended_at' => now()]);

        $this->forget($session);
    }

    public function open(QuizSessionQuestion $question): void
    {
        $question->update(['status' => 'active', 'opened_at' => now(), 'opened_at_ms' => $this->nowMs()]);
    }

    public function elapsedMs(QuizSessionQuestion $question): int
    {
        $opened = $question->opened_at_ms ?? $question->opened_at?->getTimestampMs();

        return $opened === null ? 0 : max(0, $this->nowMs() - (int) $opened);
    }

    public function remainingSeconds(QuizSessionQuestion $question): int
    {
        $limitMs = $this->timeLimit($question) * 1000;

        return (int) max(0, ceil(($limitMs - $this->elapsedMs($question)) / 1000));
    }

    public function isExpired(QuizSessionQuestion $question): bool
    {
        return $this->elapsedMs($question) > $this->timeLimit($question) * 1000 + self::GRACE_MS;
    }

    public function gamePoints(bool $correct, int $elapsedMs, int $timeLimitSeconds): int
    {
        if (! $correct) {
            return 0;
        }

        $ratio = min(1, max(0, $elapsedMs / max(1, $timeLimitSeconds * 1000)));

        return (int) round(self::MAX_POINTS - (self::MAX_POINTS - self::MIN_POINTS) * $ratio);
    }

    /**
     * Record one answer to the open question. Returns the stored response and whether it
     * already existed (a second tap, or a race between two requests).
     *
     * @return array{0: QuizResponse, 1: bool}
     */
    public function answer(QuizParticipant $participant, QuizSessionQuestion $question, ?QuestionOption $option, ?string $text): array
    {
        $existing = QuizResponse::where('quiz_session_question_id', $question->id)
            ->where('quiz_participant_id', $participant->id)
            ->first();

        if ($existing) {
            return [$existing, true];
        }

        $elapsed = $this->elapsedMs($question);
        $correct = $option !== null && $option->is_correct;
        $marks = $correct ? (float) $question->question->points : 0.0;
        $game = $this->gamePoints($correct, $elapsed, $this->timeLimit($question));

        try {
            $response = DB::transaction(function () use ($participant, $question, $option, $text, $correct, $marks, $game, $elapsed) {
                $response = QuizResponse::create([
                    'quiz_session_question_id' => $question->id,
                    'quiz_participant_id' => $participant->id,
                    'selected_option_id' => $option?->id,
                    'answer_text' => $text !== null && $text !== '' ? $text : null,
                    'is_correct' => $correct,
                    'points_earned' => $marks,
                    'game_points' => $game,
                    'response_time_ms' => $elapsed,
                ]);

                if ($marks > 0 || $game > 0) {
                    QuizParticipant::whereKey($participant->id)->update([
                        'total_score' => DB::raw('total_score + '.$marks),
                        'game_score' => DB::raw('game_score + '.$game),
                    ]);
                }

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            return [QuizResponse::where('quiz_session_question_id', $question->id)
                ->where('quiz_participant_id', $participant->id)
                ->firstOrFail(), true];
        }

        return [$response, false];
    }

    public function rename(QuizParticipant $participant, string $nickname): void
    {
        $participant->update(['display_name' => $this->cleanNickname($nickname)]);

        Cache::forget("quiz_session_{$participant->quiz_session_id}_lobby");
    }

    public function cleanNickname(string $nickname): string
    {
        $clean = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $nickname));

        return mb_substr($clean, 0, self::NICKNAME_MAX);
    }

    public function displayName(QuizParticipant $participant): string
    {
        return $participant->display_name ?: ($participant->user?->name ?? 'Player');
    }

    /**
     * Every participant ranked by game score, with their rank before the latest closed
     * question (null when nobody had scored yet) and the points that question gave them.
     * Ties share a rank (1, 1, 3).
     *
     * @return Collection<int, array{participant_id: int, user_id: int, rank: int, previous_rank: ?int, name: string, points: int, gained: int, score: float}>
     */
    public function standings(QuizSession $session): Collection
    {
        $participants = QuizParticipant::where('quiz_session_id', $session->id)
            ->with('user:id,name')
            ->get();

        $lastClosed = $session->sessionQuestions()
            ->where('status', 'closed')
            ->reorder()
            ->orderByDesc('closed_at')
            ->orderByDesc('sort_order')
            ->first();

        $round = $lastClosed
            ? QuizResponse::where('quiz_session_question_id', $lastClosed->id)->get(['quiz_participant_id', 'game_points', 'points_earned'])->keyBy('quiz_participant_id')
            : collect();
        $gained = $round->map(fn (QuizResponse $r) => (int) $r->game_points);

        $previous = $this->rank($participants->map(fn (QuizParticipant $p) => [
            'id' => $p->id,
            'points' => $p->game_score - (int) ($gained[$p->id] ?? 0),
            'score' => (float) $p->total_score - (float) ($round->get($p->id)?->points_earned ?? 0),
        ]));

        $current = $this->rank($participants->map(fn (QuizParticipant $p) => [
            'id' => $p->id,
            'points' => $p->game_score,
            'score' => (float) $p->total_score,
        ]));

        // Before anyone scored there is no ranking to move from.
        $hadRanking = $previous->contains(fn (array $row) => $row['points'] > 0 || $row['score'] > 0);

        $byId = $participants->keyBy('id');

        return $current->map(fn (array $row) => [
            'participant_id' => $row['id'],
            'user_id' => $byId[$row['id']]->user_id,
            'rank' => $row['rank'],
            'previous_rank' => $hadRanking ? $previous[$row['id']]['rank'] : null,
            'name' => $this->displayName($byId[$row['id']]),
            'points' => $row['points'],
            'gained' => (int) ($gained[$row['id']] ?? 0),
            'score' => $row['score'],
        ])->values();
    }

    /**
     * Board state for the lecturer's presenter screen.
     */
    public function boardState(QuizSession $session): array
    {
        $session->refresh();
        $questions = $session->sessionQuestions()->with('question.options')->get();
        $active = $questions->firstWhere('status', 'active');
        $lastClosed = $questions->where('status', 'closed')->sortBy([['closed_at', 'desc'], ['sort_order', 'desc']])->first();

        $phase = match (true) {
            $session->status === 'waiting' => 'lobby',
            $session->status === 'reviewing' => 'finished',
            $session->status === 'ended' => 'ended',
            $active !== null => 'answering',
            $lastClosed !== null => 'reveal',
            default => 'lobby',
        };

        $current = $phase === 'answering' ? $active : ($phase === 'reveal' ? $lastClosed : null);

        $participants = QuizParticipant::where('quiz_session_id', $session->id)
            ->with('user:id,name')
            ->orderBy('joined_at')
            ->orderBy('id')
            ->get()
            ->map(fn (QuizParticipant $p) => ['id' => $p->id, 'name' => $this->displayName($p)])
            ->values();

        $question = null;
        $answered = 0;

        if ($current) {
            $responses = QuizResponse::where('quiz_session_question_id', $current->id)->get(['selected_option_id']);
            $answered = $responses->count();
            $reveal = $phase === 'reveal';
            $counts = $responses->countBy('selected_option_id');
            $q = $current->question;

            $question = [
                'session_question_id' => $current->id,
                'number' => $questions->search(fn ($row) => $row->id === $current->id) + 1,
                'type' => $q->question_type,
                'text' => $q->text,
                'time_limit' => $this->timeLimit($current),
                'remaining_seconds' => $phase === 'answering' ? $this->remainingSeconds($current) : null,
                'options' => $q->options->map(fn (QuestionOption $o) => array_merge(
                    ['id' => $o->id, 'label' => $o->label, 'text' => $o->text],
                    $reveal ? ['is_correct' => (bool) $o->is_correct, 'count' => (int) ($counts[$o->id] ?? 0)] : [],
                ))->values()->all(),
                'explanation' => $reveal ? $q->explanation : null,
            ];
        }

        return [
            'status' => $session->status,
            'phase' => $phase,
            'join_code' => $session->join_code,
            'participant_count' => $participants->count(),
            'participants' => $participants,
            'question_total' => $questions->count(),
            'is_last_question' => $questions->where('status', 'pending')->isEmpty(),
            'question' => $question,
            'answered_count' => $answered,
            'leaderboard' => in_array($phase, ['reveal', 'finished', 'ended'], true)
                ? $this->standings($session)->take(10)->map(fn (array $row) => collect($row)->except('user_id')->all())->values()
                : null,
        ];
    }

    /**
     * What a student's screen shows (web player and the mobile app). The session-wide part
     * is cached briefly and shared by every student; only `me` is per request.
     */
    public function playerState(QuizSession $session, ?QuizParticipant $participant): array
    {
        $shared = Cache::remember(
            "api_quiz_session_{$session->id}_state",
            self::STATE_CACHE_SECONDS,
            fn () => $this->sharedPlayerState($session),
        );

        $phase = $shared['phase'];
        $question = $shared['question'];

        if ($question) {
            $question['remaining_seconds'] = $phase === 'answering' && $shared['opened_at_ms'] !== null
                ? (int) max(0, ceil(($question['time_limit'] * 1000 - ($this->nowMs() - $shared['opened_at_ms'])) / 1000))
                : null;
        }

        $standing = $participant && $shared['standings'] !== null
            ? collect($shared['standings'])->firstWhere('participant_id', $participant->id)
            : null;

        $me = [
            'joined' => $participant !== null,
            'display_name' => $participant?->display_name,
            'score' => (float) ($participant?->total_score ?? 0),
            'points' => (int) ($participant?->game_score ?? 0),
            'answered' => false,
            'selected_option_id' => null,
            'answer_text' => null,
            'is_correct' => null,
            'points_earned' => null,
            'points_gained' => null,
            'rank' => $standing['rank'] ?? null,
            'previous_rank' => $standing['previous_rank'] ?? null,
        ];

        if ($participant && $question && in_array($phase, ['answering', 'reveal'], true)) {
            $response = QuizResponse::where('quiz_session_question_id', $question['session_question_id'])
                ->where('quiz_participant_id', $participant->id)
                ->first();

            $me['answered'] = $response !== null;
            $me['selected_option_id'] = $response?->selected_option_id;
            $me['answer_text'] = $response?->answer_text;

            if ($phase === 'reveal') {
                $me['is_correct'] = $response ? (bool) $response->is_correct : null;
                $me['points_earned'] = $response ? (float) $response->points_earned : null;
                $me['points_gained'] = $response ? (int) $response->game_points : 0;
            }
        }

        $leaderboard = $shared['standings'] === null ? null : collect($shared['standings'])
            ->take(10)
            ->map(fn (array $row) => [
                'rank' => $row['rank'],
                'previous_rank' => $row['previous_rank'],
                'name' => $row['name'],
                'score' => $row['score'],
                'points' => $row['points'],
                'gained' => $row['gained'],
                'is_me' => $participant !== null && $row['participant_id'] === $participant->id,
            ])
            ->values()
            ->all();

        return [
            'server_time' => now()->toIso8601String(),
            'status' => $shared['status'],
            'phase' => $phase,
            'participant_count' => $shared['participant_count'],
            'question_total' => $shared['question_total'],
            'question' => $question,
            'me' => $me,
            'leaderboard' => $leaderboard,
        ];
    }

    private function sharedPlayerState(QuizSession $session): array
    {
        $session->refresh();
        $questions = $session->sessionQuestions()->with('question.options')->get();
        $active = $questions->firstWhere('status', 'active');
        $closed = null;

        $phase = match (true) {
            $session->status === 'waiting' => 'lobby',
            in_array($session->status, ['reviewing', 'ended'], true) => 'finished',
            $active !== null => 'answering',
            default => 'reveal',
        };

        if ($phase === 'reveal') {
            $closed = $questions->where('status', 'closed')->sortBy([['closed_at', 'desc'], ['sort_order', 'desc']])->first();

            if (! $closed) {
                $phase = 'lobby';
            }
        }

        $current = $phase === 'answering' ? $active : ($phase === 'reveal' ? $closed : null);
        $question = null;

        if ($current) {
            $position = $questions->search(fn (QuizSessionQuestion $q) => $q->id === $current->id) + 1;
            $question = (new QuizQuestionResource($current, $position, $questions->count(), $phase === 'reveal'))->resolve();
        }

        $standings = in_array($phase, ['reveal', 'finished'], true)
            ? $this->standings($session)->all()
            : null;

        return [
            'status' => $session->status,
            'phase' => $phase,
            'participant_count' => QuizParticipant::where('quiz_session_id', $session->id)->count(),
            'question_total' => $questions->count(),
            'question' => $question,
            'opened_at_ms' => $current && $phase === 'answering'
                ? ($current->opened_at_ms ?? $current->opened_at?->getTimestampMs())
                : null,
            'standings' => $standings,
        ];
    }

    public function forget(QuizSession $session): void
    {
        Cache::forget("quiz_session_{$session->id}_state");
        Cache::forget("quiz_session_{$session->id}_lobby");
        Cache::forget("api_quiz_session_{$session->id}_state");
    }

    private function timeLimit(QuizSessionQuestion $question): int
    {
        return max(5, (int) ($question->question?->time_limit_seconds ?? 30));
    }

    private function nowMs(): int
    {
        return (int) now()->getTimestampMs();
    }

    /**
     * @param  Collection<int, array{id: int, points: int, score: float}>  $rows
     * @return Collection<int, array{id: int, points: int, score: float, rank: int}>
     */
    private function rank(Collection $rows): Collection
    {
        $rank = 0;
        $previous = null;

        return $rows
            ->sort(fn (array $a, array $b) => [$b['points'], $b['score'], $a['id']] <=> [$a['points'], $a['score'], $b['id']])
            ->values()
            ->map(function (array $row, int $i) use (&$rank, &$previous) {
                $key = $row['points'].'|'.$row['score'];
                if ($key !== $previous) {
                    $rank = $i + 1;
                    $previous = $key;
                }

                return $row + ['rank' => $rank];
            })
            ->keyBy('id');
    }
}
