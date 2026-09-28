<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One address a customer's system answers on.
 *
 * @property string $host
 * @property string $kind
 * @property ?Carbon $verified_at
 */
class CompanyDomain extends BaseModel
{
    use BelongsToTenant;

    /** A host we control, against the customer's own domain. */
    public const KINDS = ['SUBDOMAIN', 'CUSTOM'];

    protected $table = 'company_domains';

    protected $fillable = [
        'company_id', 'host', 'kind', 'verification_token', 'verified_at', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * The TXT record the customer has to publish.
     *
     * Under a _-prefixed label so it cannot collide with a real host of theirs,
     * which is the same convention DKIM and ACME use.
     */
    public function verificationRecordName(): string
    {
        return '_'.config('tenancy.verification_label').'.'.$this->host;
    }

    /**
     * Hostnames are case-insensitive, and a stored address that is not
     * lowercased would never match the host on an incoming request.
     */
    public static function normaliseHost(string $host): string
    {
        return strtolower(trim($host));
    }
}
