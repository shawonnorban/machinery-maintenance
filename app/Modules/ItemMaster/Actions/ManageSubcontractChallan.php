<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\SubcontractChallan;
use App\Modules\ItemMaster\Models\SubcontractChallanLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sending fabric out to be dyed, printed or embroidered, and taking it back
 * (Inventory spec §8).
 *
 * The rolls stay the factory's the whole time — this posts nothing to the
 * stock ledger, because nothing was consumed and nothing was sold. What it
 * does instead is move each roll to `SENT_FOR_SUBCONTRACT`, which keeps it
 * off the available shelf without pretending it has gone.
 *
 * Returns are partial by nature, and the arithmetic is the point: sent =
 * returned + wastage. A dyer who sends back 94 of 100 metres has either
 * lost six to shrinkage or owes them; recording only the 94 lets the
 * difference disappear, and process loss that nobody can see is process
 * loss nobody fixes.
 */
class ManageSubcontractChallan
{
    private const SCALE = 2;

    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  vendor_id, purpose, challan_date?, expected_return_date?, notes?
     * @param  list<string>  $rollIds
     */
    public function create(Factory $factory, array $data, array $rollIds, ?string $userId = null): SubcontractChallan
    {
        $this->assertVendor($data['vendor_id'] ?? null);

        $purpose = (string) ($data['purpose'] ?? 'OTHER');

        if (! in_array($purpose, SubcontractChallan::PURPOSES, true)) {
            throw ValidationException::withMessages(['purpose' => __('validation.in', ['attribute' => 'purpose'])]);
        }

        $rolls = $this->rollsFor($rollIds);

        return DB::transaction(function () use ($factory, $data, $purpose, $rolls, $userId): SubcontractChallan {
            $challan = SubcontractChallan::create([
                'factory_id' => $factory->id,
                'challan_no' => $this->numbers->next('SUBCONTRACT_CHALLAN', $factory),
                'challan_date' => $data['challan_date'] ?? CarbonImmutable::today(),
                'vendor_id' => $data['vendor_id'],
                'purpose' => $purpose,
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'status' => 'DRAFT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($rolls as $roll) {
                SubcontractChallanLine::create([
                    'subcontract_challan_id' => $challan->id,
                    'fabric_roll_id' => $roll->id,
                    'item_id' => $roll->item_id,
                    'roll_no' => $roll->roll_no,
                    'sent_qty' => $roll->measuredQuantity(),
                    'status' => 'OUT',
                ]);
            }

            return $challan->fresh();
        });
    }

    /**
     * The cloth leaves the building. Only here do the rolls change status —
     * a draft challan is a plan, and a plan should not take stock off the
     * shelf.
     */
    public function send(SubcontractChallan $challan, ?string $userId = null): SubcontractChallan
    {
        $this->assertTransition($challan, 'SENT');

        $challan->load('lines');

        if ($challan->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        return DB::transaction(function () use ($challan): SubcontractChallan {
            foreach ($challan->lines as $line) {
                $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);

                if (! $roll->canTransitionTo('SENT_FOR_SUBCONTRACT')) {
                    throw ValidationException::withMessages([
                        'roll_ids' => __('subcontract.roll_not_sendable', [
                            'roll' => $roll->roll_no, 'status' => $roll->status,
                        ]),
                    ])->status(409);
                }

                $roll->forceFill(['status' => 'SENT_FOR_SUBCONTRACT'])->save();
            }

            $challan->forceFill([
                'status' => 'SENT',
                'sent_at' => CarbonImmutable::now(),
            ])->save();

            return $challan->fresh();
        });
    }

    /**
     * Cloth coming back, roll by roll and often in instalments.
     *
     * @param  list<array{line_id: string, returned_qty?: string, wastage_qty?: string, quality_status?: string, notes?: string}>  $returns
     */
    public function receiveReturn(SubcontractChallan $challan, array $returns, ?string $userId = null): SubcontractChallan
    {
        if (! $challan->isOut()) {
            throw ValidationException::withMessages([
                'status' => __('subcontract.challan_not_out', ['status' => $challan->status]),
            ])->status(409);
        }

        if ($returns === []) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $known = $challan->lines()->get()->keyBy('id');

        DB::transaction(function () use ($returns, $known, $userId): void {
            foreach ($returns as $index => $payload) {
                $field = "lines.$index";
                /** @var SubcontractChallanLine|null $line */
                $line = $known->get($payload['line_id'] ?? '');

                if ($line === null) {
                    throw ValidationException::withMessages(["$field.line_id" => __('subcontract.unknown_line')]);
                }

                $returned = $this->scale($payload['returned_qty'] ?? 0);
                $wastage = $this->scale($payload['wastage_qty'] ?? 0);
                $accounted = bcadd($returned, $wastage, self::SCALE);

                if (bccomp($accounted, '0', self::SCALE) !== 1) {
                    throw ValidationException::withMessages([
                        "$field.returned_qty" => __('inventory.quantity_must_be_positive'),
                    ]);
                }

                if (bccomp($accounted, $line->outstandingQty(), self::SCALE) > 0) {
                    throw ValidationException::withMessages([
                        "$field.returned_qty" => __('subcontract.return_exceeds_sent', [
                            'roll' => $line->roll_no,
                            'outstanding' => $this->trim($line->outstandingQty()),
                        ]),
                    ])->status(409);
                }

                $line->forceFill([
                    'returned_qty' => bcadd((string) $line->returned_qty, $returned, self::SCALE),
                    'wastage_qty' => bcadd((string) $line->wastage_qty, $wastage, self::SCALE),
                    'quality_status' => $payload['quality_status'] ?? $line->quality_status,
                    'returned_at' => CarbonImmutable::now(),
                    'notes' => $payload['notes'] ?? $line->notes,
                ])->save();

                $line->forceFill(['status' => $this->lineStatus($line->fresh())])->save();

                $this->settleRoll($line->fresh(), $userId);
            }
        });

        return $this->settle($challan->fresh());
    }

    /**
     * Whatever is still out is given up on, with a reason. The rolls that
     * never came back are written off rather than left `SENT_FOR_SUBCONTRACT`
     * for ever, where they would show as stock the factory does not have.
     */
    public function close(SubcontractChallan $challan, ?string $reason = null, ?string $userId = null): SubcontractChallan
    {
        $this->assertTransition($challan, 'CLOSED');

        return DB::transaction(function () use ($challan, $reason): SubcontractChallan {
            foreach ($challan->lines()->get() as $line) {
                if ($line->isSettled()) {
                    continue;
                }

                $line->forceFill([
                    'status' => 'WRITTEN_OFF',
                    'notes' => trim(($line->notes ?? '')."\n".($reason ?? '')),
                ])->save();

                $roll = FabricRoll::find($line->fabric_roll_id);

                if ($roll !== null && $roll->status === 'SENT_FOR_SUBCONTRACT') {
                    $roll->forceFill(['status' => 'DAMAGED'])->save();
                }
            }

            $challan->forceFill([
                'status' => 'CLOSED',
                'closed_at' => CarbonImmutable::now(),
                'notes' => $reason === null ? $challan->notes : trim(($challan->notes ?? '')."\n".$reason),
            ])->save();

            return $challan->fresh();
        });
    }

    public function cancel(SubcontractChallan $challan, ?string $reason = null): SubcontractChallan
    {
        $this->assertTransition($challan, 'CANCELLED');

        return DB::transaction(function () use ($challan, $reason): SubcontractChallan {
            // A cancelled challan puts the rolls back on the shelf — they
            // never left, or they came straight back untouched.
            foreach ($challan->lines()->get() as $line) {
                $roll = FabricRoll::find($line->fabric_roll_id);

                if ($roll !== null && $roll->status === 'SENT_FOR_SUBCONTRACT') {
                    $roll->forceFill(['status' => 'AVAILABLE'])->save();
                }
            }

            $challan->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $reason === null ? $challan->notes : trim(($challan->notes ?? '')."\n".$reason),
            ])->save();

            return $challan->fresh();
        });
    }

    /**
     * A roll fully accounted for goes back on the shelf, with the length
     * the subcontractor actually returned — shrinkage is real and the roll
     * is genuinely shorter than it was.
     */
    private function settleRoll(SubcontractChallanLine $line, ?string $userId): void
    {
        if (! $line->isSettled()) {
            return;
        }

        $roll = FabricRoll::find($line->fabric_roll_id);

        if ($roll === null || $roll->status !== 'SENT_FOR_SUBCONTRACT') {
            return;
        }

        $measure = $roll->measure();

        $roll->forceFill([
            $measure => (string) $line->returned_qty,
            'status' => bccomp((string) $line->returned_qty, '0', self::SCALE) === 1 ? 'AVAILABLE' : 'DAMAGED',
            'inspection_notes' => $line->quality_status,
            'inspected_by' => $userId ?? $roll->inspected_by,
        ])->save();
    }

    private function settle(SubcontractChallan $challan): SubcontractChallan
    {
        $lines = $challan->lines()->get();
        $status = $lines->every(fn (SubcontractChallanLine $line): bool => $line->isSettled())
            ? 'CLOSED'
            : 'PARTIALLY_RETURNED';

        $challan->forceFill([
            'status' => $status,
            'closed_at' => $status === 'CLOSED' ? CarbonImmutable::now() : null,
        ])->save();

        return $challan->fresh();
    }

    private function lineStatus(SubcontractChallanLine $line): string
    {
        if ($line->isSettled()) {
            return 'RETURNED';
        }

        return bccomp((string) $line->returned_qty, '0', self::SCALE) === 1 ? 'PARTIALLY_RETURNED' : 'OUT';
    }

    /**
     * @param  list<string>  $rollIds
     * @return \Illuminate\Support\Collection<int, FabricRoll>
     */
    private function rollsFor(array $rollIds)
    {
        $ids = array_values(array_unique($rollIds));

        if ($ids === []) {
            throw ValidationException::withMessages(['roll_ids' => __('procurement.needs_lines')]);
        }

        $rolls = FabricRoll::whereIn('id', $ids)->get();

        if ($rolls->count() !== count($ids)) {
            throw ValidationException::withMessages(['roll_ids' => __('requisition.unknown_roll')]);
        }

        $blocked = $rolls->firstWhere(fn (FabricRoll $roll): bool => $roll->status !== 'AVAILABLE');

        if ($blocked !== null) {
            throw ValidationException::withMessages([
                'roll_ids' => __('subcontract.roll_not_sendable', [
                    'roll' => $blocked->roll_no, 'status' => $blocked->status,
                ]),
            ])->status(409);
        }

        return $rolls;
    }

    private function assertVendor(?string $vendorId): void
    {
        if ($vendorId === null || Vendor::where('status', 'ACTIVE')->find($vendorId) === null) {
            throw ValidationException::withMessages(['vendor_id' => __('itemmaster.unknown_vendor')]);
        }
    }

    private function assertTransition(SubcontractChallan $challan, string $to): void
    {
        if (! $challan->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $challan->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }

    private function trim(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
