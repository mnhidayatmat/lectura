<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('clo_ids')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_topics');
    }
};
