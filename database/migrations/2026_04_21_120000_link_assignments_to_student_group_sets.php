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
            $table->foreignId('student_group_set_id')
                ->nullable()
                ->after('section_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('student_group_id')
                ->nullable()
                ->after('assignment_group_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_group_id');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_group_set_id');
        });
    }
};
