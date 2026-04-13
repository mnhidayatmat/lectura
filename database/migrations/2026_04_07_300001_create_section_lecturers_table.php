<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('section_lecturers')) {
            Schema::create('section_lecturers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('section_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['section_id', 'user_id']);
            });
        }

        // Migrate existing lecturer_id data to pivot table (skip rows already migrated)
        if (Schema::hasColumn('sections', 'lecturer_id')) {
            DB::table('sections')
                ->whereNotNull('lecturer_id')
                ->orderBy('id')
                ->each(function ($section) {
                    DB::table('section_lecturers')->updateOrInsert(
                        ['section_id' => $section->id, 'user_id' => $section->lecturer_id],
                        ['created_at' => now(), 'updated_at' => now()],
                    );
                });
        }

        if (Schema::hasColumn('sections', 'lecturer_id')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropIndex('sections_lecturer_id_index');
            });

            Schema::table('sections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lecturer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('lecturer_id')->nullable()->after('academic_term_id')->constrained('users')->nullOnDelete();
        });

        // Migrate first lecturer back to sections
        $pivots = DB::table('section_lecturers')
            ->select('section_id', DB::raw('MIN(user_id) as user_id'))
            ->groupBy('section_id')
            ->get();

        foreach ($pivots as $pivot) {
            DB::table('sections')
                ->where('id', $pivot->section_id)
                ->update(['lecturer_id' => $pivot->user_id]);
        }

        Schema::dropIfExists('section_lecturers');
    }
};
