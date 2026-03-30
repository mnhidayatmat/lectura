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
            $table->json('content_meta')->nullable()->after('poll_config');
        });
    }

    public function down(): void
    {
        Schema::table('active_learning_activities', function (Blueprint $table) {
            $table->dropColumn('content_meta');
        });
    }
};
