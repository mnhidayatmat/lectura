<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episode_clips', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
        });

        Schema::create('episode_clip_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_clip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('plays')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['episode_clip_id', 'user_id']);
        });

        foreach (['episode_clip_likes', 'episode_clip_saves'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('episode_clip_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['episode_clip_id', 'user_id']);
            });
        }

        Schema::create('clip_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('episode_clip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('clip_comments')->cascadeOnDelete();
            $table->foreignId('reply_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['episode_clip_id', 'parent_id']);
        });

        Schema::create('clip_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clip_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['clip_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_comment_likes');
        Schema::dropIfExists('clip_comments');
        Schema::dropIfExists('episode_clip_saves');
        Schema::dropIfExists('episode_clip_likes');
        Schema::dropIfExists('episode_clip_views');
        Schema::table('episode_clips', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'likes_count', 'comments_count', 'saves_count']);
        });
    }
};
