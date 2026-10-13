<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episode_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('clip_number');
            $table->string('title');
            $table->string('topic')->nullable();
            $table->text('takeaway')->nullable();
            $table->unsignedInteger('start_seconds')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('video_disk', 30);
            $table->string('video_path');
            $table->string('video_mime', 60)->default('video/mp4');
            $table->unsignedBigInteger('video_size_bytes')->nullable();
            $table->string('poster_path')->nullable();
            $table->timestamps();

            $table->unique(['episode_id', 'clip_number']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_clips');
    }
};
