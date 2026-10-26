<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tender_sessions', function (Blueprint $table) {
            // The rows opened when the reconcile round started; a change that clears a
            // flag must not close the row on the Judges mid-round.
            $table->json('reconcile_criteria')->nullable()->after('reconcile_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('tender_sessions', function (Blueprint $table) {
            $table->dropColumn('reconcile_criteria');
        });
    }
};
