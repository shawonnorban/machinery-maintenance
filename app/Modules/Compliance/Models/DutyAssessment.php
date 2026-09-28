<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Duty falling due on goods leaving the bond (Shared Systems §3.4).
 *
 * Bonded stock is duty-free on the promise that it leaves as an export.
 * The moment it goes anywhere else — sold locally, moved to an ordinary
 * store, or simply found missing — the exemption stops applying and the
 * duty is owed. This is the record of that.
 *
 * The rates are **copied onto the row**, not joined to the HS code.
 * Customs rates change, and an assessment raised last year has to keep
 * saying what was charged then; a join to today's rate would quietly
 * restate history in a document somebody may have to defend.
 */
class DutyAssessment extends BaseModel
{
    use BelongsToTenant;

    /**
     * Why the goods left the bond.
     *
     * `SHORTAGE` is what a stock count finds: bonded goods the factory
     * cannot produce are, to customs, goods that were diverted.
     *
     * @var list<string>
     */
    public const REASONS = ['LOCAL_SALE', 'TRANSFER_OUT', 'SHORTAGE', 'WRITE_OFF', 'OTHER'];

    protected $table = 'duty_assessments';

    protected $fillable = [
        'company_id', 'factory_id', 'bond_licence_id', 'assessment_no', 'assessment_date',
        'item_id', 'item_code', 'item_name', 'store_id', 'reason', 'quantity',
        'unit_value', 'assessable_value', 'hs_code_id', 'hs_code', 'duty_rate', 'vat_rate',
        'duty_amount', 'vat_amount', 'total_payable', 'currency',
        'reference_type', 'reference_id', 'notes', 'assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'quantity' => MoneyCast::class,
            'unit_value' => MoneyCast::class,
            'assessable_value' => MoneyCast::class,
            'duty_amount' => MoneyCast::class,
            'vat_amount' => MoneyCast::class,
            'total_payable' => MoneyCast::class,
            'duty_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
        ];
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(BondLicence::class, 'bond_licence_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function hsCode(): BelongsTo
    {
        return $this->belongsTo(HsCode::class, 'hs_code_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /** Whether any rate was actually found to charge. */
    public function isRated(): bool
    {
        return $this->hs_code_id !== null;
    }
}
