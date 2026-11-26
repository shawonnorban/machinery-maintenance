<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeChemClosing;
use App\Modules\DyeHouse\Models\DyeChemClosingLine;
use App\Modules\DyeHouse\Models\DyeChemCs;
use App\Modules\DyeHouse\Models\DyeChemCsLine;
use App\Modules\DyeHouse\Models\DyeChemCsQuote;
use App\Modules\DyeHouse\Models\DyeChemLot;
use App\Modules\DyeHouse\Models\DyeChemMove;
use App\Modules\DyeHouse\Support\ChemStore;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Chemical Store (AnnotechERP 17/06): the pickers, Chemical Item
 * Stock (what a store holds right now, lot by lot, worked out from every
 * movement in and out), the Chemical Comparative Statement (CS-YYYYMM####:
 * the suppliers' rates per item side by side, Draft → Submitted → Approved or
 * Rejected → Closed) and Chemical Month Closing (a struck month's closing
 * balance; the month takes no more movement, and is not struck while paper in
 * it is still open).
 */
class DyeChemStoreApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok(ChemStore::options());
    }

    /** Every lot holding stock: store, category, item, lot, rate, stock, unit, expiry. */
    public function stock(): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');
        $bal = DyeChemMove::query()->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as q, sum(value) as v')->get()->keyBy('lot_id');
        $lots = DyeChemLot::query()->whereIn('id', $bal->filter(fn ($b) => abs((float) $b->q) > 0.00005)->keys())->get();
        $items = DB::table('items')->whereIn('id', $lots->pluck('item_id')->unique())->get(['id', 'name', 'item_code', 'category_id'])->keyBy('id');
        $cats = DB::table('item_categories')->whereIn('id', $items->pluck('category_id')->filter()->unique())->pluck('name', 'id');
        $n = DyeBatches::names();

        return ApiResponse::ok($lots->map(fn (DyeChemLot $l) => [
            'id' => $l->id, 'store_id' => $l->store_id, 'store' => ChemStore::store($l->store_id), 'item_id' => $l->item_id, 'category_id' => $items[$l->item_id]->category_id ?? null,
            'category' => $cats[$items[$l->item_id]->category_id ?? ''] ?? null, 'item' => isset($items[$l->item_id]) ? "{$items[$l->item_id]->name} ({$items[$l->item_id]->item_code})" : null,
            'lot_no' => $l->lot_no, 'rate_bdt' => (float) $l->rate_bdt, 'stock' => round((float) $bal[$l->id]->q, 3), 'value' => round((float) $bal[$l->id]->v, 2),
            'uom' => $l->uom, 'exp_date' => $l->exp_date?->toDateString(),
        ])->sortBy([['store', 'asc'], ['item', 'asc'], ['exp_date', 'asc']])->values()->all());
    }

    public function statements(): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok($this->csRows(DyeChemCs::query()->orderByDesc('cs_date')->orderByDesc('cs_no')->get()));
    }

    public function showStatement(string $no): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok($this->presentCs($this->statement($no)));
    }

    /**
     * A statement, its items and every supplier's quote together: new, or
     * rewritten while a draft (a rejected one goes back to draft on saving).
     * A quote in dollars is priced in taka at the statement's rate; the
     * supplier chosen for an item must have quoted for it.
     */
    public function saveStatement(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.chemstore.manage');
        $row = $no ? $this->statement($no) : null;
        if ($row && ! in_array($row->status, ['DRAFT', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['cs_no' => __('dyehouse.chem.v.cs_closed', ['no' => $row->cs_no])]);
        }
        $c = DyeBatches::company();
        $data = $request->validate([
            'cs_date' => ['required', 'date'],
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('company_id', $c)],
            'factory_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $c)],
            'mou_code' => ['nullable', 'string', 'max:40'],
            'usd_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $c)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($c)],
            'lines.*.chosen_supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'lines.*.quotes' => ['nullable', 'array', 'max:20'],
            'lines.*.quotes.*.supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'lines.*.quotes.*.rate_usd' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.quotes.*.rate_bdt' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
        ], [
            'cs_date.required' => __('dyehouse.chem.v.cs_date'), 'lines.required' => __('dyehouse.chem.v.cs_lines'), 'lines.min' => __('dyehouse.chem.v.cs_lines'),
            'lines.*.item_id.required' => __('dyehouse.chem.v.line_item'), 'lines.*.qty.required' => __('dyehouse.chem.v.line_qty'), 'lines.*.qty.gt' => __('dyehouse.chem.v.line_qty'),
        ]);
        ksort($data['lines']);
        $usd = isset($data['usd_rate']) ? (float) $data['usd_rate'] : null;
        $lines = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $quotes = [];
            foreach ($l['quotes'] ?? [] as $q) {
                $bdt = isset($q['rate_bdt']) ? (float) $q['rate_bdt'] : (isset($q['rate_usd']) && $usd ? round((float) $q['rate_usd'] * $usd, 4) : null);
                if ($bdt === null) {
                    if (isset($q['rate_usd'])) {
                        throw ValidationException::withMessages(['usd_rate' => __('dyehouse.chem.v.cs_usd_rate')]);
                    }

                    continue;
                }
                $quotes[$q['supplier_id']] = ['supplier_id' => $q['supplier_id'], 'rate_usd' => isset($q['rate_usd']) ? (float) $q['rate_usd'] : null, 'rate_bdt' => $bdt];
            }
            $chosen = $l['chosen_supplier_id'] ?? null;
            if ($chosen && ! isset($quotes[$chosen])) {
                throw ValidationException::withMessages(["lines.{$i}.chosen_supplier_id" => __('dyehouse.chem.v.cs_chosen_unquoted', ['row' => $i + 1])]);
            }
            $lines[] = ['item_id' => $l['item_id'], 'qty' => (float) $l['qty'], 'uom' => UomCodes::normalize($l['uom'] ?? null), 'chosen' => $chosen, 'quotes' => array_values($quotes)];
        }
        $head = collect($data)->only(['cs_date', 'category_id', 'factory_id', 'mou_code', 'usd_rate', 'remarks'])->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $row = DB::transaction(function () use ($row, $head, $lines, $data): DyeChemCs {
            if ($row) {
                $row->update($head + ['status' => 'DRAFT', 'decision_note' => null]);
                DyeChemCsLine::query()->where('cs_id', $row->id)->delete();
            } else {
                $row = DyeChemCs::query()->create($head + ['status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
                    'cs_no' => DyeBatches::nextNumber('CS-', 'dye_chem_cs', 'cs_no', $data['cs_date'], 'Ym')]);
            }
            foreach ($lines as $i => $l) {
                $line = DyeChemCsLine::query()->create(['cs_id' => $row->id, 'item_id' => $l['item_id'], 'qty' => $l['qty'], 'uom' => $l['uom'], 'chosen_supplier_id' => $l['chosen'], 'sort_order' => ($i + 1) * 10]);
                foreach ($l['quotes'] as $q) {
                    DyeChemCsQuote::query()->create($q + ['cs_line_id' => $line->id]);
                }
            }

            return $row;
        });

        return ApiResponse::ok($this->presentCs($row->refresh()), [], $no ? 200 : 201);
    }

    /** Submit (every item quoted and its supplier chosen), Approve or Reject (the approver), Close (an approved statement bought against). */
    public function statementTransition(Request $request, string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['submit', 'approve', 'reject', 'close'], true), 404);
        in_array($action, ['approve', 'reject'], true) ? $this->allow('dyehouse.chemstore.approve') : $this->allow('dyehouse.chemstore.manage');
        $row = $this->statement($no);
        $from = ['submit' => 'DRAFT', 'approve' => 'SUBMITTED', 'reject' => 'SUBMITTED', 'close' => 'APPROVED'][$action];
        if ($row->status !== $from) {
            throw ValidationException::withMessages(['status' => __("dyehouse.chem.v.cs_cannot_{$action}", ['no' => $row->cs_no])]);
        }
        if ($action === 'submit') {
            $lines = DyeChemCsLine::query()->where('cs_id', $row->id)->get();
            $quoted = DyeChemCsQuote::query()->whereIn('cs_line_id', $lines->pluck('id'))->distinct()->pluck('cs_line_id');
            if ($lines->contains(fn ($l) => ! $quoted->contains($l->id) || ! $l->chosen_supplier_id)) {
                throw ValidationException::withMessages(['lines' => __('dyehouse.chem.v.cs_unchosen', ['no' => $row->cs_no])]);
            }
        }
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
        $to = ['submit' => 'SUBMITTED', 'approve' => 'APPROVED', 'reject' => 'REJECTED', 'close' => 'CLOSED'][$action];
        $row->update(['status' => $to] + (in_array($action, ['approve', 'reject'], true) ? ['decision_note' => $note, 'decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->presentCs($row->refresh()));
    }

    public function closings(): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');
        $rows = DyeChemClosing::query()->orderByDesc('period')->get();
        $users = DB::table('users')->whereIn('id', $rows->pluck('closed_by')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (DyeChemClosing $r) => [
            'id' => $r->id, 'period' => $r->period, 'period_label' => CarbonImmutable::parse("{$r->period}-01")->format('M/Y'), 'remarks' => $r->remarks, 'lots' => $r->lots,
            'closing_qty' => round((float) $r->closing_qty, 3), 'closing_value' => round((float) $r->closing_value, 2), 'closed_by' => $users[$r->closed_by] ?? null,
            'closed_at' => $r->created_at?->toIso8601String(),
        ])->values()->all());
    }

    /**
     * Month Close: strike a month — refused while anything in it is still
     * open, which is listed rather than silently included — and keep every
     * lot's balance at its end.
     */
    public function close(Request $request): JsonResponse
    {
        $this->allow('dyehouse.chemstore.close');
        $data = $request->validate(['period' => ['required', 'string', 'max:20'], 'remarks' => ['nullable', 'string', 'max:2000']], ['period.required' => __('dyehouse.chem.v.period')]);
        $period = ChemStore::period($data['period']);
        if (! $period) {
            throw ValidationException::withMessages(['period' => __('dyehouse.chem.v.period_bad', ['text' => $data['period']])]);
        }
        $label = CarbonImmutable::parse("{$period}-01")->format('M/Y');
        if ($period > CarbonImmutable::now('Asia/Dhaka')->format('Y-m')) {
            throw ValidationException::withMessages(['period' => __('dyehouse.chem.v.period_future', ['period' => $label])]);
        }
        if (DyeChemClosing::query()->where('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => __('dyehouse.chem.v.period_closed', ['period' => $label])]);
        }
        $open = ChemStore::exceptions($period);
        if ($open) {
            $list = implode(', ', array_map(fn ($e) => "{$e['no']} ({$e['date']})", array_slice($open, 0, 12))).(count($open) > 12 ? ' …' : '');
            throw ValidationException::withMessages(['period' => __('dyehouse.chem.v.period_open', ['period' => $label, 'count' => count($open), 'list' => $list])]);
        }
        $end = CarbonImmutable::parse("{$period}-01")->endOfMonth()->toDateString();
        $row = DB::transaction(function () use ($period, $end, $data): DyeChemClosing {
            $bal = DyeChemMove::query()->whereDate('move_date', '<=', $end)->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as q, sum(value) as v')->get()
                ->filter(fn ($b) => abs((float) $b->q) > 0.00005);
            $row = DyeChemClosing::query()->create(['period' => $period, 'remarks' => ($data['remarks'] ?? '') ?: null, 'lots' => $bal->count(),
                'closing_qty' => round((float) $bal->sum('q'), 4), 'closing_value' => round((float) $bal->sum('v'), 4), 'closed_by' => $this->caller()->auditUserId()]);
            foreach ($bal as $b) {
                DyeChemClosingLine::query()->create(['closing_id' => $row->id, 'lot_id' => $b->lot_id, 'qty' => round((float) $b->q, 4), 'value' => round((float) $b->v, 4)]);
            }

            return $row;
        });

        return ApiResponse::ok(['period' => $row->period, 'lots' => $row->lots, 'closing_qty' => (float) $row->closing_qty, 'closing_value' => (float) $row->closing_value], [], 201);
    }

    private function statement(string $no): DyeChemCs
    {
        return DyeChemCs::query()->where('cs_no', $no)->firstOrFail();
    }

    /**
     * @param  Collection<int, DyeChemCs>  $rows
     * @return list<array<string, mixed>>
     */
    private function csRows(Collection $rows): array
    {
        $lines = DyeChemCsLine::query()->whereIn('cs_id', $rows->pluck('id'))->get(['id', 'cs_id']);
        $quotes = DyeChemCsQuote::query()->whereIn('cs_line_id', $lines->pluck('id'))->get(['cs_line_id', 'supplier_id']);
        $csOf = $lines->pluck('cs_id', 'id');
        $suppliers = $quotes->groupBy(fn ($q) => $csOf[$q->cs_line_id])->map(fn ($g) => $g->pluck('supplier_id')->unique()->count());
        $items = $lines->groupBy('cs_id')->map->count();
        $cats = DB::table('item_categories')->whereIn('id', $rows->pluck('category_id')->filter()->unique())->pluck('name', 'id');
        $factories = DB::table('factories')->whereIn('id', $rows->pluck('factory_id')->filter()->unique())->pluck('name', 'id');

        return $rows->map(fn (DyeChemCs $r) => [
            'id' => $r->id, 'cs_no' => $r->cs_no, 'cs_date' => $r->cs_date->toDateString(), 'category_id' => $r->category_id, 'category' => $cats[$r->category_id] ?? null,
            'factory_id' => $r->factory_id, 'factory' => $factories[$r->factory_id] ?? null, 'mou_code' => $r->mou_code, 'usd_rate' => $r->usd_rate === null ? null : (float) $r->usd_rate,
            'remarks' => $r->remarks, 'status' => $r->status, 'decision_note' => $r->decision_note, 'items' => (int) ($items[$r->id] ?? 0), 'quotes' => (int) ($suppliers[$r->id] ?? 0),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentCs(DyeChemCs $r): array
    {
        $lines = DyeChemCsLine::query()->where('cs_id', $r->id)->orderBy('sort_order')->get();
        $quotes = DyeChemCsQuote::query()->whereIn('cs_line_id', $lines->pluck('id'))->get()->groupBy('cs_line_id');
        $names = ChemStore::itemNames($lines->pluck('item_id'));
        $sup = DyeBatches::names()['suppliers'];

        return $this->csRows(collect([$r]))[0] + [
            // The suppliers quoting, in the order they first appear.
            'suppliers' => $quotes->flatten()->pluck('supplier_id')->unique()->values()->map(fn ($id) => ['value' => $id, 'label' => $sup[$id] ?? $id])->all(),
            'lines' => $lines->map(fn (DyeChemCsLine $l) => [
                'item_id' => $l->item_id, 'item' => $names[$l->item_id] ?? null, 'qty' => (float) $l->qty, 'uom' => $l->uom, 'chosen_supplier_id' => $l->chosen_supplier_id,
                'chosen' => $sup[$l->chosen_supplier_id] ?? null,
                'quotes' => ($quotes[$l->id] ?? collect())->map(fn (DyeChemCsQuote $q) => ['supplier_id' => $q->supplier_id, 'rate_usd' => $q->rate_usd === null ? null : (float) $q->rate_usd, 'rate_bdt' => (float) $q->rate_bdt])->values()->all(),
            ])->values()->all(),
        ];
    }
}
