<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Folders for file organisation within a group workspace
        Schema::create('student_group_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index('student_group_id');
        });

        // Add folder support to existing files table
        Schema::table('student_group_files', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()
                ->constrained('student_group_folders')->nullOnDelete()
                ->after('student_group_id');
        });

        // Tasks / timeline items
        Schema::create('group_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo'); // todo, in_progress, done
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['student_group_id', 'status']);
        });

        // Minutes of meeting
        Schema::create('group_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('meeting_date');
            $table->string('title');
            $table->longText('body');
            $table->string('file_path', 500)->nullable();
            $table->string('file_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_group_id', 'meeting_date']);
        });

        // Anonymous sleeping partner reports
        Schema::create('group_sleeping_partner_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            // No reporter_id — reports are fully anonymous
            $table->text('description');
            $table->boolean('is_reviewed')->default(false);
            $table->timestamps();
            $table->index('student_group_id');
        });

        // Voting rounds
        Schema::create('group_vote_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('open'); // open, closed
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['student_group_id', 'status']);
        });

        // Individual votes cast in a round
        Schema::create('group_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_round_id')->constrained('group_vote_rounds')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('nominee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['vote_round_id', 'voter_id']); // one vote per member per round
        });

        // Member swap requests
        Schema::create('group_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->foreignId('to_group_id')->constrained('student_groups')->cascadeOnDelete();
            // Status flow: pending_member → pending_lecturer → approved | rejected
            $table->string('status', 30)->default('pending_member');
            $table->text('reject_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['requester_id', 'status']);
            $table->index(['target_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_swap_requests');
        Schema::dropIfExists('group_votes');
        Schema::dropIfExists('group_vote_rounds');
        Schema::dropIfExists('group_sleeping_partner_reports');
        Schema::dropIfExists('group_minutes');
        Schema::dropIfExists('group_tasks');

        Schema::table('student_group_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('student_group_folders');
    }
};
