<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade');
            $table->string('suggestion_type', 30);
            $table->string('status', 20)->default('processing');
            $table->json('content')->nullable();
            $table->text('prompt_summary')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'suggestion_type']);
            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_ai_suggestions');
    }
};
