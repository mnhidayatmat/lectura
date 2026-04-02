<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index so activeQuestion() (WHERE quiz_session_id = ? AND status = 'active')
        // uses a covering index instead of a full table scan.
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->index(['quiz_session_id', 'status'], 'qsq_session_status_idx');
        });

        // Index so SUM/WHERE queries on quiz_participant_id in quiz_responses are fast.
        // Also used by the end() score recalculation loop.
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->index('quiz_participant_id', 'qr_participant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->dropIndex('qsq_session_status_idx');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropIndex('qr_participant_idx');
        });
    }
};
