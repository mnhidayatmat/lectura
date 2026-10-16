<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('notify_students')->default(true)->after('requires_submission');
            $table->timestamp('announced_at')->nullable()->after('notify_students');
        });

        // Work students can already see was released before notices existed: never announce it now.
        DB::table('assessments')
            ->where('requires_submission', true)
            ->whereIn('status', ['active', 'completed'])
            ->update(['announced_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['notify_students', 'announced_at']);
        });
    }
};
