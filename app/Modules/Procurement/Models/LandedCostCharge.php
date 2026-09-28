<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge on a landed cost voucher (Procurement spec §11).
 *
 * Each head is kept separate rather than summed into one figure, because
 * they are separate invoices from separate people — the freight forwarder,
 * customs, the C&F agent, the bank — and "why did this fabric cost 12%
 * more than the PO" is a question answered by the breakdown, not the total.
 */
class LandedCostCharge extends BaseModel
{
    use BelongsToTenant;

    /** The heads the spec names, plus OTHER for whatever a port invents. */
    public const TYPES = ['FREIGHT', 'DUTY', 'CNF', 'INSURANCE', 'BANK_CHARGE', 'OTHER'];

    protected $table = 'landed_cost_charges';

    protected $fillable = [
        'company_id', 'landed_cost_voucher_id', 'charge_type', 'label',
        'vendor_id', 'amount', 'currency', 'reference_no', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
