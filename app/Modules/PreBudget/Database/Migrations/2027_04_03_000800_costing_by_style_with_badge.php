<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Knit/Dying Costing (user, 2026-11-19): Embellishment, CM and
 * Badge Cost are per style, as in Norban — one row for each style of the
 * budget (its requisitions' Order Qty added up), not one per requisition; the
 * Badge Cost table lists the styles the same way, with no style picking.
 * A style row now keys on the style name and carries the badge's wastage,
 * cost per dozen, supplier and remarks; the separate badge rows go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_budget_costs', function (Blueprint $table): void {
            $table->decimal('badge_wastage', 8, 4)->nullable()->after('cm_per_dz');
            $table->decimal('badge_per_dz', 12, 4)->nullable()->after('badge_wastage');
            $table->string('badge_supplier', 255)->nullable()->after('badge_per_dz');
            $table->string('badge_remarks', 500)->nullable()->after('badge_supplier');
        });

        $style = fn (string $requisitionId) => trim((string) (json_decode((string) DB::table('pb_requisitions')->where('id', $requisitionId)->value('values'), true)['style'] ?? ''));

        // Style rows keyed by requisition → by style name (the first rate given wins).
        $merged = [];
        foreach (DB::table('pb_budget_costs')->where('kind', 'STYLE')->orderBy('created_at')->get() as $row) {
            $key = $row->budget_id.'|'.$style($row->cost_key);
            $merged[$key] ??= ['budget_id' => $row->budget_id, 'company_id' => $row->company_id, 'style' => $style($row->cost_key), 'emb_per_dz' => null, 'cm_per_dz' => null];
            $merged[$key]['emb_per_dz'] ??= $row->emb_per_dz;
            $merged[$key]['cm_per_dz'] ??= $row->cm_per_dz;
        }
        // The badge rows → the badge of each of their styles.
        foreach (DB::table('pb_budget_badges')->orderBy('sort_order')->get() as $badge) {
            foreach (json_decode((string) $badge->styles, true) ?: [] as $id) {
                $key = $badge->budget_id.'|'.$style($id);
                $merged[$key] ??= ['budget_id' => $badge->budget_id, 'company_id' => $badge->company_id, 'style' => $style($id), 'emb_per_dz' => null, 'cm_per_dz' => null];
                $merged[$key] += ['badge_wastage' => $badge->wastage, 'badge_per_dz' => $badge->cost_per_dz, 'badge_supplier' => $badge->supplier, 'badge_remarks' => $badge->remarks];
            }
        }
        DB::table('pb_budget_costs')->where('kind', 'STYLE')->delete();
        foreach ($merged as $row) {
            DB::table('pb_budget_costs')->insert([
                'id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'company_id' => $row['company_id'], 'budget_id' => $row['budget_id'], 'kind' => 'STYLE', 'cost_key' => $row['style'],
                'emb_per_dz' => $row['emb_per_dz'], 'cm_per_dz' => $row['cm_per_dz'], 'badge_wastage' => $row['badge_wastage'] ?? null, 'badge_per_dz' => $row['badge_per_dz'] ?? null,
                'badge_supplier' => $row['badge_supplier'] ?? null, 'badge_remarks' => $row['badge_remarks'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::dropIfExists('pb_budget_badges');
        // A completed costing stays completed: its basis is now the budget's fabrications and styles.
        $context = app(\App\Shared\Tenancy\TenantContext::class);
        foreach (DB::table('pb_budgets')->whereNotNull('costing_done_at')->get(['id', 'company_id']) as $row) {
            $context->forget();
            $context->set($row->company_id);
            $b = \App\Modules\PreBudget\Models\PbBudget::query()->find($row->id);
            if ($b) {
                $basis = (new \App\Modules\PreBudget\Support\BudgetSheet)->costing($b, $b->requisitions()->get())['basis'];
                DB::table('pb_budgets')->where('id', $row->id)->update(['costing_basis' => $basis]);
            }
        }
        $context->forget();
    }

    public function down(): void
    {
        Schema::table('pb_budget_costs', fn (Blueprint $t) => $t->dropColumn(['badge_wastage', 'badge_per_dz', 'badge_supplier', 'badge_remarks']));
    }
};
