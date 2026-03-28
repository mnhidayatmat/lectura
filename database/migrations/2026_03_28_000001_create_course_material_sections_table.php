<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_material_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
        });

        Schema::table('course_files', function (Blueprint $table) {
            $table->foreignId('material_section_id')
                ->nullable()
                ->after('section_id')
                ->constrained('course_material_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_files', function (Blueprint $table) {
            $table->dropForeign(['material_section_id']);
            $table->dropColumn('material_section_id');
        });

        Schema::dropIfExists('course_material_sections');
    }
};
