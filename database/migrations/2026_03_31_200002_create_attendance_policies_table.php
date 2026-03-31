<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20)->default('percentage');
            $table->json('warning_thresholds');
            $table->decimal('bar_threshold', 5, 2)->nullable();
            $table->string('bar_action', 30)->default('flag');
            $table->boolean('include_late_as_absent')->default(false);
            $table->boolean('notify_student')->default(true);
            $table->boolean('notify_lecturer')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
