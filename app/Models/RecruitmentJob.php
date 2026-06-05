<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecruitmentJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'required_skills',
        'preferred_skills',
        'experience_years',
        'parsed_analysis',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'preferred_skills' => 'array',
        'parsed_analysis' => 'array',
    ];

    public function candidateScores(): HasMany
    {
        return $this->hasMany(CandidateScore::class, 'recruitment_job_id');
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'candidate_scores', 'recruitment_job_id', 'candidate_id')
            ->withPivot(['score', 'skill_match', 'experience_match', 'education_match', 'recommendation', 'analysis'])
            ->withTimestamps();
    }
}
