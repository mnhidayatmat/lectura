<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Tied to the Sanctum token the app signed in with, so signing out (or
            // deleting the account, which revokes every token) stops the pushes.
            $table->foreignId('personal_access_token_id')->nullable()
                ->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
