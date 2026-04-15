<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original create_assignment_tables migration used
     * $table->foreignId('rubric_criteria_id')->constrained() which lets
     * Laravel's pluraliser derive the referenced table from the column
     * name. "criteria" is already a Latin plural, so the pluraliser
     * produces "rubric_criterias" — a table that never exists. Inserts
     * into rubric_levels therefore fail on drivers that enforce foreign
     * keys. Rebuild the table pointing at the real "rubric_criteria"
     * table.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::create('rubric_levels_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rubric_criteria_id')
                    ->constrained('rubric_criteria')
                    ->cascadeOnDelete();
                $table->string('label', 100);
                $table->text('description')->nullable();
                $table->decimal('marks', 5, 2);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            DB::statement('INSERT INTO rubric_levels_tmp
                (id, rubric_criteria_id, label, description, marks, sort_order, created_at, updated_at)
                SELECT id, rubric_criteria_id, label, description, marks, sort_order, created_at, updated_at
                FROM rubric_levels');

            Schema::drop('rubric_levels');
            Schema::rename('rubric_levels_tmp', 'rubric_levels');

            return;
        }

        Schema::table('rubric_levels', function (Blueprint $table) {
            // MySQL/Postgres: swap the foreign key for one that points at
            // the real rubric_criteria table.
            $table->dropForeign(['rubric_criteria_id']);
            $table->foreign('rubric_criteria_id')
                ->references('id')->on('rubric_criteria')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // No down path: reverting would reintroduce the broken FK target.
    }
};
