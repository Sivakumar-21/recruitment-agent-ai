<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->onDelete('cascade');
            $table->foreignId('recruitment_job_id')->nullable()->constrained('recruitment_jobs')->onDelete('cascade');
            $table->string('agent_name');
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('output_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('action_type'); // auto_reject, high_offer, etc.
            $table->string('target_type'); // e.g. App\Models\CandidateScore
            $table->unsignedBigInteger('target_id');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('requester_notes')->nullable();
            $table->text('approver_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('agent_executions');
    }
};
