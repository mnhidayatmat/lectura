<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
    }
};
