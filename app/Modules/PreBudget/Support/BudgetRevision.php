<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\PreBudget\Models\PbBom;
use App\Modules\PreBudget\Models\PbBomStyle;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbRequisition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A budget's next revision (-R1, -R2 …): a draft copy with its requisitions,
 * what was typed on its lines, the costing, the stock lots and the
 * accessories BOMs. Made by Revise (an approved budget, then REVISED) and by
 * an order's change cancelling a budget (then CANCELLED, OrderBudgetSync) —
 * the old one is kept as it was either way. `$without`: requisitions left
 * out of the copy (a dropped order's), their styles leaving its BOMs.
 */
final class BudgetRevision
{
    /** @param  list<string>  $without */
    public static function make(PbBudget $b, ?string $by, array $without = []): PbBudget
    {
        PbBudget::query()->whereKey($b->id)->lockForUpdate()->value('id');
        $rev = (int) PbBudget::withTrashed()->where('budget_no', $b->budget_no)->max('revision_no') + 1;
        $copy = $b->replicate(['approval_status', 'approval_request_id', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'revised_at', 'cancelled_at', 'cancelled_by', 'cancel_reason']);
        $copy->forceFill(['revision_no' => $rev, 'parent_id' => $b->id, 'status' => PbBudget::DRAFT, 'version' => 1, 'created_by' => $by, 'updated_by' => $by])->save();
        $now = now();
        $ids = array_values(array_diff($b->requisitions()->pluck('pb_requisitions.id')->all(), $without));
        DB::table('pb_budget_requisitions')->insert(array_map(fn (string $id) => [
            'id' => Str::lower((string) Str::ulid()), 'company_id' => $b->company_id, 'budget_id' => $copy->id, 'requisition_id' => $id, 'created_at' => $now, 'updated_at' => $now,
        ], $ids));
        foreach ([$b->lines()->get(), $b->costs()->get(), $b->stockLots()->get()] as $rows) {
            foreach ($rows as $row) {
                $row->replicate()->forceFill(['budget_id' => $copy->id])->save();
            }
        }
        foreach ($b->boms()->with('styles.lines')->get() as $bom) {
            $nb = $bom->replicate()->forceFill(['budget_id' => $copy->id, 'version' => 1]);
            $nb->save();
            foreach ($bom->styles as $st) {
                $ns = $st->replicate()->forceFill(['bom_id' => $nb->id, 'budget_id' => $copy->id]);
                $ns->save();
                foreach ($st->lines as $line) {
                    $line->replicate()->forceFill(['bom_style_id' => $ns->id])->save();
                }
            }
        }
        if ($without !== []) {
            // A style no longer in the budget leaves its BOM (an emptied BOM goes), as Remove Style does.
            $left = PbRequisition::query()->whereKey($ids)->get()->map(fn ($r) => BomSheet::styleOf($r))->unique()->all();
            PbBomStyle::query()->where('budget_id', $copy->id)->whereNotIn('style', $left)->delete();
            PbBom::query()->where('budget_id', $copy->id)->whereDoesntHave('styles')->delete();
        }

        return $copy;
    }
}
