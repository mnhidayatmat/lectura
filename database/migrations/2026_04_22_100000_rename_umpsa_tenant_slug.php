<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tenants')->where('slug', 'umpsa')->exists()) {
            return;
        }

        DB::table('tenants')
            ->where('slug', 'universiti-malaysia-pahang-al-sultan-abdullah')
            ->update(['slug' => 'umpsa']);
    }

    public function down(): void
    {
        if (DB::table('tenants')->where('slug', 'universiti-malaysia-pahang-al-sultan-abdullah')->exists()) {
            return;
        }

        DB::table('tenants')
            ->where('slug', 'umpsa')
            ->update(['slug' => 'universiti-malaysia-pahang-al-sultan-abdullah']);
    }
};
