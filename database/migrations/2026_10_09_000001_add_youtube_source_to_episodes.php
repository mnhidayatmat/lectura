<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->string('source', 10)->default('upload')->after('status');
            $table->string('youtube_video_id', 20)->nullable()->after('source');
            $table->string('video_disk', 30)->nullable()->change();
            $table->string('video_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn(['source', 'youtube_video_id']);
        });
    }
};
