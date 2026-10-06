<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->timestamp('required_by')->nullable()->after('publish_at');
            $table->boolean('notify_students')->default(true)->after('required_by');
            $table->timestamp('announced_at')->nullable()->after('notify_students');
        });

        Schema::create('episode_scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('title');
            $table->unsignedInteger('start_seconds');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['episode_id', 'start_seconds']);
        });

        Schema::create('episode_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('at_seconds');
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['episode_id', 'at_seconds']);
        });

        Schema::create('episode_check_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_check_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('episode_check_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('episode_check_option_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->boolean('first_is_correct')->default(false);
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['episode_check_id', 'user_id']);
        });

        Schema::create('episode_captions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->string('language', 5);
            $table->string('disk', 30);
            $table->string('path');
            $table->timestamps();

            $table->unique(['episode_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_captions');
        Schema::dropIfExists('episode_check_answers');
        Schema::dropIfExists('episode_check_options');
        Schema::dropIfExists('episode_checks');
        Schema::dropIfExists('episode_scenes');

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn(['required_by', 'notify_students', 'announced_at']);
        });
    }
};
