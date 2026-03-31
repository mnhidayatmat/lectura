<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->string('project_title')->nullable()->after('sort_order');
            $table->text('project_description')->nullable()->after('project_title');
            $table->date('project_deadline')->nullable()->after('project_description');
            $table->string('whatsapp_link', 500)->nullable()->after('project_deadline');
            $table->decimal('score', 5, 2)->nullable()->after('whatsapp_link');
            $table->decimal('score_max', 5, 2)->nullable()->after('score');
            $table->text('score_remarks')->nullable()->after('score_max');
            $table->timestamp('score_released_at')->nullable()->after('score_remarks');
            $table->foreignId('score_by')->nullable()->constrained('users')->nullOnDelete()->after('score_released_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('score_by');
            $table->dropColumn([
                'project_title', 'project_description', 'project_deadline',
                'whatsapp_link', 'score', 'score_max', 'score_remarks',
                'score_released_at',
            ]);
        });
    }
};
