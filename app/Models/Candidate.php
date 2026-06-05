<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'resume_path',
        'file_hash',
        'resume_text',
        'parsed_data',
        'embedding',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'embedding' => 'array',
    ];

    public function candidateScores(): HasMany
    {
        return $this->hasMany(CandidateScore::class, 'candidate_id');
    }

    public function recruitmentJobs(): BelongsToMany
    {
        return $this->belongsToMany(RecruitmentJob::class, 'candidate_scores', 'candidate_id', 'recruitment_job_id')
            ->withPivot(['score', 'skill_match', 'experience_match', 'education_match', 'recommendation', 'analysis'])
            ->withTimestamps();
    }
}
