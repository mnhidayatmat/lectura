<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_group_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // lecture, lab, tutorial
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('creation_method', 20)->default('manual'); // manual, random, ai_suggested
            $table->unsignedTinyInteger('max_group_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'course_id']);
            $table->index(['course_id', 'type']);
        });

        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_set_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color_tag', 7)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('student_group_set_id');
        });

        Schema::create('student_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // member, leader
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['student_group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('student_group_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('student_group_posts')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_group_id', 'created_at']);
        });

        Schema::create('student_group_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size_bytes');
            $table->string('storage_path', 500);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_files');
        Schema::dropIfExists('student_group_posts');
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('student_group_sets');
    }
};
