<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description');
            $table->json('required_skills')->nullable();
            $table->json('preferred_skills')->nullable();
            $table->integer('experience_years')->default(0);
            $table->json('parsed_analysis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_jobs');
    }
};
