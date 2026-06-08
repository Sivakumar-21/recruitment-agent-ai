<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create candidate_activities table
        Schema::create('candidate_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('recruitment_job_id')->nullable()->constrained('recruitment_jobs')->onDelete('cascade');
            $table->string('event_type');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Create candidate_screenings table
        Schema::create('candidate_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('recruitment_job_id')->constrained('recruitment_jobs')->onDelete('cascade');
            $table->string('expected_salary')->nullable();
            $table->string('notice_period')->nullable();
            $table->string('work_authorization')->nullable();
            $table->string('remote_preference')->nullable();
            $table->text('additional_notes')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_screenings');
        Schema::dropIfExists('candidate_activities');
    }
};
