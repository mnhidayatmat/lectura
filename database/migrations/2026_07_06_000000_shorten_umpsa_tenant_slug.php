<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $oldSlug = 'universiti-malaysia-pahang-al-sultan-abdullah';

    private string $newSlug = 'umpsa';

    public function up(): void
    {
        // Only rename if the short slug is not already taken by another tenant.
        if (DB::table('tenants')->where('slug', $this->newSlug)->exists()) {
            return;
        }

        DB::table('tenants')
            ->where('slug', $this->oldSlug)
            ->update(['slug' => $this->newSlug]);
    }

    public function down(): void
    {
        DB::table('tenants')
            ->where('slug', $this->newSlug)
            ->update(['slug' => $this->oldSlug]);
    }
};
