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
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('title');
        });

        // Start every course's manual order in creation order.
        DB::table('sections')->distinct()->pluck('course_id')->each(function ($courseId) {
            DB::table('quiz_sessions')
                ->whereIn('section_id', DB::table('sections')->where('course_id', $courseId)->select('id'))
                ->orderBy('id')
                ->pluck('id')
                ->each(fn ($id, $index) => DB::table('quiz_sessions')->where('id', $id)->update(['sort_order' => $index]));
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
