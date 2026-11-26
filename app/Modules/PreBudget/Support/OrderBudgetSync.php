<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbChange;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Formula\Formula;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An order changed in Order Summary after its requisition (user,
 * 2026-11-24). The cells the order fills on its requisition rows follow it
 * at once (JOB No, Contract No, dates, Item… and Quantity, PAC, FOB), the
 * budget's worked-out values with them. Then the budget:
 *
 *  - Quantity (Ticket), PAC or FOB changed, or the order DROPPED / Cancelled,
 *    while its budget waits for approval or is approved: changing it needs
 *    `ordersummary.rebudget`; the budget is CANCELLED — kept as it was, with
 *    why and by whom, its approval request withdrawn — and its next revision
 *    (-R1, -R2 …) is made as a draft to budget again (a dropped order's rows
 *    left out of it).
 *  - A draft (or sent-back) budget is not cancelled: it simply works with the
 *    new values; a dropped order's rows leave it.
 *  - Anything else (JOB No later, PO, dates…) never cancels.
 */
final class OrderBudgetSync
{
    /** Order Summary columns whose change, after sending, cancels a budget. */
    public const MONEY = ['quantity_ticket', 'pac', 'fob'];

    public const PERMISSION = 'ordersummary.rebudget';

    /**
     * Budgets this change would cancel (waiting for approval or approved, holding the order's
     * requisitions) — the caller asks for the permission when there are any.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $diff
     * @return Collection<int, PbBudget>
     */
    public static function wouldCancel(OsOrder $o, array $diff): Collection
    {
        if (! self::cancels($o, $diff)) {
            return collect();
        }

        return self::budgets($o)->filter(fn (PbBudget $b) => in_array($b->status, PbBudget::LOCKING, true))->values();
    }

    /**
     * After the order was saved: its requisition rows follow, its budgets as above. Returns what
     * happened, for the page to say (null: nothing).
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $diff
     */
    public static function changed(OsOrder $o, array $diff, ?string $by): ?string
    {
        $reqs = PbRequisition::query()->where('os_order_id', $o->id)->get();
        if ($reqs->isEmpty()) {
            return null;
        }
        $mapped = array_intersect(OrderLink::MAP, array_keys($diff)); // requisition key => order key, of the cells that changed
        $closing = self::closing($o, $diff);
        if ($mapped === [] && ! $closing) {
            return null;
        }
        $sheet = new RequisitionSheet($o->company_id);
        if ($mapped !== []) {
            self::follow($sheet, $o, $reqs, array_keys($mapped), $by);
        }
        if (! self::cancels($o, $diff)) {
            return null;
        }
        $reason = self::reason($sheet, $o, $diff, $closing);
        $said = [];
        foreach (self::budgets($o) as $b) {
            if (in_array($b->status, PbBudget::LOCKING, true)) {
                $copy = self::cancel($b, $reason, $closing ? $reqs->pluck('id')->all() : [], $by);
                $said[] = $copy
                    ? __('prebudget.cancel.done', ['budget' => $b->label(), 'revision' => $copy->label()])
                    : __('prebudget.cancel.done_alone', ['budget' => $b->label()]);
            } elseif ($closing) {
                // A budget still being worked on: the dropped order's rows simply leave it.
                self::leave($b, $reqs, $reason, $by);
                $said[] = __('prebudget.cancel.left', ['budget' => $b->label()]);
            }
        }

        return $said === [] ? null : implode(' ', $said);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private static function cancels(OsOrder $o, array $diff): bool
    {
        return array_intersect(self::MONEY, array_keys($diff)) !== [] || self::closing($o, $diff);
    }

    /** The order turned DROPPED / Cancelled by this change. */
    private static function closing(OsOrder $o, array $diff): bool
    {
        return array_key_exists('order_type', $diff) && in_array((string) ($o->values['order_type'] ?? ''), OrderLink::CLOSED_TYPES, true)
            && ! in_array((string) ($diff['order_type'][0] ?? ''), OrderLink::CLOSED_TYPES, true);
    }

    /** @return Collection<int, PbBudget> the live budgets (not replaced, not cancelled) holding the order's requisitions */
    private static function budgets(OsOrder $o): Collection
    {
        return PbBudget::query()->whereNotIn('status', PbBudget::INACTIVE)
            ->whereHas('requisitions', fn ($q) => $q->where('os_order_id', $o->id))->orderBy('created_at')->get();
    }

    /**
     * The order's new values on its requisition rows (formulas worked out again). A value a requisition
     * list does not hold is left as it was — the order is still saved.
     *
     * @param  Collection<int, PbRequisition>  $reqs
     * @param  list<string>  $keys  requisition keys whose order cell changed
     */
    private static function follow(RequisitionSheet $sheet, OsOrder $o, Collection $reqs, array $keys, ?string $by): void
    {
        $fill = OrderLink::fill($o, $sheet);
        $input = [];
        foreach ($keys as $key) {
            $c = $sheet->column($key);
            if (! $c) {
                continue;
            }
            try {
                $input[$key] = $sheet->normalize($c, $fill[$key] ?? null, $key);
            } catch (ValidationException) {
                continue;
            }
        }
        if ($input === []) {
            return;
        }
        $batch = (string) Str::ulid();
        $first = $reqs->sortBy('serial_no')->first()?->id;
        foreach ($reqs as $r) {
            // The quantity and price sit on the order's first row; its other parts' rows carry none.
            $own = $r->id === $first ? $input : array_diff_key($input, array_flip(OrderLink::QTY));
            if ($own === []) {
                continue;
            }
            [$values, $diff] = $sheet->put($r->values ?? [], $own, 'order');
            if ($diff === []) {
                continue;
            }
            $r->forceFill(['values' => $values, 'buyer_id' => array_key_exists('buyer', $diff) ? $sheet->buyerId($values) : $r->buyer_id, 'version' => $r->version + 1, 'updated_by' => $by])->save();
            $sheet->log($r, 'EDIT', $diff, $batch, $by);
        }
    }

    /** "Quantity (Ticket) 1,000 → 1,200; FOB 3.5 → 3.8 (Order Summary SSL 1406)" — kept on the cancelled budget. */
    private static function reason(RequisitionSheet $sheet, OsOrder $o, array $diff, bool $closing): string
    {
        $os = new \App\Modules\OrderSummary\Support\Sheet($o->company_id);
        $parts = [];
        foreach ($closing ? ['order_type'] : self::MONEY as $key) {
            if (! array_key_exists($key, $diff)) {
                continue;
            }
            [$old, $new] = $diff[$key];
            $parts[] = ($os->column($key)?->label ?? $key).' '.(Formula::blank($old) ? '—' : Formula::text($old)).' → '.(Formula::blank($new) ? '—' : Formula::text($new));
        }
        $style = Formula::text(($o->values['style_number_name'] ?? [])[0] ?? '');

        return __('prebudget.cancel.reason', ['changes' => implode('; ', $parts), 'ssl' => $o->serial_no, 'style' => $style !== '' ? $style : '—']);
    }

    /**
     * Cancel a sent / approved budget: its approval request withdrawn, kept as CANCELLED with the reason,
     * its next revision made as a draft (null when nothing would be left in it).
     *
     * @param  list<string>  $without
     */
    private static function cancel(PbBudget $b, string $reason, array $without, ?string $by): ?PbBudget
    {
        if ($b->status === PbBudget::PENDING && $b->approval_request_id && ($request = ApprovalRequest::query()->find($b->approval_request_id)) && $request->isPending()) {
            app(DecideApproval::class)->cancel($request, $by, $reason); // the budget turns DRAFT through its approval hook
            $b->refresh();
        }
        $left = array_diff($b->requisitions()->pluck('pb_requisitions.id')->all(), $without);
        $copy = $left === [] ? null : BudgetRevision::make($b, $by, $without);
        $b->forceFill(['status' => PbBudget::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by, 'cancel_reason' => $reason, 'version' => $b->version + 1, 'updated_by' => $by])->save();
        self::log($b, 'CANCEL', ['reason' => [null, $reason], 'revision' => [$b->label(), $copy?->label()]], $by);

        return $copy;
    }

    /** @param  Collection<int, PbRequisition>  $reqs */
    private static function leave(PbBudget $b, Collection $reqs, string $reason, ?string $by): void
    {
        $ids = $b->requisitions()->whereIn('pb_requisitions.id', $reqs->pluck('id'))->pluck('pb_requisitions.id');
        if ($ids->isEmpty()) {
            return;
        }
        $b->requisitions()->detach($ids->all());
        $left = $b->requisitions()->get()->map(fn ($r) => BomSheet::styleOf($r))->unique()->all();
        \App\Modules\PreBudget\Models\PbBomStyle::query()->where('budget_id', $b->id)->whereNotIn('style', $left)->delete();
        \App\Modules\PreBudget\Models\PbBom::query()->where('budget_id', $b->id)->whereDoesntHave('styles')->delete();
        $b->forceFill(['version' => $b->version + 1, 'updated_by' => $by])->save();
        self::log($b, 'REMOVE_STYLE', ['styles' => [$reqs->pluck('values.style')->filter()->unique()->implode(', '), null], 'reason' => [null, $reason]], $by);
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $changes */
    private static function log(PbBudget $b, string $kind, array $changes, ?string $by): void
    {
        $batch = (string) Str::ulid();
        $base = ['company_id' => $b->company_id, 'subject' => 'BUDGET', 'subject_id' => $b->id, 'buyer_id' => $b->buyer_id, 'kind' => $kind, 'batch' => $batch, 'changed_by' => $by, 'changed_at' => now()];
        $rows = [];
        foreach ($changes as $key => [$old, $new]) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => $key, 'old_value' => $old === null ? null : mb_substr((string) $old, 0, 5000), 'new_value' => $new === null ? null : mb_substr((string) $new, 0, 5000)];
        }
        PbChange::query()->insert($rows);
        \App\Modules\Erp\Support\SheetNotices::note('pb', $batch);
    }
}
