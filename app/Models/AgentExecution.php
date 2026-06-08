<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'recruitment_job_id',
        'agent_name',
        'status',
        'started_at',
        'completed_at',
        'output_json',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'output_json' => 'array',
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
