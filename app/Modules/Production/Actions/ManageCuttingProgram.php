<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cutting programmes — the batch material is issued against (Master Data
 * spec §6, Inventory spec §9).
 *
 * The programme quantity is checked against what the style has left to cut,
 * because a programme for more than the style ordered is almost always a
 * typo, and one that slips through quietly inflates the material
 * requirement and the purchase raised from it.
 */
class ManageCuttingProgram
{
    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  program_qty, cutting_date?, notes?
     */
    public function create(Style $style, array $data, ?string $userId = null): CuttingProgram
    {
        $order = Order::findOrFail($style->production_order_id);

        if (! $order->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('production.order_not_open', ['status' => $order->status]),
            ])->status(409);
        }

        $quantity = ItemLineResolver::money($data['program_qty'] ?? 0);

        if (bccomp($quantity, '0', 4) !== 1) {
            throw ValidationException::withMessages(['program_qty' => __('inventory.quantity_must_be_positive')]);
        }

        $this->assertWithinStyle($style, $quantity, null);

        $factory = Factory::findOrFail($order->factory_id);

        return CuttingProgram::create([
            'factory_id' => $factory->id,
            'style_id' => $style->id,
            'program_no' => $this->numbers->next('CUTTING_PROGRAM', $factory),
            'program_qty' => $quantity,
            'cutting_date' => $data['cutting_date'] ?? CarbonImmutable::today(),
            'status' => 'CONFIRMED',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ])->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CuttingProgram $program, array $data): CuttingProgram
    {
        if ($program->status !== 'CONFIRMED') {
            // Once cutting has started the quantity is history, not a plan.
            throw ValidationException::withMessages([
                'status' => __('production.program_not_editable', ['status' => $program->status]),
            ])->status(409);
        }

        $quantity = (string) $program->program_qty;

        if (array_key_exists('program_qty', $data)) {
            $quantity = ItemLineResolver::money($data['program_qty']);

            if (bccomp($quantity, '0', 4) !== 1) {
                throw ValidationException::withMessages(['program_qty' => __('inventory.quantity_must_be_positive')]);
            }

            $this->assertWithinStyle(Style::findOrFail($program->style_id), $quantity, $program->id);
        }

        $program->forceFill([
            'program_qty' => $quantity,
            'cutting_date' => $data['cutting_date'] ?? $program->cutting_date,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $program->notes,
        ])->save();

        return $program->fresh();
    }

    public function setStatus(CuttingProgram $program, string $status): CuttingProgram
    {
        if (! $program->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $program->status, 'to' => $status]),
            ])->status(409);
        }

        $program->forceFill(['status' => $status])->save();

        return $program->fresh();
    }

    /**
     * Everything already programmed for this style, plus what is being asked
     * for now, must fit inside the style's quantity. Cancelled programmes do
     * not count — they were given back.
     */
    private function assertWithinStyle(Style $style, string $quantity, ?string $ignoreProgramId): void
    {
        $programmed = CuttingProgram::where('style_id', $style->id)
            ->where('status', '!=', 'CANCELLED')
            ->when($ignoreProgramId !== null, fn ($q) => $q->whereKeyNot($ignoreProgramId))
            ->get(['program_qty'])
            ->reduce(fn (string $sum, CuttingProgram $p): string => bcadd($sum, (string) $p->program_qty, 4), '0.0000');

        $total = bcadd($programmed, $quantity, 4);

        if (bccomp($total, (string) $style->quantity, 4) > 0) {
            throw ValidationException::withMessages([
                'program_qty' => __('production.program_exceeds_style', [
                    'style' => $style->style_no,
                    'remaining' => rtrim(rtrim(bcsub((string) $style->quantity, $programmed, 4), '0'), '.'),
                ]),
            ])->status(409);
        }
    }
}
