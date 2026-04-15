<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use Throwable;

class SubmissionReportStampingService
{
    /**
     * Re-stamp every PDF in a submission with a grade-report cover page.
     * Called after the lecturer saves marks — safe to run repeatedly; each
     * call rebuilds the graded copy from the original source file.
     */
    public function stamp(AssessmentSubmission $submission): void
    {
        $submission->loadMissing([
            'files',
            'user',
            'score',
            'assessment.course',
            'assessment.rubric.criteria.levels',
        ]);

        $score = $submission->score;
        if (! $score || ! $score->finalized_at) {
            return;
        }

        $assessment = $submission->assessment;
        if (! $assessment) {
            return;
        }

        foreach ($submission->files as $file) {
            if (! $file->isPdf()) {
                continue;
            }

            if (! Storage::disk('local')->exists($file->storage_path)) {
                continue;
            }

            try {
                $gradedPath = $this->buildStampedCopy($submission, $file->storage_path, $assessment);
            } catch (Throwable $e) {
                Log::warning('submission stamping failed', [
                    'submission_id' => $submission->id,
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            // Clean up any previous graded copy before swapping in the new one.
            if ($file->graded_file_path && $file->graded_file_path !== $gradedPath
                && Storage::disk('local')->exists($file->graded_file_path)) {
                Storage::disk('local')->delete($file->graded_file_path);
            }

            $file->update([
                'graded_file_path' => $gradedPath,
                'graded_at' => now(),
            ]);
        }
    }

    protected function buildStampedCopy(AssessmentSubmission $submission, string $sourceRelativePath, Assessment $assessment): string
    {
        $sourceAbsolute = Storage::disk('local')->path($sourceRelativePath);

        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 15);

        $this->drawCoverPage($pdf, $submission, $assessment);

        try {
            $pageCount = $pdf->setSourceFile($sourceAbsolute);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);
            }
        } catch (PdfParserException $e) {
            // The original PDF uses features FPDI's free parser can't read
            // (most commonly PDF 1.5+ object streams). Fall back to a cover
            // sheet only — still gives the student the grade breakdown,
            // just without the original pages appended.
            Log::info('submission stamping: could not import original PDF, saving cover-only copy', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }

        $destinationRelative = sprintf(
            'assessment-submissions/graded/%d-%s.pdf',
            $submission->id,
            Str::random(8)
        );

        Storage::disk('local')->makeDirectory(dirname($destinationRelative));
        $destinationAbsolute = Storage::disk('local')->path($destinationRelative);
        $pdf->Output('F', $destinationAbsolute);

        return $destinationRelative;
    }

    /**
     * Draw the cover page into the FPDI document. Uses core FPDF fonts only
     * so we don't depend on any bundled font files.
     */
    protected function drawCoverPage(Fpdi $pdf, AssessmentSubmission $submission, Assessment $assessment): void
    {
        $pdf->AddPage();

        $score = $submission->score;
        $student = $submission->user;
        $course = $assessment->course;

        $indigo = [79, 70, 229];
        $slate700 = [51, 65, 85];
        $slate500 = [100, 116, 139];
        $slate300 = [203, 213, 225];
        $slate100 = [241, 245, 249];
        $emerald = [16, 185, 129];
        $amber = [245, 158, 11];
        $red = [239, 68, 68];

        // ── Header band ────────────────────────────────────────────────
        $pdf->SetFillColor(...$indigo);
        $pdf->Rect(0, 0, 210, 28, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetXY(15, 9);
        $pdf->Cell(0, 8, $this->ascii('Grade Report'));

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(15, 18);
        $pdf->Cell(0, 6, $this->ascii(($course->code ?? '').' - '.($course->title ?? '')));

        // ── Student + assessment meta ──────────────────────────────────
        $pdf->SetTextColor(...$slate700);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetXY(15, 36);
        $pdf->Cell(0, 7, $this->ascii($assessment->title));

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$slate500);
        $pdf->SetXY(15, 43);
        $pdf->Cell(0, 5, $this->ascii(
            'Student: '.($student->name ?? 'Unknown').
            '    |    Submitted: '.($submission->submitted_at?->format('d M Y H:i') ?? '—')
        ));

        if ($score?->finalized_at) {
            $pdf->SetXY(15, 49);
            $pdf->Cell(0, 5, $this->ascii(
                'Graded: '.$score->finalized_at->format('d M Y H:i')
            ));
        }

        // ── Score headline card ────────────────────────────────────────
        $cardY = 60;
        $pdf->SetFillColor(...$slate100);
        $pdf->SetDrawColor(...$slate300);
        $pdf->Rect(15, $cardY, 180, 30, 'DF');

        $raw = (float) ($score->raw_marks ?? 0);
        $max = (float) ($score->max_marks ?? $assessment->total_marks);
        $pct = $max > 0 ? round(($raw / $max) * 100, 1) : 0;
        $grade = $this->letterGrade($pct);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...$slate500);
        $pdf->SetXY(22, $cardY + 5);
        $pdf->Cell(0, 4, $this->ascii('MARKS'));

        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetTextColor(...$slate700);
        $pdf->SetXY(22, $cardY + 10);
        $pdf->Cell(60, 12, $this->ascii(
            rtrim(rtrim(number_format($raw, 2), '0'), '.').' / '.rtrim(rtrim(number_format($max, 2), '0'), '.')
        ));

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...$slate500);
        $pdf->SetXY(95, $cardY + 5);
        $pdf->Cell(0, 4, $this->ascii('PERCENTAGE'));

        $pdf->SetFont('Helvetica', 'B', 22);
        $pctColor = $pct >= 70 ? $emerald : ($pct >= 50 ? $amber : $red);
        $pdf->SetTextColor(...$pctColor);
        $pdf->SetXY(95, $cardY + 10);
        $pdf->Cell(40, 12, $this->ascii($pct.'%'));

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...$slate500);
        $pdf->SetXY(155, $cardY + 5);
        $pdf->Cell(0, 4, $this->ascii('GRADE'));

        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetTextColor(...$pctColor);
        $pdf->SetXY(155, $cardY + 10);
        $pdf->Cell(30, 12, $this->ascii($grade));

        // ── Rubric breakdown (if any) ──────────────────────────────────
        $y = $cardY + 38;
        $rubric = $assessment->rubric;

        if ($rubric && $rubric->criteria->isNotEmpty()) {
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(...$slate700);
            $pdf->SetXY(15, $y);
            $pdf->Cell(0, 6, $this->ascii('Rubric Breakdown'));
            $y += 8;

            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetTextColor(...$slate500);
            $pdf->SetFillColor(...$slate100);
            $pdf->SetDrawColor(...$slate300);
            $pdf->Rect(15, $y, 180, 7, 'F');
            $pdf->SetXY(17, $y + 1.5);
            $pdf->Cell(90, 4, $this->ascii('CRITERION'));
            $pdf->SetXY(107, $y + 1.5);
            $pdf->Cell(25, 4, $this->ascii('WEIGHT'));
            $pdf->SetXY(132, $y + 1.5);
            $pdf->Cell(30, 4, $this->ascii('SCORE'));
            $pdf->SetXY(162, $y + 1.5);
            $pdf->Cell(30, 4, $this->ascii('CONTRIB.'));
            $y += 7;

            $isWeighted = $rubric->criteria->contains(fn ($c) => $c->weightage !== null && (float) $c->weightage > 0);
            $storedCriteriaMarks = $score->criteria_marks ?? [];

            foreach ($rubric->criteria as $criterion) {
                if ($y > 250) {
                    $pdf->AddPage();
                    $y = 20;
                }

                $criterionMax = (float) $criterion->max_marks;
                $weight = $criterion->weightage !== null ? (float) $criterion->weightage : null;

                $criterionScore = $storedCriteriaMarks[(string) $criterion->id]
                    ?? $storedCriteriaMarks[$criterion->id]
                    ?? null;
                $criterionScore = $criterionScore !== null ? (float) $criterionScore : null;

                if ($isWeighted && $weight !== null && $weight > 0) {
                    $contribution = ($criterionScore !== null && $criterionMax > 0)
                        ? ($criterionScore / $criterionMax) * ($weight / 100) * $max
                        : 0.0;
                } else {
                    $contribution = $criterionScore ?? 0.0;
                }

                $pdf->SetTextColor(...$slate700);
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->SetXY(17, $y + 2);
                $pdf->Cell(88, 5, $this->truncate($this->ascii($criterion->title), 60));

                if ($criterion->description) {
                    $pdf->SetFont('Helvetica', '', 7);
                    $pdf->SetTextColor(...$slate500);
                    $pdf->SetXY(17, $y + 6.5);
                    $pdf->MultiCell(88, 3, $this->truncate($this->ascii($criterion->description), 120));
                }

                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetTextColor(...$slate700);
                $pdf->SetXY(107, $y + 2);
                $pdf->Cell(25, 5, $this->ascii($weight !== null ? rtrim(rtrim(number_format($weight, 2), '0'), '.').'%' : '-'));

                $pdf->SetXY(132, $y + 2);
                $scoreLabel = $criterionScore !== null
                    ? rtrim(rtrim(number_format($criterionScore, 2), '0'), '.').' / '.rtrim(rtrim(number_format($criterionMax, 2), '0'), '.')
                    : '- / '.rtrim(rtrim(number_format($criterionMax, 2), '0'), '.');
                $pdf->Cell(30, 5, $this->ascii($scoreLabel));

                $pdf->SetXY(162, $y + 2);
                $pdf->Cell(30, 5, $this->ascii(rtrim(rtrim(number_format($contribution, 2), '0'), '.').' pts'));

                $pdf->SetDrawColor(...$slate300);
                $pdf->Line(15, $y + 11, 195, $y + 11);

                $y += 12;
            }

            $y += 4;
        }

        // ── Feedback ───────────────────────────────────────────────────
        if (! empty($score->feedback)) {
            if ($y > 240) {
                $pdf->AddPage();
                $y = 20;
            }

            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(...$slate700);
            $pdf->SetXY(15, $y);
            $pdf->Cell(0, 6, $this->ascii('Lecturer Feedback'));
            $y += 8;

            $pdf->SetFillColor(...$slate100);
            $pdf->SetDrawColor(...$slate300);
            $pdf->Rect(15, $y, 180, 40, 'DF');

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(...$slate700);
            $pdf->SetXY(19, $y + 4);
            $pdf->MultiCell(172, 4.5, $this->ascii($score->feedback));
            $y += 46;
        }

        // ── Footer note ───────────────────────────────────────────────
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(...$slate500);
        $pdf->SetXY(15, 280);
        $pdf->Cell(0, 4, $this->ascii('This cover page was generated automatically by Lectura when the lecturer released the grade. The original submission follows on the next page.'));
    }

    protected function letterGrade(float $pct): string
    {
        if ($pct >= 80) return 'A';
        if ($pct >= 70) return 'B';
        if ($pct >= 60) return 'C';
        if ($pct >= 50) return 'D';
        return 'F';
    }

    /**
     * FPDF core fonts only support Windows-1252. Fall back to an ASCII
     * transliteration for anything outside that range.
     */
    protected function ascii(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    protected function truncate(string $text, int $max): string
    {
        return strlen($text) > $max ? substr($text, 0, $max - 3).'...' : $text;
    }
}
