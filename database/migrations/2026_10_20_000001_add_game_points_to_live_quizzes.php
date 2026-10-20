<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('opened_at_ms')->nullable()->after('opened_at');
        });

        Schema::table('quiz_participants', function (Blueprint $table) {
            $table->unsignedInteger('game_score')->default(0)->after('total_score');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->unsignedInteger('game_points')->default(0)->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn('game_points');
        });

        Schema::table('quiz_participants', function (Blueprint $table) {
            $table->dropColumn('game_score');
        });

        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->dropColumn('opened_at_ms');
        });
    }
};
