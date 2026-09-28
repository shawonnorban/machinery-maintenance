<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Procurement\Services\MaterialRequirement;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Tenancy\Models\Factory;
use Illuminate\Validation\ValidationException;

/**
 * The automatic purchase requisition raised from a material requirement
 * (Procurement spec §5.3).
 *
 * Left in DRAFT, deliberately: the spec says the requester reviews it and
 * submits — it does not go straight to approval. A document that appeared by
 * itself and approached an approver unread is a document approvers stop
 * reading.
 *
 * One requisition per cutting programme, with the programme on
 * `source_reference` so "why are we buying this" has an answer. Raising a
 * second one for the same programme is refused rather than silently
 * duplicating the order — if the requirement has moved, the draft is edited.
 */
class RaiseRequirementRequisition
{
    public function __construct(
        private readonly MaterialRequirement $requirement,
        private readonly ManagePurchaseRequisition $requisitions,
    ) {}

    public function forProgram(CuttingProgram $program, ?string $userId = null): PurchaseRequisition
    {
        $existing = PurchaseRequisition::where('source_reference', $program->id)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'cutting_program_id' => __('procurement.requirement_already_raised', ['number' => $existing->pr_no]),
            ])->status(409);
        }

        $lines = array_values(array_filter(
            $this->requirement->forProgram($program),
            fn (array $line): bool => bccomp($line['net_requirement'], '0', 4) === 1,
        ));

        if ($lines === []) {
            throw ValidationException::withMessages([
                'cutting_program_id' => __('procurement.requirement_fully_covered'),
            ])->status(409);
        }

        return $this->requisitions->create(
            Factory::findOrFail($program->factory_id),
            [
                'pr_type' => 'AUTO',
                'source_reference' => $program->id,
                'notes' => __('procurement.requirement_pr_notes', ['number' => $program->program_no]),
            ],
            array_map(fn (array $line): array => [
                'item_id' => $line['item_id'],
                'requested_qty' => $line['net_requirement'],
                'uom_id' => $line['uom_id'],
                'required_date' => $program->cutting_date?->toDateString(),
            ], $lines),
            $userId,
        );
    }
}
