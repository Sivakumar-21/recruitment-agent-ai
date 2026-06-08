<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create reference_checks table
        Schema::create('reference_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('candidate_score_id')->nullable()->constrained('candidate_scores')->onDelete('cascade');
            $table->string('reference_name');
            $table->string('reference_relationship');
            $table->string('email');
            $table->string('status')->default('pending'); // pending, sent, completed, failed
            $table->text('feedback_text')->nullable();
            $table->json('evaluation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_checks');
    }
};
