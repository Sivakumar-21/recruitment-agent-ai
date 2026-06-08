<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'resume_path',
        'file_hash',
        'resume_text',
        'parsed_data',
        'embedding',
        'version',
        'uploaded_at',
        'is_latest',
        'expected_salary',
        'notice_period',
        'current_company',
        'remote_preference',
        'visa_status',
        'merged_into_id',
        'github_analysis',
        'linkedin_analysis',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'embedding' => 'array',
        'version' => 'integer',
        'uploaded_at' => 'datetime',
        'is_latest' => 'boolean',
        'github_analysis' => 'array',
        'linkedin_analysis' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($candidate) {
            if (empty($candidate->uuid)) {
                $candidate->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

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

    public function activities(): HasMany
    {
        return $this->hasMany(CandidateActivity::class)->orderBy('created_at', 'desc');
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(CandidateScreening::class)->orderBy('created_at', 'desc');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'merged_into_id');
    }

    public function mergedCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'merged_into_id');
    }

    public function referenceChecks(): HasMany
    {
        return $this->hasMany(ReferenceCheck::class)->orderBy('created_at', 'desc');
    }
}
