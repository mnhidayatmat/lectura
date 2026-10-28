<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->foreignId('quiz_session_id')->nullable()->after('poster_path')
                ->constrained('quiz_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quiz_session_id');
        });
    }
};
