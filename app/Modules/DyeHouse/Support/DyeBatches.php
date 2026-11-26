<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Support;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\FabricPlanning\Models\FpBatchPlan;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What Dye House → Batch (Bulk)'s screens share (AnnotechERP 17/03): document
 * numbers, the names their grids and pickers show, and the batch cards a
 * released Fabric Batch Plan is cut into.
 */
final class DyeBatches
{
    public static function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * The next number of a document in its month — `B-` gives B-26110001,
     * `GF/OTR/` GF/OTR/26110001, or with `Ym` and three digits SBB/202611001
     * — under a lock that holds to the end of the transaction, so a refused save
     * burns nothing. A number keyed by hand in the same shape is stepped over.
     */
    public static function nextNumber(string $prefix, string $table, string $column, string $date, string $month = 'ym', int $width = 4): string
    {
        $head = $prefix.CarbonImmutable::parse($date)->format($month);
        $company = self::company();
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$head}:{$company}"]);
        $n = 1 + (int) DB::table($table)->where('company_id', $company)->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => ctype_digit($tail = substr($v, strlen($head))) ? (int) $tail : 0)->max();

        return $head.str_pad((string) $n, $width, '0', STR_PAD_LEFT);
    }

    /** The dyes and chemicals: the Dyes Chemical Store department's active items, "Acetic Acid (DC-0003)". @return list<array{value: string, label: string}> */
    public static function chemicals(): array
    {
        $c = self::company();
        $department = DB::table('item_departments')->where('company_id', $c)->where('code', 'DYES_CHEMICAL_STORE')->value('id');

        return DB::table('items')->where('company_id', $c)->where('active', true)->when($department, fn ($q) => $q->where('item_department_id', $department))
            ->orderBy('name')->get(['id', 'item_code', 'name'])->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->name} ({$i->item_code})"])->values()->all();
    }

    /** A batch number as it is kept: trimmed and upper case. */
    public static function batchNo(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    /** "LPP-2026-000007 — 1111": an order as Brickland's pickers name it. */
    public static function orders(): array
    {
        return DB::table('merch_orders')->where('company_id', self::company())->where('status', '!=', 'CANCELLED')->orderByDesc('created_at')->limit(1000)
            ->get(['id', 'order_no', 'style_no', 'work_style_no', 'buyer_id'])
            ->map(fn ($o) => ['value' => $o->id, 'label' => trim($o->order_no.' — '.($o->work_style_no ?: $o->style_no), ' —'), 'buyer_id' => $o->buyer_id, 'style' => $o->work_style_no ?: $o->style_no, 'order_no' => $o->order_no])
            ->values()->all();
    }

    /**
     * Names for the grids, read once.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function names(): array
    {
        $c = self::company();

        return [
            'buyers' => DB::table('buyers')->where('company_id', $c)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name])->all(),
            'orders' => DB::table('merch_orders')->where('company_id', $c)->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id')->all(),
            'colours' => DB::table('colours')->where('company_id', $c)->pluck('name', 'id')->all(),
            'groups' => DB::table('dye_colour_groups')->where('company_id', $c)->pluck('name', 'id')->all(),
            'machines' => DB::table('dye_machines')->where('company_id', $c)->pluck('machine_no', 'id')->all(),
            'stores' => DB::table('stores')->where('company_id', $c)->pluck('name', 'id')->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->pluck('name', 'id')->all(),
            'companies' => DB::table('companies')->pluck('name', 'id')->all(),
        ];
    }

    /**
     * Cut a released Fabric Batch Plan into its batch cards, onto a board plan:
     * one card per batch it counts, each taking an even share of every fabric
     * line (the last card the rounding). A plan is carded once.
     *
     * @return list<DyeBatch>
     */
    public static function cardsFrom(FpBatchPlan $fp, ?string $planId, string $date, ?string $userId): array
    {
        if ($fp->status !== 'RELEASED') {
            throw ValidationException::withMessages(['fp_batch_plan_id' => __('dyehouse.batch.v.fp_not_released', ['no' => $fp->bp_no])]);
        }
        if (DyeBatch::query()->where('fp_batch_plan_id', $fp->id)->exists()) {
            throw ValidationException::withMessages(['fp_batch_plan_id' => __('dyehouse.batch.v.fp_carded', ['no' => $fp->bp_no])]);
        }
        $n = max(1, (int) $fp->batches);
        $lines = DB::table('fp_batch_plan_lines')->where('batch_plan_id', $fp->id)->orderBy('sort_order')->get(['fabric', 'bp_qty']);
        $order = $fp->order_no ? DB::table('merch_orders')->where('company_id', self::company())->where('order_no', $fp->order_no)->value('id') : null;
        $share = fn (float $total, int $i): float => $i < $n - 1 ? round($total / $n, 2) : round($total - round($total / $n, 2) * ($n - 1), 2);
        $cards = [];
        for ($i = 0; $i < $n; $i++) {
            $qty = $share((float) $fp->bp_qty, $i);
            $card = DyeBatch::query()->create([
                'batch_no' => self::nextNumber('B-', 'dye_batches', 'batch_no', $date), 'batch_date' => $date, 'scope' => 'BULK', 'source' => 'PLAN', 'fp_batch_plan_id' => $fp->id,
                'plan_id' => $planId, 'buyer_id' => $fp->buyer_id, 'order_id' => $order, 'order_no' => $fp->order_no, 'style_ref' => $fp->style_job, 'colour_id' => $fp->colour_id,
                'colour_group_id' => $fp->colour_group_id, 'dyeing_method' => $fp->dyeing_method, 'dye_machine_id' => $fp->dye_machine_id, 'plan_qty' => $qty, 'card_qty' => $qty,
                'status' => 'OPEN', 'created_by' => $userId,
            ]);
            foreach ($lines as $k => $l) {
                DyeBatchItem::query()->create(['batch_id' => $card->id, 'kind' => 'GREY', 'description' => $l->fabric, 'qty_kg' => $share((float) $l->bp_qty, $i), 'sort_order' => ($k + 1) * 10]);
            }
            $cards[] = $card;
        }

        return $cards;
    }

    /** The one line a card keyed in as a whole carries (manual, finished fabric): its weight, kept in step with the card. */
    public static function wholeLine(DyeBatch $batch, string $kind, string $description): DyeBatchItem
    {
        $item = DyeBatchItem::query()->where('batch_id', $batch->id)->orderBy('sort_order')->first();
        $values = ['kind' => $kind, 'description' => $description, 'qty_kg' => (float) $batch->card_qty];
        if ($item) {
            $item->update($values);

            return $item;
        }

        return DyeBatchItem::query()->create(['batch_id' => $batch->id, 'sort_order' => 10] + $values);
    }
}
