<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->text('description');
            $table->string('domain', 30)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('clo_plo_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_learning_outcome_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_learning_outcome_id')->constrained()->cascadeOnDelete();
            $table->string('mapping_level', 10)->default('primary');
            $table->timestamps();

            $table->unique(['course_learning_outcome_id', 'programme_learning_outcome_id'], 'clo_plo_unique');
        });

        Schema::table('course_learning_outcomes', function (Blueprint $table) {
            $table->string('bloom_level', 20)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('course_learning_outcomes', function (Blueprint $table) {
            $table->dropColumn('bloom_level');
        });
        Schema::dropIfExists('clo_plo_mappings');
        Schema::dropIfExists('programme_learning_outcomes');
    }
};
