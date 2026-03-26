<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 15)->default('individual'); // individual, group
            $table->decimal('total_marks', 8, 2);
            $table->timestamp('deadline')->nullable();
            $table->boolean('allow_resubmission')->default(false);
            $table->unsignedTinyInteger('max_resubmissions')->default(0);
            $table->string('marking_mode', 15)->default('manual'); // manual, ai_assisted
            $table->text('answer_scheme')->nullable();
            $table->string('status', 15)->default('draft'); // draft, published, closed, graded
            $table->json('clo_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_id');
        });

        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 15)->default('matrix'); // matrix, free_text
            $table->timestamps();
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('max_marks', 5, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_criteria_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->decimal('marks', 5, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('submission_number')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamp('submitted_at');
            $table->string('status', 20)->default('submitted'); // submitted, ai_processing, ai_completed, marking, graded
            $table->timestamps();

            $table->index('assignment_id');
            $table->index('user_id');
        });

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size_bytes');
            $table->string('storage_path', 500);
            $table->string('status', 15)->default('uploaded'); // uploaded, synced, failed
            $table->timestamps();
        });

        Schema::create('marking_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rubric_criteria_id')->nullable()->constrained('rubric_criteria')->nullOnDelete();
            $table->string('question_ref', 50)->nullable();
            $table->text('extracted_answer')->nullable();
            $table->decimal('suggested_marks', 5, 2)->nullable();
            $table->decimal('max_marks', 5, 2)->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('status', 15)->default('pending'); // pending, accepted, modified, rejected
            $table->decimal('final_marks', 5, 2)->nullable();
            $table->text('lecturer_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('submission_id');
        });

        Schema::create('student_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_marks', 8, 2);
            $table->decimal('max_marks', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade', 5)->nullable();
            $table->boolean('is_final')->default(false);
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
        });

        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('strengths')->nullable();
            $table->text('missing_points')->nullable();
            $table->text('misconceptions')->nullable();
            $table->text('revision_advice')->nullable();
            $table->text('improvement_tips')->nullable();
            $table->string('performance_level', 15)->nullable(); // low, average, advanced
            $table->boolean('ai_generated')->default(false);
            $table->boolean('is_released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
        Schema::dropIfExists('student_marks');
        Schema::dropIfExists('marking_suggestions');
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('rubric_levels');
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
        Schema::dropIfExists('assignments');
    }
};
