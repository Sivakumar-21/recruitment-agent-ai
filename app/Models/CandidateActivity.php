<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'recruitment_job_id',
        'event_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public static function logActivity(int $candidateId, ?int $jobId, string $eventType, string $description, ?array $metadata = null): self
    {
        return self::create([
            'candidate_id' => $candidateId,
            'recruitment_job_id' => $jobId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function recruitmentJob(): BelongsTo
    {
        return $this->belongsTo(RecruitmentJob::class, 'recruitment_job_id');
    }
}
