<?php

declare(strict_types=1);

namespace App\View;

use App\Models\Course;

/**
 * A stable colour identity per course, so the same course looks the same in
 * the picker, sidebar, switcher and course home. Tailwind scans this file.
 */
class CourseAccent
{
    protected const PALETTE = [
        ['gradient' => 'from-indigo-500 to-violet-600', 'hero' => 'from-indigo-600 via-indigo-700 to-violet-800', 'ring' => 'ring-indigo-400', 'soft' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'],
        ['gradient' => 'from-teal-500 to-emerald-600', 'hero' => 'from-teal-600 via-teal-700 to-emerald-800', 'ring' => 'ring-teal-400', 'soft' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300'],
        ['gradient' => 'from-rose-500 to-orange-500', 'hero' => 'from-rose-600 via-rose-700 to-orange-700', 'ring' => 'ring-rose-400', 'soft' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'],
        ['gradient' => 'from-sky-500 to-blue-600', 'hero' => 'from-sky-600 via-sky-700 to-blue-800', 'ring' => 'ring-sky-400', 'soft' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'],
        ['gradient' => 'from-amber-500 to-orange-600', 'hero' => 'from-amber-600 via-orange-600 to-orange-800', 'ring' => 'ring-amber-400', 'soft' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
        ['gradient' => 'from-fuchsia-500 to-purple-600', 'hero' => 'from-fuchsia-600 via-purple-700 to-purple-800', 'ring' => 'ring-fuchsia-400', 'soft' => 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300'],
        ['gradient' => 'from-cyan-500 to-sky-600', 'hero' => 'from-cyan-600 via-cyan-700 to-sky-800', 'ring' => 'ring-cyan-400', 'soft' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300'],
        ['gradient' => 'from-emerald-500 to-lime-600', 'hero' => 'from-emerald-600 via-emerald-700 to-green-800', 'ring' => 'ring-emerald-400', 'soft' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'],
    ];

    public static function for(Course $course): array
    {
        return self::PALETTE[$course->id % count(self::PALETTE)];
    }

    public static function monogram(Course $course): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', (string) $course->code);

        return strtoupper(substr($letters ?: (string) $course->code, 0, 3)) ?: 'C';
    }

    /** The course number part of the code (e.g. "2232" from "BTD2232"). */
    public static function number(Course $course): string
    {
        preg_match('/\d+/', (string) $course->code, $m);

        return $m[0] ?? '';
    }
}
