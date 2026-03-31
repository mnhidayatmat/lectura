<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('policy_level');
            $table->unsignedInteger('absence_count');
            $table->unsignedInteger('total_sessions');
            $table->decimal('absence_percentage', 5, 2);
            $table->timestamp('created_at')->nullable();

            $table->unique(['course_id', 'user_id', 'policy_level']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_warnings');
    }
};
