<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('company_a_name')->default('Company A');
            $table->string('company_b_name')->default('Company B');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tender_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // student, leader or deputy. Leader and deputy sit outside both companies.
            $table->string('role', 16)->default('student');
            $table->char('company', 1)->nullable();
            $table->unsignedTinyInteger('group_no')->nullable();
            $table->boolean('is_md')->default(false);
            // active or consultant (retrenched); a consultant keeps company and group.
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['tender_game_id', 'user_id']);
        });

        Schema::create('tender_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_game_id')->constrained()->cascadeOnDelete();
            $table->string('code', 4);
            $table->unsignedTinyInteger('week')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 16)->default('setup');
            $table->char('first_company', 1)->nullable();
            $table->unsignedTinyInteger('lead_group_a')->nullable();
            $table->unsignedTinyInteger('lead_group_b')->nullable();
            $table->text('change_order_note')->nullable();
            $table->timestamp('scoring_started_at')->nullable();
            $table->timestamp('reconcile_started_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('announced_at')->nullable();
            $table->char('winner', 1)->nullable();
            $table->string('tie_break', 16)->nullable();
            $table->char('sudden_death_winner', 1)->nullable();
            $table->json('result')->nullable();
            $table->string('review_status', 16)->nullable();
            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['tender_game_id', 'code']);
        });

        Schema::create('tender_judges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_token_hash', 64)->nullable()->unique();
            $table->char('company', 1)->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('conflict_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('paper_photo_path')->nullable();
            $table->timestamps();

            $table->index(['tender_session_id', 'slot']);
        });

        Schema::create('tender_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_judge_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 24);
            $table->unsignedTinyInteger('level_a')->nullable();
            $table->unsignedTinyInteger('level_b')->nullable();
            $table->unsignedTinyInteger('level_a_after')->nullable();
            $table->unsignedTinyInteger('level_b_after')->nullable();
            $table->string('evidence', 500)->nullable();
            $table->timestamps();

            $table->unique(['tender_judge_id', 'criterion']);
        });

        Schema::create('tender_pitch_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_judge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultant_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('relevant');
            $table->unsignedTinyInteger('credible');
            $table->unsignedTinyInteger('standards');
            $table->unsignedTinyInteger('communication');
            $table->timestamps();

            $table->unique(['tender_judge_id', 'consultant_id']);
        });

        Schema::create('tender_roster_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_session_id')->constrained()->cascadeOnDelete();
            // retrench, at_floor, hire, hire_back or pass
            $table->string('kind', 16);
            $table->char('company', 1);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tender_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('company', 1)->nullable();
            $table->text('body');
            $table->string('decision', 16)->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_appeals');
        Schema::dropIfExists('tender_roster_events');
        Schema::dropIfExists('tender_pitch_scores');
        Schema::dropIfExists('tender_scores');
        Schema::dropIfExists('tender_judges');
        Schema::dropIfExists('tender_sessions');
        Schema::dropIfExists('tender_members');
        Schema::dropIfExists('tender_games');
    }
};
