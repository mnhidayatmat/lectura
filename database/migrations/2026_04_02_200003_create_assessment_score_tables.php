<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('raw_marks', 8, 2);
            $table->decimal('max_marks', 8, 2);
            $table->decimal('weighted_marks', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_computed')->default(true);
            $table->timestamps();

            $table->unique(['assessment_id', 'user_id']);
        });

        Schema::create('assessment_clo_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_learning_outcome_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks', 8, 2);
            $table->decimal('max_marks', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'course_learning_outcome_id', 'user_id'], 'assessment_clo_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_clo_scores');
        Schema::dropIfExists('assessment_scores');
    }
};
