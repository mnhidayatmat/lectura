<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('structure');
            $table->boolean('is_default')->default(false);
            $table->string('scope', 15); // global, tenant, personal
            $table->timestamps();
        });

        Schema::create('course_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('course_folders')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'parent_id']);
        });

        Schema::create('course_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size_bytes');
            $table->string('storage_path', 500);
            $table->text('description')->nullable();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_folder_id');
            $table->index('course_id');
        });

        Schema::create('file_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_file_id')->constrained()->cascadeOnDelete();
            $table->string('tag_type', 30); // week, clo, assessment_type, topic, evidence_type
            $table->string('tag_value');

            $table->index('course_file_id');
            $table->index(['tag_type', 'tag_value']);
        });

        Schema::create('compliance_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('compliance_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('rule_type', 20); // file_exists, folder_not_empty, tag_exists, custom
            $table->json('rule_config')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_checklist_items');
        Schema::dropIfExists('compliance_checklists');
        Schema::dropIfExists('file_tags');
        Schema::dropIfExists('course_files');
        Schema::dropIfExists('course_folders');
        Schema::dropIfExists('folder_templates');
    }
};
