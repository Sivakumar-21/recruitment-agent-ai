<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateScreening extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'recruitment_job_id',
        'expected_salary',
        'notice_period',
        'work_authorization',
        'remote_preference',
        'additional_notes',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function recruitmentJob(): BelongsTo
    {
        return $this->belongsTo(RecruitmentJob::class, 'recruitment_job_id');
    }
}
