<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('instruction_file_path', 500)->nullable()->after('answer_scheme_filename');
            $table->string('instruction_filename')->nullable()->after('instruction_file_path');
            $table->string('instruction_drive_file_id')->nullable()->after('instruction_filename');
            $table->string('instruction_drive_web_link', 500)->nullable()->after('instruction_drive_file_id');
            $table->string('answer_scheme_drive_file_id')->nullable()->after('answer_scheme_filename');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn([
                'instruction_file_path',
                'instruction_filename',
                'instruction_drive_file_id',
                'instruction_drive_web_link',
                'answer_scheme_drive_file_id',
            ]);
        });
    }
};
