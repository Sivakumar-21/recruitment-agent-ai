<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'recruitment_job_id',
        'candidate_id',
        'score',
        'skill_match',
        'experience_match',
        'education_match',
        'recommendation',
        'status',
        'analysis',
    ];

    protected $casts = [
        'score' => 'float',
        'skill_match' => 'float',
        'experience_match' => 'float',
        'education_match' => 'float',
        'analysis' => 'array',
    ];

    public function recruitmentJob(): BelongsTo
    {
        return $this->belongsTo(RecruitmentJob::class, 'recruitment_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }
}
