<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
