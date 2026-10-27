<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Leader no longer names a presenting ("lead") group per company: a Judge
 * may be any active member of their company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tender_sessions', function (Blueprint $table) {
            $table->dropColumn(['lead_group_a', 'lead_group_b']);
        });
    }

    public function down(): void
    {
        Schema::table('tender_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('lead_group_a')->nullable()->after('first_company');
            $table->unsignedTinyInteger('lead_group_b')->nullable()->after('lead_group_a');
        });
    }
};
