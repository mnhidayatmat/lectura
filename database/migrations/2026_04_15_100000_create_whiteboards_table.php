<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whiteboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_learning_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 16); // 'course' | 'group'
            $table->string('title');
            $table->longText('scene_data')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'course_id']);
            $table->index(['tenant_id', 'active_learning_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whiteboards');
    }
};
