<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original "add assessment_id" migration skipped relaxing
     * rubrics.assignment_id to nullable on SQLite, which meant any rubric
     * attached to an Assessment (instead of an Assignment) hit a NOT NULL
     * constraint error at insert time. Force the column to nullable on
     * every driver.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot ALTER a column in place. Rebuild the table with
            // assignment_id nullable while preserving existing rows and FKs.
            $hasAssessmentCol = Schema::hasColumn('rubrics', 'assessment_id');

            Schema::create('rubrics_tmp', function (Blueprint $table) use ($hasAssessmentCol) {
                $table->id();
                $table->foreignId('assignment_id')->nullable()->constrained()->cascadeOnDelete();
                if ($hasAssessmentCol) {
                    $table->foreignId('assessment_id')->nullable()->constrained('assessments')->cascadeOnDelete();
                }
                $table->string('type', 15)->default('matrix');
                $table->timestamps();
            });

            $cols = $hasAssessmentCol
                ? 'id, assignment_id, assessment_id, type, created_at, updated_at'
                : 'id, assignment_id, type, created_at, updated_at';

            DB::statement("INSERT INTO rubrics_tmp ({$cols}) SELECT {$cols} FROM rubrics");

            Schema::drop('rubrics');
            Schema::rename('rubrics_tmp', 'rubrics');

            return;
        }

        Schema::table('rubrics', function (Blueprint $table) {
            $table->foreignId('assignment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // no-op: we don't want to force NOT NULL back, that would break rubrics
        // that belong to an assessment rather than an assignment.
    }
};
