<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('answer_scheme_path', 500)->nullable()->after('answer_scheme');
            $table->string('answer_scheme_filename')->nullable()->after('answer_scheme_path');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['answer_scheme_path', 'answer_scheme_filename']);
        });
    }
};
