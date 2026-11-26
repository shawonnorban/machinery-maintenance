<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An installed app (AnnotechERP 37). QCs may sign in on it once approved;
 * anyone signed in on it is signed out when it is blocked.
 */
class QtPhone extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_phones';

    protected $fillable = ['company_id', 'install_id', 'secret_hash', 'name', 'section', 'line_id', 'model', 'os', 'app_version', 'waiting_taps', 'last_ip', 'last_seen_at', 'first_user_id', 'requested_at', 'approved_at', 'approved_by', 'blocked_at', 'blocked_by'];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['waiting_taps' => 'integer', 'last_seen_at' => 'datetime', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'blocked_at' => 'datetime'];
    }

    /** BLOCKED, APPROVED (QCs may sign in), PENDING (a QC asked), or PERSONAL (only an email sign-in so far). */
    public function status(): string
    {
        return match (true) {
            $this->blocked_at !== null => 'BLOCKED',
            $this->approved_at !== null => 'APPROVED',
            $this->requested_at !== null => 'PENDING',
            default => 'PERSONAL',
        };
    }

    public function knows(string $secret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $secret));
    }
}
