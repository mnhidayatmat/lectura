<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episode_clips', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('poster_path');
        });
    }

    public function down(): void
    {
        Schema::table('episode_clips', function (Blueprint $table) {
            $table->dropColumn('publish_at');
        });
    }
};
