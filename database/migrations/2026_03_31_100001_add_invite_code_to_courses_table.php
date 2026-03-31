<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('invite_code', 10)->nullable()->unique()->after('status');
        });

        // Backfill existing courses with unique invite codes
        DB::table('courses')->whereNull('invite_code')->orderBy('id')->each(function ($course) {
            DB::table('courses')->where('id', $course->id)->update([
                'invite_code' => strtoupper(Str::random(8)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('invite_code');
        });
    }
};
