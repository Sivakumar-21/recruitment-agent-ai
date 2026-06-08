<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    /**
     * Helper to log an action.
     */
    public static function logAction(string $action, string $description, ?string $username = 'Recruiter'): self
    {
        return self::create([
            'username' => $username ?: 'Recruiter',
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
