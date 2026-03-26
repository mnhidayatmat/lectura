<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('credit_hours')->nullable();
            $table->unsignedTinyInteger('num_weeks')->default(14);
            $table->string('teaching_mode', 20)->default('face_to_face');
            $table->json('format')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('custom_start_date')->nullable();
            $table->date('custom_end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'academic_term_id']);
            $table->index('lecturer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
