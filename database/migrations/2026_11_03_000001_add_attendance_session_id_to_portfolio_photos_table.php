<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_photos', function (Blueprint $table) {
            $table->foreignId('attendance_session_id')->nullable()->after('section_id')
                ->constrained('attendance_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_session_id');
        });
    }
};
