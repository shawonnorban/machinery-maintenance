<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One password change (Access Control → Password Change's Records,
 * AnnotechERP 02/01). Never the password itself — who, when, how, forced.
 */
class PasswordChange extends BaseModel
{
    public const TYPES = ['SELF', 'ADMIN_SET', 'ADMIN_RESET', 'CREATED'];

    public $timestamps = false;

    protected $table = 'password_changes';

    protected $fillable = ['user_id', 'changed_by', 'change_type', 'forced_change_next_login', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'forced_change_next_login' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public static function record(User $user, string $type, ?string $by, bool $forced = false): self
    {
        return self::create([
            'user_id' => $user->id, 'changed_by' => $by, 'change_type' => $type,
            'forced_change_next_login' => $forced, 'changed_at' => now(),
        ]);
    }
}
