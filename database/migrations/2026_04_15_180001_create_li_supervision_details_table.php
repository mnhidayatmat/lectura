<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('li_supervision_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentorship_id')->unique()->constrained('student_mentorships')->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('company_address')->nullable();
            $table->string('industry_supervisor_name')->nullable();
            $table->string('industry_supervisor_email')->nullable();
            $table->string('industry_supervisor_phone')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->enum('placement_status', ['pending', 'ongoing', 'completed', 'terminated'])->default('pending');
            $table->string('logbook_drive_folder_id')->nullable();
            $table->string('final_report_path')->nullable();
            $table->decimal('final_evaluation_score', 5, 2)->nullable();
            $table->text('supervisor_remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('li_supervision_details');
    }
};
