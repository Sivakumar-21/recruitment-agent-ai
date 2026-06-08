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
        'candidate_status',
        'status_updated_at',
        'candidate_notes',
        'candidate_rating',
        'analysis',
    ];

    protected $casts = [
        'score' => 'float',
        'skill_match' => 'float',
        'experience_match' => 'float',
        'education_match' => 'float',
        'status_updated_at' => 'datetime',
        'candidate_rating' => 'integer',
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

    public function interviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Interview::class);
    }
}
