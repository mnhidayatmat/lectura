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
        Schema::table('active_learning_plans', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('week_number');
            $table->index(['course_id', 'sort_order']);
        });

        // Start every course's manual order from week order (no week last), then creation order.
        DB::table('active_learning_plans')->distinct()->pluck('course_id')->each(function ($courseId) {
            DB::table('active_learning_plans')
                ->where('course_id', $courseId)
                ->orderByRaw('CASE WHEN week_number IS NULL THEN 1 ELSE 0 END')
                ->orderBy('week_number')
                ->orderBy('id')
                ->pluck('id')
                ->each(fn ($id, $index) => DB::table('active_learning_plans')->where('id', $id)->update(['sort_order' => $index]));
        });
    }

    public function down(): void
    {
        Schema::table('active_learning_plans', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
