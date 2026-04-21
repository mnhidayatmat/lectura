<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // lecture, lab, tutorial, group_activity, presentation, workshop, field_trip, other
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedInteger('file_size_bytes');
            $table->integer('week_number')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'course_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_photos');
    }
};
