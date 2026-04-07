<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_submission_id')->constrained('assessment_submissions')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size_bytes');
            $table->string('storage_path');
            $table->string('drive_file_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_submission_files');
    }
};
