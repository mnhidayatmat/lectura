<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('active_learning_activities', function (Blueprint $table) {
            $table->foreignId('quiz_session_id')->nullable()->after('content_meta')
                ->constrained('quiz_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('active_learning_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quiz_session_id');
        });
    }
};
