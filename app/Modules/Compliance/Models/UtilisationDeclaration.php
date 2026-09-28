<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A utilisation declaration or permission (Shared Systems spec §3.2).
 *
 * What the factory tells customs a particular export order will consume,
 * accepted in advance. The reconciliation that matters afterwards is
 * declared against actual: consuming far less than declared is how a
 * factory ends up holding bonded goods it has no export to justify, and
 * consuming more is a shortfall somebody has to explain.
 *
 * Both links are optional. A UD is raised against an export order, and
 * the import order it will be consumed from often does not exist yet —
 * requiring either would mean raising the paperwork in the wrong order.
 */
class UtilisationDeclaration extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['UD', 'UP'];

    public const STATUSES = ['DRAFT', 'ISSUED', 'CLOSED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['ISSUED', 'CANCELLED'],
        'ISSUED' => ['CLOSED', 'CANCELLED'],
        // Terminal: reconciled and filed.
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'utilisation_declarations';

    protected $fillable = [
        'company_id', 'factory_id', 'bond_licence_id', 'ud_no', 'ud_type',
        'issue_date', 'valid_to', 'production_order_id', 'purchase_order_id',
        'buyer_reference', 'status', 'notes', 'created_by',
        'issued_at', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_to' => 'date',
            'issued_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(UtilisationDeclarationLine::class);
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(BondLicence::class, 'bond_licence_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Issued, and still inside whatever validity it was given. */
    public function isLive(?CarbonImmutable $on = null): bool
    {
        if ($this->status !== 'ISSUED') {
            return false;
        }

        if ($this->valid_to === null) {
            return true;
        }

        return ($on ?? CarbonImmutable::today())->startOfDay()
            ->lessThanOrEqualTo($this->valid_to->startOfDay());
    }
}
