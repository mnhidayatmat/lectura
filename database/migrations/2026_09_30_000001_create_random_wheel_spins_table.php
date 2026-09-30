<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('random_wheel_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spun_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_id')->constrained('users')->cascadeOnDelete();
            $table->json('candidates');
            $table->unsignedSmallInteger('winner_index');
            $table->unsignedTinyInteger('turns');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('spun_at', 3);
            $table->timestamps();

            $table->index(['section_id', 'spun_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('random_wheel_spins');
    }
};
