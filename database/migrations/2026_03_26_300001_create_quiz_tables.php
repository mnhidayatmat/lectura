<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_type', 20); // mcq, true_false, short_answer
            $table->text('text');
            $table->text('explanation')->nullable();
            $table->string('difficulty', 10)->nullable(); // easy, medium, hard
            $table->unsignedSmallInteger('time_limit_seconds')->default(30);
            $table->decimal('points', 5, 2)->default(1.00);
            $table->json('tags')->nullable();
            $table->boolean('is_bank')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'created_by', 'is_bank']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 10);
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('join_code', 8)->unique();
            $table->string('mode', 15)->default('formative'); // formative, participation, graded
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 15)->default('waiting'); // waiting, active, reviewing, ended
            $table->json('settings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('section_id');
        });

        Schema::create('quiz_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('status', 10)->default('pending'); // pending, active, closed
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::create('quiz_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 100)->nullable();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_session_id', 'user_id']);
        });

        Schema::create('quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_participant_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->foreignId('selected_option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 5, 2)->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();

            $table->unique(['quiz_session_question_id', 'quiz_participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_responses');
        Schema::dropIfExists('quiz_participants');
        Schema::dropIfExists('quiz_session_questions');
        Schema::dropIfExists('quiz_sessions');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
