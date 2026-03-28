<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_files', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('course_files', function (Blueprint $table) {
            $table->dropColumn('drive_file_id');
        });
    }
};
