<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episode_clip_views', function (Blueprint $table) {
            // First time the student played the clip to its end; the app's feed skips it after that.
            $table->timestamp('completed_at')->nullable()->after('last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('episode_clip_views', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
