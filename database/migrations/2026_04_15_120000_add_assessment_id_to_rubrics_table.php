<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubrics', function (Blueprint $table) {
            $table->foreignId('assessment_id')->nullable()->after('assignment_id')
                ->constrained('assessments')->cascadeOnDelete();
        });

        // SQLite (used in tests) can't drop/alter FK columns in place, so skip on sqlite.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('rubrics', function (Blueprint $table) {
                $table->foreignId('assignment_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('rubrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_id');
        });
    }
};
