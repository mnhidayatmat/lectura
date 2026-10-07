<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episode_section_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_reminded_at');

            $table->unique(['episode_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_section_reminders');
    }
};
