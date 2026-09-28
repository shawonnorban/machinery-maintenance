<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Actions;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\ItemMaster\Services\ItemNotifier;
use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Requisition\Models\FloorRequisitionLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The floor requisition's life, Phase 1: draft and submit (Floor/Store
 * Requisition spec §3). Store review and issue arrive with Phase 3.
 *
 * Every line must belong to the item department the requisition targets:
 * the form narrows the picker to that department, and this is what stops
 * a client from posting past it.
 */
class ManageFloorRequisition
{
    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLineResolver $lines,
        private readonly ItemNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  target_item_department_id, requester_department_id?, priority?, purpose?, fr_date?
     * @param  list<array<string, mixed>>  $lines  item_id, requested_qty, uom_id?, purpose?
     */
    public function create(Factory $factory, array $data, array $lines, ?string $userId = null): FloorRequisition
    {
        $department = $this->department($data['target_item_department_id'] ?? null);
        $resolved = $this->lines->resolve($lines, 'requested_qty');
        $this->assertLinesInDepartment($resolved, $department);

        return DB::transaction(function () use ($factory, $department, $data, $resolved, $userId): FloorRequisition {
            $requisition = FloorRequisition::create([
                'factory_id' => $factory->id,
                'fr_no' => $this->numbers->next('FLOOR_REQUISITION', $factory),
                'fr_date' => $data['fr_date'] ?? CarbonImmutable::today(),
                'requested_by' => $userId,
                'requester_department_id' => $data['requester_department_id'] ?? null,
                'target_item_department_id' => $department->id,
                'priority' => $data['priority'] ?? 'NORMAL',
                'status' => 'DRAFT',
                'purpose' => $data['purpose'] ?? null,
            ]);

            $this->writeLines($requisition, $resolved);

            return $requisition->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(FloorRequisition $requisition, array $data, array $lines): FloorRequisition
    {
        if (! $requisition->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.only_draft_editable', ['status' => $requisition->status]),
            ])->status(409);
        }

        $department = $this->department($data['target_item_department_id'] ?? $requisition->target_item_department_id);
        $resolved = $this->lines->resolve($lines, 'requested_qty');
        $this->assertLinesInDepartment($resolved, $department);

        return DB::transaction(function () use ($requisition, $department, $data, $resolved): FloorRequisition {
            $requisition->forceFill([
                'fr_date' => $data['fr_date'] ?? $requisition->fr_date,
                'requester_department_id' => array_key_exists('requester_department_id', $data) ? $data['requester_department_id'] : $requisition->requester_department_id,
                'target_item_department_id' => $department->id,
                'priority' => $data['priority'] ?? $requisition->priority,
                'purpose' => array_key_exists('purpose', $data) ? $data['purpose'] : $requisition->purpose,
            ])->save();

            $requisition->lines()->delete();
            $this->writeLines($requisition, $resolved);

            return $requisition->fresh();
        });
    }

    public function submit(FloorRequisition $requisition, ?string $userId = null): FloorRequisition
    {
        $this->assertTransition($requisition, 'SUBMITTED');

        $requisition->load('lines');

        if ($requisition->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        // Master Data spec §2.10: an item retired while this sat in draft
        // blocks the submit, naming it.
        $this->lines->assertAllActive($requisition->lines);

        $requisition->forceFill([
            'status' => 'SUBMITTED',
            'submitted_at' => CarbonImmutable::now(),
        ])->save();

        $this->notifier->floorRequisitionSubmitted($requisition->fresh());

        return $requisition->fresh();
    }

    public function cancel(FloorRequisition $requisition, ?string $reason = null): FloorRequisition
    {
        $this->assertTransition($requisition, 'CANCELLED');

        $requisition->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'purpose' => $reason === null ? $requisition->purpose : trim(($requisition->purpose ?? '')."\n".$reason),
        ])->save();

        return $requisition->fresh();
    }

    private function department(?string $id): ItemDepartment
    {
        $department = $id === null ? null : ItemDepartment::where('active', true)->find($id);

        if ($department === null) {
            throw ValidationException::withMessages(['target_item_department_id' => __('itemmaster.unknown_department')]);
        }

        return $department;
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>  $resolved
     */
    private function assertLinesInDepartment(Collection $resolved, ItemDepartment $department): void
    {
        foreach ($resolved as $index => $line) {
            /** @var Item $item */
            $item = $line['item'];

            if ($item->item_department_id !== $department->id) {
                throw ValidationException::withMessages([
                    "lines.$index.item_id" => __('requisition.item_not_in_department', [
                        'item' => $item->item_code, 'department' => $department->name,
                    ]),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>  $resolved
     */
    private function writeLines(FloorRequisition $requisition, Collection $resolved): void
    {
        foreach ($resolved as $line) {
            /** @var Item $item */
            $item = $line['item'];

            FloorRequisitionLine::create([
                'floor_requisition_id' => $requisition->id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'uom_id' => $line['uom_id'],
                'requested_qty' => $line['quantity'],
                'purpose' => $line['input']['purpose'] ?? null,
            ]);
        }
    }

    private function assertTransition(FloorRequisition $requisition, string $to): void
    {
        if (! $requisition->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $requisition->status, 'to' => $to]),
            ])->status(409);
        }
    }
}
