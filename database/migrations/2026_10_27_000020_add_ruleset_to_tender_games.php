<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tender_games', function (Blueprint $table) {
            // piping (BTG3333) or ophir (BTG2663); see App\Services\Tender\TenderRules.
            $table->string('ruleset', 16)->default('piping')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tender_games', function (Blueprint $table) {
            $table->dropColumn('ruleset');
        });
    }
};
