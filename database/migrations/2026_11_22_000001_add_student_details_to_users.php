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
            $table->string('matric_no', 30)->nullable()->after('email');
            $table->string('phone', 30)->nullable()->after('matric_no');
            $table->string('academic_advisor')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['matric_no', 'phone', 'academic_advisor']);
        });
    }
};
