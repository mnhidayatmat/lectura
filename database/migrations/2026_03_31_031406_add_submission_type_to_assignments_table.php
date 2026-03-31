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
            $table->string('submission_type', 20)->default('file')->after('marking_mode');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->longText('text_content')->nullable()->after('notes');
        });

    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('submission_type');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('text_content');
        });
    }
};
