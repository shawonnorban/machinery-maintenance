<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Actions;

use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Models\DutyAssessment;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Duty on goods leaving the bond (Shared Systems spec §3.4).
 *
 * Bonded stock comes in duty-free against a promise that it leaves as an
 * export. Anything else — a local sale, a move into an ordinary store, or
 * stock a count cannot find — breaks that promise, and the duty becomes
 * payable on the spot.
 *
 * **Assessing is a deliberate act, not a side effect of a stock
 * movement.** It was tempting to hang this off the ledger and have every
 * outbound movement from a bonded store raise one automatically, and that
 * would have been wrong: issuing bonded fabric to the cutting floor is
 * the *intended* use of a bond, not a breach of it, and a system that
 * charged duty for it would be unusable on day one. Only a human knows
 * whether goods left for export or for something else, so the caller says
 * which, and `reason` records the answer.
 *
 * The rates are copied onto the assessment rather than referenced. An
 * assessment raised today has to keep saying what was charged today, even
 * after customs moves the rate.
 */
class AssessBondedDuty
{
    private const SCALE = 4;

    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  reason, quantity, store_id?, unit_value?, bond_licence_id?,
     *                                      assessment_date?, reference_type?, reference_id?, notes?
     */
    public function handle(Factory $factory, Item $item, array $data, ?string $userId = null): DutyAssessment
    {
        $reason = (string) ($data['reason'] ?? 'OTHER');

        if (! in_array($reason, DutyAssessment::REASONS, true)) {
            throw ValidationException::withMessages([
                'reason' => __('validation.in', ['attribute' => 'reason']),
            ]);
        }

        $quantity = $this->money($data['quantity'] ?? 0);

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.quantity_must_be_positive'),
            ]);
        }

        $store = isset($data['store_id']) ? Store::find($data['store_id']) : null;

        if ($store !== null && $store->store_type !== 'BONDED') {
            // Duty is a bond concept. Assessing stock that was never in
            // bond would produce a charge nobody owes.
            throw ValidationException::withMessages([
                'store_id' => __('compliance.duty_store_not_bonded', ['store' => $store->name]),
            ]);
        }

        $unitValue = isset($data['unit_value'])
            ? $this->money($data['unit_value'])
            : $this->prevailingValue($item, $store);

        $assessable = bcmul($quantity, $unitValue, self::SCALE);
        $rates = $this->ratesFor($item);

        $duty = bcdiv(bcmul($assessable, $rates['duty_rate'], 6), '100', self::SCALE);
        // VAT sits on the duty-paid value, which is how customs computes
        // it — charging it on the bare value would understate the bill.
        $vat = bcdiv(bcmul(bcadd($assessable, $duty, self::SCALE), $rates['vat_rate'], 6), '100', self::SCALE);

        $licence = $this->licenceFor($factory, $data['bond_licence_id'] ?? null);

        return DutyAssessment::create([
            'factory_id' => $factory->id,
            'bond_licence_id' => $licence?->id,
            'assessment_no' => $this->numbers->next('DUTY_ASSESSMENT', $factory),
            'assessment_date' => $data['assessment_date'] ?? CarbonImmutable::today(),
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->name,
            'store_id' => $store?->id,
            'reason' => $reason,
            'quantity' => $quantity,
            'unit_value' => $unitValue,
            'assessable_value' => $assessable,
            'hs_code_id' => $rates['hs_code_id'],
            'hs_code' => $rates['hs_code'],
            'duty_rate' => $rates['duty_rate'],
            'vat_rate' => $rates['vat_rate'],
            'duty_amount' => $duty,
            'vat_amount' => $vat,
            'total_payable' => bcadd($duty, $vat, self::SCALE),
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'assessed_by' => $userId,
        ])->fresh();
    }

    /**
     * What the goods are worth, for assessment.
     *
     * The bonded store's own weighted average first, since that is what
     * these particular goods cost; failing that, the item's average
     * anywhere. Zero only when the ledger has never seen it — an
     * assessment of nothing, which reads as the unknown it is rather than
     * as a bill of nil.
     */
    private function prevailingValue(Item $item, ?Store $store): string
    {
        $query = ItemStockBalance::query()
            ->where('item_id', $item->id)
            ->where('quantity_on_hand', '>', 0);

        if ($store !== null) {
            $inStore = (clone $query)
                ->whereHas('bin', fn ($q) => $q->where('store_id', $store->id))
                ->orderByDesc('quantity_on_hand')
                ->first();

            if ($inStore !== null) {
                return (string) $inStore->weighted_average_cost;
            }
        }

        $anywhere = $query->orderByDesc('quantity_on_hand')->first();

        return (string) ($anywhere->weighted_average_cost ?? '0');
    }

    /**
     * The tariff rates for an item, as they stand today.
     *
     * @return array{hs_code_id: ?string, hs_code: ?string, duty_rate: string, vat_rate: string}
     */
    private function ratesFor(Item $item): array
    {
        $hsCode = $item->hs_code_id === null ? null : HsCode::find($item->hs_code_id);

        if ($hsCode === null) {
            // No tariff heading on the item: the assessment is still
            // written, at zero, because a released consignment nobody can
            // rate is a question for customs rather than a row to drop.
            return ['hs_code_id' => null, 'hs_code' => null, 'duty_rate' => '0', 'vat_rate' => '0'];
        }

        return [
            'hs_code_id' => $hsCode->id,
            'hs_code' => $hsCode->code,
            'duty_rate' => (string) ($hsCode->duty_rate ?? '0'),
            'vat_rate' => (string) ($hsCode->vat_rate ?? '0'),
        ];
    }

    /** The licence named, or the factory's one live one. */
    private function licenceFor(Factory $factory, ?string $licenceId): ?BondLicence
    {
        if ($licenceId !== null) {
            return BondLicence::find($licenceId);
        }

        return BondLicence::where('factory_id', $factory->id)
            ->where('status', 'ACTIVE')
            ->orderByDesc('valid_to')
            ->first();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }
}
