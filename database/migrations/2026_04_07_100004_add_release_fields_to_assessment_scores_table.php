<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->foreignId('assessment_submission_id')->nullable()->after('user_id')
                ->constrained('assessment_submissions')->nullOnDelete();
            $table->boolean('is_released')->default(false)->after('is_computed');
            $table->timestamp('released_at')->nullable()->after('is_released');
            $table->text('feedback')->nullable()->after('released_at');
            $table->foreignId('finalized_by')->nullable()->after('feedback')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropForeign(['assessment_submission_id']);
            $table->dropForeign(['finalized_by']);
            $table->dropColumn([
                'assessment_submission_id', 'is_released', 'released_at',
                'feedback', 'finalized_by', 'finalized_at',
            ]);
        });
    }
};
