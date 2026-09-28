<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;

/**
 * Append-only record of every authentication attempt (SRS 34, 50.4).
 * Retained 90 days (SRS 49.1).
 */
class LoginAttempt extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'email', 'user_id', 'ip_address', 'successful', 'failure_reason', 'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }
}
