<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\Erp\Support\SheetNotices;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbChange;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Formula\Formula;
use Illuminate\Support\Collection;

/**
 * Pre-Budget's notifications (user, 2026-11-21). The team of a buyer is
 * Order Summary's (its merchandisers, their team and department heads) and
 * the record's author; the Approval Center already tells approvers and the
 * sender of a budget sent for approval, so that is not told twice.
 *
 *  - PB_REQUISITIONS_CHANGED: completed requisitions added, changed or
 *    deleted (a draft is nobody else's business yet).
 *  - PB_BUDGET_CHANGED: a budget made, changed, its costing or stock yarn
 *    use done, its styles or accessories BOMs changed, withdrawn, revised or
 *    deleted.
 *  - PB_STOCK_NEEDED: a budget made (or given more styles) — the Store
 *    (`prebudget.stock` without `prebudget.view_all`) fills Stock Yarn Use.
 *  - PB_PENDING_WORK: each morning, the team's draft or sent-back budgets
 *    with costing, stock yarn use or accessories budget still to do; the
 *    Store hears of the stock yarn use waiting on it.
 */
final class BudgetNotices
{
    /** The Approval Center's own notifications cover these. */
    private const TOLD_ELSEWHERE = ['FINALIZE', 'APPROVED', 'RETURNED'];

    public static function batch(string $batch): void
    {
        $changes = PbChange::query()->where('batch', $batch)->where('kind', '!=', 'RECALC')->get(['subject', 'subject_id', 'kind', 'column_key', 'changed_by']);
        if ($changes->isEmpty()) {
            return;
        }
        $changes->first()->subject === 'BUDGET' ? self::budget($changes) : self::requisitions($changes);
    }

    /** @param  Collection<int, PbChange>  $changes */
    private static function requisitions(Collection $changes): void
    {
        $actor = $changes->first()->changed_by;
        $kinds = $changes->pluck('kind')->unique();
        $kind = $kinds->contains('DELETE') ? 'DELETE' : ($kinds->contains('COMPLETE') ? 'COMPLETE' : ($kinds->contains('CREATE') ? 'CREATE' : 'EDIT'));
        $reqs = PbRequisition::withTrashed()->whereKey($changes->pluck('subject_id')->unique())->get(['id', 'serial_no', 'status', 'buyer_id', 'created_by', 'values'])
            ->where('status', PbRequisition::COMPLETE);
        if ($reqs->isEmpty()) {
            return;
        }
        $mine = self::team($reqs, 'buyer_id');
        $by = SheetNotices::name($actor);
        $cells = $changes->whereNotNull('column_key')->groupBy('subject_id')->map->count();
        SheetNotices::send(array_keys($mine), 'PB_REQUISITIONS_CHANGED', function (string $locale, string $u) use ($mine, $by, $kind, $cells) {
            $list = collect($mine[$u]);

            return [
                'by' => $by, 'action' => __("notification.sheet.kind.{$kind}", [], $locale), 'n' => $list->count(),
                'buyers' => SheetNotices::list($list->map(fn (PbRequisition $r) => Formula::text($r->values['buyer'] ?? ''))) ?: '—',
                'styles' => SheetNotices::list($list->map(fn (PbRequisition $r) => Formula::text($r->values['style'] ?? ''))) ?: '—',
                'cells' => (int) $list->sum(fn (PbRequisition $r) => $cells[$r->id] ?? 0),
            ];
        }, $kind === 'COMPLETE' ? '/pre-budget/create-budget' : '/pre-budget/all-requisition', 'INFO', $actor);
    }

    /** @param  Collection<int, PbChange>  $changes  one budget's logged step */
    private static function budget(Collection $changes): void
    {
        $kind = $changes->first()->kind;
        $actor = $changes->first()->changed_by;
        $b = PbBudget::withTrashed()->find($changes->first()->subject_id);
        if (! $b || in_array($kind, self::TOLD_ELSEWHERE, true)) {
            return;
        }
        $team = array_keys(self::team(collect([$b]), 'buyer_id'));
        $by = SheetNotices::name($actor);
        $data = fn (string $locale) => [
            'by' => $by, 'action' => __("notification.sheet.kind.{$kind}", [], $locale), 'budget' => $b->label(), 'buyer' => $b->buyer_name ?: '—',
        ];
        $open = $kind === 'DELETE' || $b->trashed() ? '/pre-budget/create-budget' : '/pre-budget/create-budget?budget='.rawurlencode($b->label());
        SheetNotices::send($team, 'PB_BUDGET_CHANGED', fn (string $locale) => $data($locale), $open, 'INFO', $actor, 'PRE_BUDGET', $b->id);

        if (in_array($kind, ['CREATE', 'ADD_STYLE'], true) && ! $b->trashed() && $b->stock_done_at === null) {
            SheetNotices::send(self::store()->diff($team), 'PB_STOCK_NEEDED', fn (string $locale) => $data($locale),
                '/pre-budget/stock-yarn-use?budget='.rawurlencode($b->label()), 'WARNING', $actor, 'PRE_BUDGET', $b->id);
        }
    }

    /** The morning's open budget work for one company (its context set). */
    public static function pendingWork(): int
    {
        $boms = new BomSheet;
        $rows = [];
        $stock = [];
        foreach (PbBudget::query()->whereIn('status', [PbBudget::DRAFT, PbBudget::RETURNED])->get() as $b) {
            $todo = [
                'costing' => $b->costing_done_at === null,
                'stock' => $b->stock_done_at === null,
                'accessories' => $boms->summary($b)['state'] !== 'CREATED',
            ];
            if (! in_array(true, $todo, true)) {
                continue;
            }
            foreach (array_keys(self::team(collect([$b]), 'buyer_id')) as $u) {
                foreach ($todo as $k => $on) {
                    $rows[$u][$k] = ($rows[$u][$k] ?? 0) + ($on ? 1 : 0);
                }
                $rows[$u]['budgets'][] = $b->label();
            }
            if ($todo['stock']) {
                $stock[] = $b->label();
            }
        }
        $sent = SheetNotices::send(array_keys($rows), 'PB_PENDING_WORK', fn (string $locale, string $u) => [
            'costing' => $rows[$u]['costing'], 'stock' => $rows[$u]['stock'], 'accessories' => $rows[$u]['accessories'], 'budgets' => SheetNotices::list($rows[$u]['budgets']),
        ], '/pre-budget/dashboard', 'WARNING');
        if ($stock !== []) {
            $sent += SheetNotices::send(self::store()->diff(array_keys($rows)), 'PB_PENDING_WORK', fn () => [
                'costing' => 0, 'stock' => count($stock), 'accessories' => 0, 'budgets' => SheetNotices::list($stock),
            ], '/pre-budget/stock-yarn-use', 'WARNING');
        }

        return $sent;
    }

    /**
     * Who looks after these records: each one's buyer team and author, who may
     * open Pre-Budget.
     *
     * @param  Collection<int, PbRequisition|PbBudget>  $records
     * @return array<string, list<PbRequisition|PbBudget>>
     */
    private static function team(Collection $records, string $buyerKey): array
    {
        $allowed = SheetNotices::holders('prebudget.view')->flip();
        $mine = [];
        foreach ($records as $r) {
            $people = collect($r->{$buyerKey} ? OrderScope::recipientsFor((string) $r->{$buyerKey}) : [])->push($r->created_by);
            foreach ($people->filter()->unique() as $u) {
                if (isset($allowed[$u])) {
                    $mine[$u][] = $r;
                }
            }
        }

        return $mine;
    }

    /** The Store: who fills Stock Yarn Use but does not read every budget. */
    private static function store(): Collection
    {
        return SheetNotices::holders('prebudget.stock')->diff(SheetNotices::holders('prebudget.view_all'))->values();
    }
}
