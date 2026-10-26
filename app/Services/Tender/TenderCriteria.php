<?php

declare(strict_types=1);

namespace App\Services\Tender;

/**
 * The scoring table from the Student Guide, Section 6: each session is worth 100
 * marks, the criteria due that week plus three standing ones.
 */
final class TenderCriteria
{
    public const SESSIONS = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'GF'];

    public const DEFAULT_WEEKS = ['S1' => 5, 'S2' => 7, 'S3' => 8, 'S4' => 10, 'S5' => 11, 'S6' => 13, 'GF' => 14];

    public const LABELS = [
        'intro' => 'Introduction and Scope',
        'plant_3d' => '3D Plant Model',
        'layout' => 'Plant Layout Plan',
        'pid_iso' => 'P&ID and Isometrics',
        'software' => 'Software Proficiency',
        'analysis' => 'Analysis Accuracy',
        'fabrication' => 'Spool Fabrication',
        'compliance' => 'Compliance slide',
        'change_order' => 'Change Order response',
        'defence' => 'Proposal defence',
    ];

    /** Company criteria go to every company member; the rest are a first read for the lecturer. */
    public const COMPANY_CRITERIA = ['intro', 'plant_3d', 'layout', 'pid_iso'];

    /** Tie-break order after equal company marks; then sudden death. */
    public const TIE_BREAK = ['compliance', 'defence', 'change_order'];

    private const STANDING = ['compliance' => 10.0, 'change_order' => 10.0, 'defence' => 10.0];

    private const DUE = [
        'S1' => ['intro' => 35.0, 'plant_3d' => 35.0],
        'S2' => ['plant_3d' => 14.0, 'layout' => 14.0, 'pid_iso' => 42.0],
        'S3' => ['software' => 35.0, 'analysis' => 35.0],
        'S4' => ['pid_iso' => 21.0, 'software' => 28.0, 'fabrication' => 21.0],
        'S5' => ['software' => 25.5, 'analysis' => 25.5, 'fabrication' => 19.0],
        'S6' => ['software' => 35.0, 'analysis' => 35.0],
        'GF' => ['software' => 35.0, 'analysis' => 35.0],
    ];

    /** @return array<string, float> */
    public static function forSession(string $code): array
    {
        return (self::DUE[$code] ?? []) + self::STANDING;
    }

    public static function label(string $criterion): string
    {
        return self::LABELS[$criterion] ?? $criterion;
    }

    public static function sessionLabel(string $code): string
    {
        return $code === 'GF' ? 'Grand Final' : 'Session '.substr($code, 1);
    }

    /** Level 0 (no slide) is only allowed on the compliance row. */
    public static function minLevel(string $criterion): int
    {
        return $criterion === 'compliance' ? 0 : 1;
    }
}
