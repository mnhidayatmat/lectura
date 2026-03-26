<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_type', 20); // lecture, tutorial, lab, extra, replacement
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->string('qr_secret', 64);
            $table->string('qr_mode', 10)->default('rotating'); // rotating, fixed
            $table->unsignedSmallInteger('qr_rotation_seconds')->default(30);
            $table->unsignedSmallInteger('late_threshold_minutes')->default(15);
            $table->string('status', 10)->default('active'); // active, ended
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
