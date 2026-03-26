<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url', 500)->nullable()->after('google_id');
            $table->string('locale', 10)->default('en')->after('avatar_url');
            $table->boolean('is_super_admin')->default(false)->after('locale');
            $table->string('password')->nullable()->change();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['google_id', 'avatar_url', 'locale', 'is_super_admin']);
        });
    }
};
