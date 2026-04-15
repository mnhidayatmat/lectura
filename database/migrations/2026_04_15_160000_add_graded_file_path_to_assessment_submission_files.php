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
            // Path of the "graded" copy — original PDF with a stamped grade
            // report as page 1. Null when the submission is ungraded, the
            // source file is not a PDF, or stamping failed and we fell back
            // to serving the original.
            $table->string('graded_file_path', 500)->nullable()->after('storage_path');
            $table->timestamp('graded_at')->nullable()->after('graded_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submission_files', function (Blueprint $table) {
            $table->dropColumn(['graded_file_path', 'graded_at']);
        });
    }
};
