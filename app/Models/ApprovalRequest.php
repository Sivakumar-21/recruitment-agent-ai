<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_type',
        'target_type',
        'target_id',
        'status',
        'requester_notes',
        'approver_notes',
    ];

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
