<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_score_id')->constrained('candidate_scores')->onDelete('cascade');
            $table->string('interviewer_name')->nullable();
            $table->string('interviewer_email')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('meeting_link')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->text('notes')->nullable();
            $table->json('evaluation')->nullable(); // technical_score, communication_score, leadership_score, recommendation, summary
            $table->json('video_evaluation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
