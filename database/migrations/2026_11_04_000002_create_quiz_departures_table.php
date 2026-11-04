<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_session_question_id')->constrained()->cascadeOnDelete();
            $table->string('client', 8);
            $table->timestamp('created_at')->nullable();

            $table->unique(['quiz_participant_id', 'quiz_session_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_departures');
    }
};
