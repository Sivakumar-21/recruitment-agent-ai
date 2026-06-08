<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_score_id',
        'interviewer_name',
        'interviewer_email',
        'scheduled_at',
        'meeting_link',
        'status',
        'notes',
        'evaluation',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'evaluation' => 'array',
    ];

    public function candidateScore(): BelongsTo
    {
        return $this->belongsTo(CandidateScore::class);
    }
}
