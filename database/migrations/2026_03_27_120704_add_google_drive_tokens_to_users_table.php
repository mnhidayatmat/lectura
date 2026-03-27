<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('drive_access_token')->nullable()->after('avatar_url');
            $table->text('drive_refresh_token')->nullable()->after('drive_access_token');
            $table->timestamp('drive_token_expires_at')->nullable()->after('drive_refresh_token');
            $table->string('drive_root_folder_id')->nullable()->after('drive_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['drive_access_token', 'drive_refresh_token', 'drive_token_expires_at', 'drive_root_folder_id']);
        });
    }
};
