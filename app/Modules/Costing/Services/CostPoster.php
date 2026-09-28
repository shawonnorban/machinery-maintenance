<?php

declare(strict_types=1);

namespace App\Modules\Costing\Services;

use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts costs, and keeps derived costs in step with their sources
 * (ERD Section 14, SRS 23-24).
 *
 * Two kinds of entry live here and they behave differently on purpose.
 *
 * A derived entry — parts issued to a job — is written by the system from
 * records that already exist, and is rewritten in place when its source
 * changes. It is not an independent fact; it is a projection, and letting it
 * drift from the part line underneath would give two answers to "what did this
 * repair cost".
 *
 * A manual entry — a vendor invoice, transport, an external service — is an
 * independent fact somebody asserted. Once posted it is never edited. A
 * correction is a reversal plus a new entry, so the history shows both what was
 * claimed and what was done about it.
 */
class CostPoster
{
    private const SCALE = 4;

    /**
     * Posts an independent cost.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(array $data, ?string $userId = null): CostEntry
    {
        $sourceType = $data['source_type'] ?? 'MANUAL';

        if (in_array($sourceType, CostEntry::DERIVED_SOURCE_TYPES, true)) {
            // Otherwise a user could post a parts cost by hand alongside the
            // one the system derives, and the work order would be charged
            // twice for the same part.
            throw ValidationException::withMessages([
                'source_type' => __('cost.derived_not_manual'),
            ])->status(422);
        }

        if (! in_array($sourceType, CostEntry::SOURCE_TYPES, true)) {
            throw ValidationException::withMessages([
                'source_type' => __('cost.unknown_source_type'),
            ]);
        }

        $amount = $this->money($data['amount'] ?? '0');

        if (bccomp($amount, '0', self::SCALE) === 0) {
            throw ValidationException::withMessages([
                'amount' => __('cost.amount_required'),
            ]);
        }

        $rate = $this->rate($data['exchange_rate'] ?? '1');

        return CostEntry::create([
            'asset_id' => $data['asset_id'],
            'work_order_id' => $data['work_order_id'] ?? null,
            'breakdown_id' => $data['breakdown_id'] ?? null,
            'cost_category_id' => $data['cost_category_id'],
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'BDT',
            'exchange_rate' => $rate,
            // Computed once and frozen. A later rate change never rewrites what
            // a closed period reported (SRS 24).
            'base_amount' => bcmul($amount, $rate, self::SCALE),
            'occurred_at' => $data['occurred_at'] ?? CarbonImmutable::now(),
            'description' => $data['description'] ?? null,
            'source_type' => $sourceType,
            'vendor_id' => $data['vendor_id'] ?? null,
            'invoice_reference' => $data['invoice_reference'] ?? null,
            'posted_at' => CarbonImmutable::now(),
            'posted_by' => $userId,
            'is_reversal' => false,
        ]);
    }

    /**
     * Undoes a posted entry with an opposing one.
     *
     * The original stays. Deleting it would leave the total right and the
     * history missing, and a missing row is harder to find than a wrong number.
     */
    public function reverse(CostEntry $original, ?string $userId = null, ?string $reason = null): CostEntry
    {
        if ($original->is_reversal) {
            throw ValidationException::withMessages([
                'cost_entry_id' => __('cost.cannot_reverse_a_reversal'),
            ])->status(409);
        }

        if (CostEntry::where('reverses_cost_entry_id', $original->id)->exists()) {
            throw ValidationException::withMessages([
                'cost_entry_id' => __('cost.already_reversed'),
            ])->status(409);
        }

        return CostEntry::create([
            'asset_id' => $original->asset_id,
            'work_order_id' => $original->work_order_id,
            'breakdown_id' => $original->breakdown_id,
            'cost_category_id' => $original->cost_category_id,
            // Negative on purpose: a reversal that stores a positive amount and
            // relies on every report to subtract it will eventually meet a
            // report that forgets.
            'amount' => bcmul((string) $original->amount, '-1', self::SCALE),
            'currency' => $original->currency,
            // The original rate, not today's. Reversing at a new rate would
            // leave a residue that looks like a real cost.
            'exchange_rate' => $original->exchange_rate,
            'base_amount' => bcmul((string) $original->base_amount, '-1', self::SCALE),
            'occurred_at' => $original->occurred_at,
            'description' => $reason ?? __('cost.reversal_of', ['id' => $original->id]),
            'source_type' => 'REVERSAL',
            'source_reference_type' => $original->source_reference_type,
            'source_reference_id' => $original->source_reference_id,
            'vendor_id' => $original->vendor_id,
            'invoice_reference' => $original->invoice_reference,
            'posted_at' => CarbonImmutable::now(),
            'posted_by' => $userId,
            'reverses_cost_entry_id' => $original->id,
            'is_reversal' => true,
        ]);
    }

    /**
     * Rewrites the derived entries for a work order from its own records.
     *
     * Idempotent: one part line produces exactly one live cost row, however
     * many times this runs. Called after every part movement, so the cost
     * ledger and the work order's own total can never disagree.
     */
    public function syncWorkOrder(WorkOrder $workOrder, ?string $userId = null): void
    {
        DB::transaction(function () use ($workOrder, $userId): void {
            $partsCategory = $this->categoryFor($workOrder->company_id, 'PARTS');

            // No labour row. A salaried technician's hour is not money leaving
            // the business, and posting it would double-count the payroll a
            // company already runs elsewhere.
            foreach (WorkOrderPart::where('work_order_id', $workOrder->id)->get() as $line) {
                // Issued minus returned at the issue-time price: what the job
                // actually kept, which is what the ledger charged it for.
                $chargeable = bcsub(
                    (string) $line->quantity_issued,
                    (string) $line->quantity_returned,
                    self::SCALE,
                );

                $amount = bcmul($chargeable, (string) ($line->unit_cost ?? '0'), self::SCALE);

                $this->syncDerived($workOrder, $partsCategory, 'PARTS', 'work_order_part', $line->id, [
                    'amount' => $amount,
                    'currency' => $line->currency,
                    'exchange_rate' => '1',
                    'occurred_at' => $line->created_at ?? CarbonImmutable::now(),
                    'description' => __('cost.parts_on', ['number' => $workOrder->work_order_number]),
                ], $userId);
            }

            // A source that has gone — a deleted labour entry — leaves a cost
            // row behind unless it is cleared. Derived rows are projections, so
            // removing one is not rewriting history.
            $this->pruneOrphans($workOrder);
        });
    }

    /**
     * Total posted against a work order, reversals included.
     */
    public function totalForWorkOrder(WorkOrder $workOrder): string
    {
        return $this->sum(CostEntry::where('work_order_id', $workOrder->id)->get());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function syncDerived(
        WorkOrder $workOrder,
        CostCategory $category,
        string $sourceType,
        string $referenceType,
        string $referenceId,
        array $values,
        ?string $userId,
    ): void {
        $amount = $this->money($values['amount']);
        $rate = $this->rate($values['exchange_rate']);

        if (bccomp($amount, '0', self::SCALE) === 0) {
            // Nothing to charge. A zero row would clutter the asset's cost
            // history with entries that mean "this happened but cost nothing".
            CostEntry::where('source_reference_type', $referenceType)
                ->where('source_reference_id', $referenceId)
                ->where('is_reversal', false)
                ->delete();

            return;
        }

        CostEntry::updateOrCreate(
            [
                'source_reference_type' => $referenceType,
                'source_reference_id' => $referenceId,
                'is_reversal' => false,
            ],
            [
                'asset_id' => $workOrder->asset_id,
                'work_order_id' => $workOrder->id,
                'breakdown_id' => $workOrder->breakdown_id,
                'cost_category_id' => $category->id,
                'amount' => $amount,
                'currency' => $values['currency'] ?? 'BDT',
                'exchange_rate' => $rate,
                'base_amount' => bcmul($amount, $rate, self::SCALE),
                'occurred_at' => $values['occurred_at'],
                'description' => $values['description'] ?? null,
                'source_type' => $sourceType,
                'posted_at' => CarbonImmutable::now(),
                'posted_by' => $userId,
            ],
        );
    }

    private function pruneOrphans(WorkOrder $workOrder): void
    {
        $partIds = WorkOrderPart::where('work_order_id', $workOrder->id)->pluck('id');

        CostEntry::where('work_order_id', $workOrder->id)
            ->where('is_reversal', false)
            ->whereIn('source_type', CostEntry::DERIVED_SOURCE_TYPES)
            ->get()
            ->each(function (CostEntry $entry) use ($partIds): void {
                $stillExists = match ($entry->source_reference_type) {
                    // Labour rows are no longer posted at all, so any that
                    // survive from before are orphans by definition and go the
                    // same way as a deleted part line.
                    'work_order_labor_entry' => false,
                    'work_order_part' => $partIds->contains($entry->source_reference_id),
                    default => true,
                };

                if (! $stillExists) {
                    $entry->delete();
                }
            });
    }

    private function categoryFor(string $companyId, string $code): CostCategory
    {
        $category = CostCategory::availableTo($companyId)
            ->where('code', $code)
            ->where('active', true)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'cost_category_id' => __('cost.category_missing', ['code' => $code]),
            ])->status(409);
        }

        return $category;
    }

    /**
     * @param  Collection<int, CostEntry>  $entries
     */
    private function sum(Collection $entries): string
    {
        return $entries->reduce(
            fn (string $carry, CostEntry $entry) => bcadd($carry, (string) $entry->base_amount, self::SCALE),
            '0.0000',
        );
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }

    private function rate(mixed $value): string
    {
        $rate = number_format((float) ($value ?? 1), 8, '.', '');

        return bccomp($rate, '0', 8) === 1 ? $rate : '1.00000000';
    }
}
