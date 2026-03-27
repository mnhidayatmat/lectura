<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_learning_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_topic_id')->nullable()->constrained('course_topics')->nullOnDelete();
            $table->tinyInteger('week_number')->unsigned()->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->smallInteger('duration_minutes')->unsigned()->default(90);
            $table->string('status', 15)->default('draft'); // draft, published, archived
            $table->string('source', 15)->default('manual'); // manual, ai_generated
            $table->string('ai_generation_status', 15)->nullable(); // pending, processing, completed, failed
            $table->timestamp('ai_generated_at')->nullable();
            $table->text('ai_prompt_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'course_id']);
            $table->index(['course_id', 'week_number']);
        });

        Schema::create('active_learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_learning_plan_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->string('title');
            $table->string('type', 20); // individual, pair, group, discussion, reflection, whole_class
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->smallInteger('duration_minutes')->unsigned()->nullable();
            $table->json('clo_ids')->nullable();
            $table->json('materials')->nullable();
            $table->string('grouping_strategy', 20)->nullable(); // random, attendance_based, manual
            $table->tinyInteger('max_group_size')->unsigned()->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();

            $table->index(['active_learning_plan_id', 'sort_order']);
        });

        Schema::create('active_learning_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_learning_activity_id')->constrained('active_learning_activities')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('color_tag', 7)->nullable();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->index('active_learning_activity_id');
        });

        Schema::create('active_learning_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_learning_group_id')->constrained('active_learning_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // member, facilitator, reporter, scribe
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['active_learning_group_id', 'user_id'], 'alg_member_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_learning_group_members');
        Schema::dropIfExists('active_learning_groups');
        Schema::dropIfExists('active_learning_activities');
        Schema::dropIfExists('active_learning_plans');
    }
};
