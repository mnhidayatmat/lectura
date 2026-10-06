<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->boolean('allow_download')->default(true)->after('notify_students');
            $table->timestamp('last_reminded_at')->nullable()->after('announced_at');
        });

        Schema::create('episode_rewinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('from_seconds');
            $table->unsignedInteger('to_seconds');
            $table->timestamp('created_at')->nullable();

            $table->index(['episode_id', 'to_seconds']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_rewinds');

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn(['allow_download', 'last_reminded_at']);
        });
    }
};
