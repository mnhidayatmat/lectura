<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_group_files', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('storage_path');
            $table->string('drive_web_link', 500)->nullable()->after('drive_file_id');
        });

        Schema::table('student_group_folders', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('student_group_files', function (Blueprint $table) {
            $table->dropColumn(['drive_file_id', 'drive_web_link']);
        });

        Schema::table('student_group_folders', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
    }
};
