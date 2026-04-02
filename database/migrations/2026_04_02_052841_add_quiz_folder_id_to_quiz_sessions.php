<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->foreignId('quiz_folder_id')->nullable()->after('lecturer_id')
                ->constrained('quiz_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\QuizFolder::class);
            $table->dropColumn('quiz_folder_id');
        });
    }
};
