<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Support;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinMachine;
use App\Modules\Finishing\Models\FinMachineProcess;
use App\Modules\Finishing\Models\FinProcessDelivery;
use App\Modules\Finishing\Models\FinProcessType;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The finishing floor (AnnotechERP 18/02): which dyed batches the Dye House
 * can hand over, the batch on each finishing card, the machine-process
 * mapping a production line must follow, and moving a batch from one WIP
 * stage to the next — every move written to its trail.
 */
final class FinFloor
{
    /** Stages only their own desks set: the sub-contract challan (18/03) and the finished fabric store (18/05). */
    public const DESK_STAGES = ['AT_SUB_CONTRACT', 'DELIVERED'];

    public static function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * Each batch as the floor reads it: number, buyer, order, style, colour, scope.
     *
     * @param  list<string>|null  $ids
     * @return array<string, object>
     */
    public static function batches(?array $ids = null): array
    {
        $c = self::company();

        return DB::table('dye_batches as b')
            ->leftJoin('buyers as y', 'y.id', '=', 'b.buyer_id')
            ->leftJoin('merch_orders as o', 'o.id', '=', 'b.order_id')
            ->leftJoin('colours as k', 'k.id', '=', 'b.colour_id')
            ->where('b.company_id', $c)
            ->when($ids !== null, fn ($q) => $q->whereIn('b.id', $ids ?: ['-']))
            ->get(['b.id', 'b.batch_no', 'b.scope', 'b.status', 'b.card_qty', 'b.lot_no', 'b.buyer_id', 'b.order_id', 'b.colour_id', 'y.short_name as buyer', 'y.name as buyer_name',
                DB::raw('coalesce(o.order_no, b.order_no) as order_no'), DB::raw("coalesce(nullif(o.work_style_no, ''), o.style_no, b.style_ref) as style"), 'k.name as colour'])
            ->map(function ($b) {
                $b->buyer = $b->buyer ?: $b->buyer_name;

                return $b;
            })->keyBy('id')->all();
    }

    /** The day a batch was first dyed, or null when it never went through a dyeing machine. */
    public static function dyedOn(string $batchId): ?string
    {
        $d = DB::table('dye_productions')->where('batch_id', $batchId)->min('production_date');

        return $d ? substr((string) $d, 0, 10) : null;
    }

    /** The card a batch is on the floor under, if it is. */
    public static function liveCard(string $batchId, ?string $except = null): ?FinBatchReceipt
    {
        return FinBatchReceipt::query()->where('batch_id', $batchId)->where('status', 'ON_FLOOR')->when($except, fn ($q) => $q->whereKeyNot($except))->first();
    }

    /**
     * Dyed batches the Dye House can hand over: dyed at least once, not replaced
     * or closed, and not on the floor already.
     *
     * @return list<array{value: string, label: string, scope: string, card_qty: float}>
     */
    public static function receivable(): array
    {
        $c = self::company();
        $dyed = DB::table('dye_productions')->where('company_id', $c)->distinct()->pluck('batch_id')->all();
        $onFloor = FinBatchReceipt::query()->where('status', 'ON_FLOOR')->pluck('batch_id')->all();
        $ids = array_values(array_diff($dyed, $onFloor));

        return collect(self::batches($ids))->filter(fn ($b) => $b->status === 'OPEN')->sortBy('batch_no')
            ->map(fn ($b) => ['value' => $b->id, 'label' => trim("{$b->batch_no} · ".($b->colour ?? '—').' · '.($b->order_no ?? '—')), 'scope' => $b->scope, 'card_qty' => (float) $b->card_qty])->values()->all();
    }

    /** Whether a batch received with this dia goes through this line. */
    public static function fitsLine(string $dia, string $line): bool
    {
        return $dia === 'BOTH' || $dia === $line;
    }

    /** The mapping a machine runs a process under on this line (its own dia, or Both). */
    public static function mapping(string $machineId, string $processId, string $line): ?FinMachineProcess
    {
        return FinMachineProcess::query()->where('machine_id', $machineId)->where('process_id', $processId)->where('active', true)
            ->whereIn('dia_type', [$line, 'BOTH'])->orderByRaw('case when dia_type = ? then 0 else 1 end', [$line])->first();
    }

    /** "ST-01 — Stenter 1" and "Stentering (ST)". @return array{0: array<string, string>, 1: array<string, string>} */
    public static function machineAndProcessNames(): array
    {
        return [
            FinMachine::query()->get(['id', 'machine_no', 'name'])->mapWithKeys(fn ($m) => [$m->id => "{$m->machine_no} — {$m->name}"])->all(),
            FinProcessType::query()->get(['id', 'name', 'short_name'])->mapWithKeys(fn ($p) => [$p->id => "{$p->name} ({$p->short_name})"])->all(),
        ];
    }

    /** Move a card to another stage and write the move down. */
    public static function move(FinBatchReceipt $card, string $to, string $source, ?CarbonInterface $at = null, ?string $sheetId = null, ?string $remarks = null, ?string $userId = null, array $extra = []): void
    {
        $at ??= now();
        FinProcessDelivery::query()->create([
            'receipt_id' => $card->id, 'from_stage' => $source === 'RECEIPT' ? null : $card->stage,'to_stage' => $to,
            'delivered_at' => $at, 'source' => $source, 'sheet_id' => $sheetId, 'remarks' => $remarks, 'created_by' => $userId,
        ]);
        $card->forceFill(['stage' => $to, 'stage_at' => $at] + $extra)->save();
    }
}
