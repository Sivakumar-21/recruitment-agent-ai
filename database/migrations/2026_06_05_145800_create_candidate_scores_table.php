<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_job_id')->constrained('recruitment_jobs')->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->decimal('score', 5, 2)->default(0.00);
            $table->decimal('skill_match', 5, 2)->default(0.00);
            $table->decimal('experience_match', 5, 2)->default(0.00);
            $table->decimal('education_match', 5, 2)->default(0.00);
            $table->string('recommendation')->nullable();
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->json('analysis')->nullable(); // summary, strengths, concerns, interview questions
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_scores');
    }
};
