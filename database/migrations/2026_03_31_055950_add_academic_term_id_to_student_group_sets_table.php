<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_group_sets', function (Blueprint $table) {
            $table->foreignId('academic_term_id')->nullable()->after('course_id')
                ->constrained('academic_terms')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('student_group_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_term_id');
        });
    }
};
