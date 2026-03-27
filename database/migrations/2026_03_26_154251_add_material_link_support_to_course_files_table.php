<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_files', function (Blueprint $table) {
            $table->string('material_type', 10)->default('file')->after('uploaded_by');
            $table->string('url', 2048)->nullable()->after('storage_path');
            $table->unsignedInteger('sort_order')->default(0)->after('week_number');
        });

        // Make columns nullable for link-type materials
        Schema::table('course_files', function (Blueprint $table) {
            $table->foreignId('course_folder_id')->nullable()->change();
            $table->string('storage_path', 500)->nullable()->change();
            $table->unsignedBigInteger('file_size_bytes')->nullable()->change();
            $table->string('file_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_files', function (Blueprint $table) {
            $table->dropColumn(['material_type', 'url', 'sort_order']);
        });
    }
};
