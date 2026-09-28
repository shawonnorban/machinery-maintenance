<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\RateContract;
use App\Modules\Procurement\Models\RateContractLine;
use App\Modules\Procurement\Services\RateContractResolver;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rate contracts, start to finish (Procurement spec §14).
 *
 * The rule this action exists to hold is that **two live contracts must
 * never cover the same item at the same band**. A rate contract earns its
 * keep by answering "what is the price" without anybody having to think;
 * the moment two agreements could both answer, it has stopped doing that
 * and started guessing on the buyer's behalf.
 *
 * So the check happens at **activation**, not at drafting. Somebody
 * negotiating a replacement needs to be able to type it up while the old
 * one is still running; they just cannot switch it on until the old one is
 * cancelled or its dates have passed.
 */
class ManageRateContract
{
    private const SCALE = 4;

    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly RateContractResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $data  vendor_id, valid_from, valid_to, factory_id?, title?, currency?, notes?
     * @param  list<array<string, mixed>>  $lines  item_id, rate, min_quantity?, lead_time_days?, notes?
     */
    public function create(Factory $factory, array $data, array $lines, ?string $userId = null): RateContract
    {
        $vendor = $this->vendorFor($data['vendor_id'] ?? null);
        [$from, $to] = $this->period($data);

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('procurement.contract_needs_lines')]);
        }

        $resolved = $this->resolveItems($lines);

        return DB::transaction(function () use ($factory, $vendor, $data, $from, $to, $resolved, $userId): RateContract {
            $contract = RateContract::create([
                // Null when the agreement is the whole group's, which is
                // the ordinary case for a mill price.
                'factory_id' => ($data['company_wide'] ?? false) ? null : $factory->id,
                'vendor_id' => $vendor->id,
                'contract_no' => $this->numbers->next('RATE_CONTRACT', $factory),
                'title' => $data['title'] ?? null,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'valid_from' => $from,
                'valid_to' => $to,
                'currency' => strtoupper((string) ($data['currency'] ?? $vendor->default_currency ?? 'BDT')),
                'status' => 'DRAFT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->writeLines($contract, $resolved);

            return $contract->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $lines
     */
    public function update(RateContract $contract, array $data, ?array $lines = null): RateContract
    {
        if ($contract->status !== 'DRAFT') {
            // An agreed price that has been switched on is what orders
            // have already been priced from; editing it in place would
            // rewrite the reason those orders say what they say.
            throw ValidationException::withMessages([
                'status' => __('procurement.contract_not_editable', ['status' => $contract->status]),
            ])->status(409);
        }

        [$from, $to] = $this->period($data + [
            'valid_from' => $contract->valid_from,
            'valid_to' => $contract->valid_to,
        ]);

        $resolved = $lines === null ? null : $this->resolveItems($lines);

        return DB::transaction(function () use ($contract, $data, $from, $to, $resolved): RateContract {
            $contract->forceFill([
                'title' => $data['title'] ?? $contract->title,
                'supplier_reference' => $data['supplier_reference'] ?? $contract->supplier_reference,
                'valid_from' => $from,
                'valid_to' => $to,
                'currency' => strtoupper((string) ($data['currency'] ?? $contract->currency)),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $contract->notes,
            ])->save();

            if ($resolved !== null) {
                $contract->lines()->delete();
                $this->writeLines($contract, $resolved);
            }

            return $contract->fresh();
        });
    }

    /**
     * Switches the contract on, once nothing else is answering for the
     * same items.
     */
    public function activate(RateContract $contract): RateContract
    {
        $this->assertTransition($contract, 'ACTIVE');

        if ($contract->lines()->count() === 0) {
            throw ValidationException::withMessages(['lines' => __('procurement.contract_needs_lines')]);
        }

        // Checked against ACTIVE contracts only — a draft answers nothing.
        $collisions = $this->resolver->collisionsFor($contract);

        if ($collisions !== []) {
            throw ValidationException::withMessages([
                'lines' => __('procurement.contract_collides', [
                    'item' => $collisions[0]['item_code'],
                    'number' => $collisions[0]['contract_no'],
                ]),
            ])->status(409);
        }

        $contract->forceFill([
            'status' => 'ACTIVE',
            'activated_at' => CarbonImmutable::now(),
        ])->save();

        return $contract->fresh();
    }

    public function cancel(RateContract $contract, ?string $reason = null): RateContract
    {
        $this->assertTransition($contract, 'CANCELLED');

        $contract->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $contract->notes : trim(($contract->notes ?? '')."\n".$reason),
        ])->save();

        return $contract->fresh();
    }

    /**
     * Items for contract lines.
     *
     * Not `ItemLineResolver::resolve()`: that one requires a positive
     * quantity on every line, and a contract line has no quantity — it has
     * a price and the band it starts at, and that band is legitimately
     * zero for a base price. Its other rules (the item exists, is not
     * retired, and its unit is one this company uses) are what matter here
     * and are kept.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<int, array{item: Item, uom_id: string, input: array<string, mixed>}>
     */
    private function resolveItems(array $lines): Collection
    {
        $items = Item::whereIn('id', collect($lines)->pluck('item_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        $uomIds = UnitOfMeasure::availableTo($this->context->companyId())
            ->where('active', true)->pluck('id')->flip();

        return collect($lines)->values()->map(function (array $line, int $index) use ($items, $uomIds): array {
            $field = "lines.$index";
            $item = $items->get((string) ($line['item_id'] ?? ''));

            if ($item === null) {
                throw ValidationException::withMessages(["$field.item_id" => __('procurement.unknown_item')]);
            }

            if (! $item->active) {
                throw ValidationException::withMessages([
                    "$field.item_id" => __('procurement.item_inactive', ['item' => $item->item_code.' — '.$item->name]),
                ]);
            }

            $uomId = (string) ($line['uom_id'] ?? '') ?: (string) $item->uom_id;

            if (! $uomIds->has($uomId)) {
                throw ValidationException::withMessages(["$field.uom_id" => __('itemmaster.unknown_uom')]);
            }

            return ['item' => $item, 'uom_id' => $uomId, 'input' => $line];
        });
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, input: array<string, mixed>}>  $resolved
     */
    private function writeLines(RateContract $contract, Collection $resolved): void
    {
        foreach ($resolved as $index => $row) {
            $item = $row['item'];
            $input = $row['input'];
            $rate = ItemLineResolver::money($input['rate'] ?? '0');

            if (bccomp($rate, '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages([
                    "lines.$index.rate" => __('procurement.contract_rate_must_be_positive'),
                ]);
            }

            RateContractLine::create([
                'rate_contract_id' => $contract->id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'uom_id' => $row['uom_id'],
                'rate' => $rate,
                'min_quantity' => ItemLineResolver::money($input['min_quantity'] ?? '0'),
                'lead_time_days' => $input['lead_time_days'] ?? null,
                'notes' => $input['notes'] ?? null,
            ]);
        }
    }

    private function vendorFor(?string $vendorId): Vendor
    {
        $vendor = $vendorId === null ? null : Vendor::find($vendorId);

        if ($vendor === null || $vendor->status !== 'ACTIVE' || ! $vendor->supplies()) {
            throw ValidationException::withMessages(['vendor_id' => __('procurement.vendor_cannot_supply')]);
        }

        return $vendor;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(array $data): array
    {
        $from = CarbonImmutable::parse((string) ($data['valid_from'] ?? CarbonImmutable::today()));
        $to = CarbonImmutable::parse((string) ($data['valid_to'] ?? $from->addYear()));

        if ($to->startOfDay()->lessThan($from->startOfDay())) {
            throw ValidationException::withMessages([
                'valid_to' => __('procurement.contract_period_backwards'),
            ]);
        }

        return [$from, $to];
    }

    private function assertTransition(RateContract $contract, string $to): void
    {
        if (! $contract->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $contract->status, 'to' => $to]),
            ])->status(409);
        }
    }
}
