<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            // Percentage contribution of this criterion to the final mark (0-100).
            // Null means "unweighted" — the rubric falls back to a plain sum of criterion marks.
            $table->decimal('weightage', 5, 2)->nullable()->after('max_marks');
        });
    }

    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->dropColumn('weightage');
        });
    }
};
