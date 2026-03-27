<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('provider_type', 30); // anthropic, openai, google, custom
            $table->text('api_key')->nullable();
            $table->string('api_base_url', 500)->nullable();
            $table->string('model');
            $table->unsignedInteger('max_tokens')->default(4096);
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->decimal('top_p', 3, 2)->default(1.00);
            $table->unsignedInteger('timeout_seconds')->default(120);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
