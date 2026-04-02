<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->string('method', 20)->nullable();
            $table->decimal('weightage', 5, 2);
            $table->decimal('total_marks', 8, 2);
            $table->string('bloom_level', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 15)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assessment_clos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_learning_outcome_id')->constrained()->cascadeOnDelete();
            $table->decimal('weightage', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'course_learning_outcome_id'], 'assessment_clo_unique');
        });

        Schema::create('assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('assessable_type');
            $table->unsignedBigInteger('assessable_id');
            $table->decimal('contribution_percentage', 5, 2)->default(100.00);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['assessable_type', 'assessable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_items');
        Schema::dropIfExists('assessment_clos');
        Schema::dropIfExists('assessments');
    }
};
