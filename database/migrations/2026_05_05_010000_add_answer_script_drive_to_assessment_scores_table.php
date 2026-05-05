<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->string('answer_script_drive_file_id')->nullable()->after('answer_script_filename');
            $table->string('answer_script_drive_link', 1000)->nullable()->after('answer_script_drive_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropColumn(['answer_script_drive_file_id', 'answer_script_drive_link']);
        });
    }
};
