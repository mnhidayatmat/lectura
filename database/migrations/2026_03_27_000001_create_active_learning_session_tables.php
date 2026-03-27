<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add response configuration to activities
        Schema::table('active_learning_activities', function (Blueprint $table) {
            $table->string('response_mode', 15)->default('individual')->after('max_group_size');
            $table->string('response_type', 15)->default('none')->after('response_mode');
            $table->json('poll_config')->nullable()->after('response_type');
        });

        // Live sessions
        Schema::create('active_learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('active_learning_plans')->cascadeOnDelete();
            $table->string('status', 15)->default('not_started');
            $table->string('join_code', 6)->unique();
            $table->foreignId('current_activity_id')->nullable()->constrained('active_learning_activities')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->json('summary_data')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['plan_id', 'status']);
        });

        // Session participants (students who joined)
        Schema::create('active_learning_session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('active_learning_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
        });

        // Student responses to activities
        Schema::create('active_learning_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('active_learning_sessions')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('active_learning_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('active_learning_groups')->nullOnDelete();
            $table->string('response_type', 15);
            $table->json('response_data');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['session_id', 'activity_id', 'user_id']);
            $table->index(['session_id', 'activity_id', 'group_id']);
        });

        // Poll options for MCQ activities
        Schema::create('active_learning_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('active_learning_activities')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->index(['activity_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_learning_poll_options');
        Schema::dropIfExists('active_learning_responses');
        Schema::dropIfExists('active_learning_session_participants');
        Schema::dropIfExists('active_learning_sessions');

        Schema::table('active_learning_activities', function (Blueprint $table) {
            $table->dropColumn(['response_mode', 'response_type', 'poll_config']);
        });
    }
};
