<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tender_scores', function (Blueprint $table) {
            $table->text('evidence')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tender_scores', function (Blueprint $table) {
            $table->string('evidence', 500)->nullable()->change();
        });
    }
};
