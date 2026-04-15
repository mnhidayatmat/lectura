<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            // Per-rubric-criterion marks captured at grading time, keyed by
            // criterion id. Null for scores entered without a rubric.
            $table->json('criteria_marks')->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropColumn('criteria_marks');
        });
    }
};
