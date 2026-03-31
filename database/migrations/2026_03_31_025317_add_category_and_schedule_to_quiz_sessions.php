<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('category', 20)->default('live')->after('join_code');
            $table->dateTime('available_from')->nullable()->after('settings');
            $table->dateTime('available_until')->nullable()->after('available_from');

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'available_from', 'available_until']);
        });
    }
};
