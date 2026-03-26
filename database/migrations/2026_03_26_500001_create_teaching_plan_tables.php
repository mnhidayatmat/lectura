<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 15)->default('draft'); // draft, published, archived
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('change_note')->nullable();
            $table->timestamps();

            $table->index('course_id');
        });

        Schema::create('teaching_plan_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->string('topic')->nullable();
            $table->text('lesson_flow')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('active_learning')->nullable();
            $table->json('online_alternatives')->nullable();
            $table->json('formative_checks')->nullable();
            $table->json('time_allocation')->nullable();
            $table->text('assessment_notes')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();

            $table->index(['teaching_plan_id', 'week_number']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module', 30); // teaching_plan, marking, feedback, activity
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('response_status', 10); // success, failed, timeout
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('teaching_plan_weeks');
        Schema::dropIfExists('teaching_plans');
    }
};
