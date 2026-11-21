<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_submission_files', function (Blueprint $table) {
            $table->string('file_type', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submission_files', function (Blueprint $table) {
            $table->string('file_type', 50)->change();
        });
    }
};
