<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->json('annotations')->nullable()->after('storage_path');
            $table->string('annotated_image_path', 500)->nullable()->after('annotations');
            $table->timestamp('annotated_at')->nullable()->after('annotated_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropColumn(['annotations', 'annotated_image_path', 'annotated_at']);
        });
    }
};
