<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('assignment_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->timestamps();

            $table->unique(['assignment_group_id', 'user_id']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('assignment_group_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_group_id');
        });

        Schema::dropIfExists('assignment_group_members');
        Schema::dropIfExists('assignment_groups');
    }
};
