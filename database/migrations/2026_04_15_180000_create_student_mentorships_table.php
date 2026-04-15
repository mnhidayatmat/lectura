<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_mentorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['academic_tutor', 'li_supervisor']);
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'lecturer_id', 'student_id', 'role', 'academic_term_id'],
                'student_mentorships_unique'
            );
            $table->index(['tenant_id', 'lecturer_id', 'role']);
            $table->index(['tenant_id', 'student_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_mentorships');
    }
};
