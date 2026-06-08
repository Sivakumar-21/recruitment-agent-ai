<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenceCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'candidate_score_id',
        'reference_name',
        'reference_relationship',
        'email',
        'status',
        'feedback_text',
        'evaluation',
    ];

    protected $casts = [
        'evaluation' => 'array',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function candidateScore(): BelongsTo
    {
        return $this->belongsTo(CandidateScore::class);
    }
}
