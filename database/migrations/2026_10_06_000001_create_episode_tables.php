<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_series_id')->constrained('course_series')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('episode_number');
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->string('title');
            $table->text('synopsis')->nullable();
            $table->string('status', 15)->default('draft'); // draft, published
            $table->timestamp('publish_at')->nullable();
            $table->string('video_disk', 30);
            $table->string('video_path');
            $table->string('video_mime', 60)->default('video/mp4');
            $table->unsignedBigInteger('video_size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('poster_path')->nullable();
            $table->timestamps();

            $table->index(['course_series_id', 'episode_number']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('episode_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('furthest_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();

            $table->unique(['episode_id', 'user_id']);
            $table->index(['user_id', 'last_watched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_progress');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('course_series');
    }
};
