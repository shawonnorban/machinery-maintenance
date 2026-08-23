<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The way back in when the phone is gone (SRS 50.3).
 *
 * Deliberately as powerful as a password, and therefore stored the same way:
 * hashed, and marked used the moment it works. A recovery code that still
 * works after being used is a password somebody has written on a piece of
 * paper and left in a drawer.
 *
 * No tenant scope. An account belongs to a person, not to a company — the same
 * person may be in three — and a recovery code has to work before any company
 * context exists.
 */
class MfaRecoveryCode extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'code_hash', 'used_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
