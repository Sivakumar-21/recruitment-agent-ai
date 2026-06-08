<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
             $table->uuid('uuid')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('resume_path');
            $table->string('file_hash', 64)->nullable()->index();
            $table->integer('version')->default(1);
            $table->timestamp('uploaded_at')->nullable();
            $table->boolean('is_latest')->default(true);
            $table->string('expected_salary')->nullable();
            $table->string('notice_period')->nullable();
            $table->string('current_company')->nullable();
            $table->string('remote_preference')->nullable();
            $table->string('visa_status')->nullable();
            $table->longText('resume_text')->nullable();
            $table->json('parsed_data')->nullable();
            $table->longText('embedding')->nullable(); // JSON array representing the embedding vector
            $table->foreignId('merged_into_id')->nullable()->constrained('candidates')->onDelete('set null');
            $table->json('github_analysis')->nullable();
            $table->json('linkedin_analysis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
