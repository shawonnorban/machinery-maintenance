<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Support;

use App\Modules\DyeHouse\Models\DyeChemClosing;
use App\Modules\DyeHouse\Models\DyeChemIssue;
use App\Modules\DyeHouse\Models\DyeChemIssueLine;
use App\Modules\DyeHouse\Models\DyeChemLoanOpening;
use App\Modules\DyeHouse\Models\DyeChemLot;
use App\Modules\DyeHouse\Models\DyeChemMove;
use App\Modules\DyeHouse\Models\DyeChemReceipt;
use App\Modules\DyeHouse\Models\DyeChemReceiptLine;
use App\Modules\DyeHouse\Models\DyeChemRequisition;
use App\Modules\DyeHouse\Models\DyeChemReturn;
use App\Modules\Inventory\Support\UomCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The dye and chemical book Dye House → Chemical Store (AnnotechERP 17/06)
 * keeps. Stock is held by lot — an item, a lot number, a store — and a lot's
 * stock is its moves added up. Papers post signed moves: an opening replaces
 * what it posted before; receipts, returns and issues add theirs when the
 * store finalises or issues. An issue line naming no lot is taken from the
 * item's lots in that store, the soonest to expire first, and is refused when
 * the store holds less than is asked. A struck month takes no more movement.
 */
final class ChemStore
{
    private const EPS = 0.00005;

    /** The pickers every chemical store screen shares. @return array<string, mixed> */
    public static function options(): array
    {
        $c = DyeBatches::company();
        $dept = DB::table('item_departments')->where('company_id', $c)->where('code', 'DYES_CHEMICAL_STORE')->value('id');
        $items = DB::table('items')->leftJoin('units_of_measure', 'units_of_measure.id', '=', 'items.uom_id')->where('items.company_id', $c)->where('items.active', true)
            ->when($dept, fn ($q) => $q->where('items.item_department_id', $dept))->orderBy('items.name')
            ->get(['items.id', 'items.item_code', 'items.name', 'items.category_id', 'units_of_measure.code as uom']);
        $cats = $items->pluck('category_id')->filter()->unique()->values();

        return [
            'chemicals' => $items->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->name} ({$i->item_code})", 'category_id' => $i->category_id, 'uom' => UomCodes::normalize($i->uom)])->values()->all(),
            'categories' => DB::table('item_categories')->whereIn('id', $cats)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($k) => ['value' => $k->id, 'label' => "{$k->name} ({$k->code})"])->values()->all(),
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'companies' => DB::table('companies')->where('id', $c)->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values()->all(),
            'factories' => DB::table('factories')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->code ? "{$f->name} ({$f->code})" : $f->name])->values()->all(),
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'short_name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ? "{$b->name} ({$b->short_name})" : $b->name])->values()->all(),
            'orders' => DyeBatches::orders(),
            'batches' => DB::table('dye_batches')->where('company_id', $c)->where('status', 'OPEN')->orderByDesc('batch_date')->orderByDesc('batch_no')->limit(1000)
                ->get(['id', 'batch_no', 'dye_machine_id', 'card_qty'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->batch_no, 'machine_id' => $b->dye_machine_id, 'qty' => (float) $b->card_qty])->values()->all(),
            'machines' => DB::table('dye_machines')->where('company_id', $c)->orderBy('machine_no')->get(['id', 'machine_no'])->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no])->values()->all(),
        ];
    }

    /** The lot of an item in a store under a number, made the first time it is seen with what it came in with. */
    public static function lot(string $storeId, string $itemId, string $lotNo, array $attrs = []): DyeChemLot
    {
        $lot = DyeChemLot::query()->firstOrCreate(['store_id' => $storeId, 'item_id' => $itemId, 'lot_no' => self::lotNo($lotNo)], [
            'exp_date' => $attrs['exp_date'] ?? null, 'uom' => UomCodes::normalize($attrs['uom'] ?? null), 'rate_bdt' => (float) ($attrs['rate_bdt'] ?? 0), 'source' => $attrs['source'] ?? null,
        ]);
        // A lot first seen without its expiry or rate takes them from the paper that brings them.
        $fill = array_filter(['exp_date' => $lot->exp_date ? null : ($attrs['exp_date'] ?? null), 'uom' => $lot->uom ? null : UomCodes::normalize($attrs['uom'] ?? null),
            'rate_bdt' => (float) $lot->rate_bdt > 0 ? null : (((float) ($attrs['rate_bdt'] ?? 0)) ?: null), 'source' => $lot->source ? null : ($attrs['source'] ?? null)]);
        if ($fill) {
            $lot->update($fill);
        }

        return $lot;
    }

    /** A lot number as it is kept: trimmed and upper case. */
    public static function lotNo(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    /** One move on a lot, valued at the lot's rate unless a value is given. */
    public static function add(DyeChemLot $lot, string $kind, string $docNo, ?string $lineId, string $date, float $qty, ?float $value = null, ?string $by = null): void
    {
        if (abs($qty) < self::EPS) {
            return;
        }
        DyeChemMove::query()->create(['lot_id' => $lot->id, 'move_date' => $date, 'kind' => $kind, 'doc_no' => $docNo, 'doc_line_id' => $lineId,
            'qty' => round($qty, 4), 'value' => round($value ?? $qty * (float) $lot->rate_bdt, 4), 'created_by' => $by]);
    }

    /** Everything a paper posted under a kind, taken back. */
    public static function unpost(string $kind, string $docNo): void
    {
        DyeChemMove::query()->where('kind', $kind)->where('doc_no', $docNo)->delete();
    }

    public static function balance(string $lotId): float
    {
        return round((float) DyeChemMove::query()->where('lot_id', $lotId)->sum('qty'), 4);
    }

    /** @return array<string, float> each lot's stock */
    public static function balances(Collection $lotIds): array
    {
        return DyeChemMove::query()->whereIn('lot_id', $lotIds->filter()->unique()->values())->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as q')
            ->pluck('q', 'lot_id')->map(fn ($q) => round((float) $q, 4))->all();
    }

    /** What a store holds of an item, every lot together. */
    public static function itemBalance(string $storeId, string $itemId): float
    {
        return round((float) DyeChemMove::query()->whereIn('lot_id', DyeChemLot::query()->where('store_id', $storeId)->where('item_id', $itemId)->select('id'))->sum('qty'), 4);
    }

    /**
     * Take a quantity of an item out of a store: from the named lot, or from
     * its lots the soonest to expire first. Refused, naming what is there,
     * when the store holds less.
     *
     * @return list<array{lot: DyeChemLot, qty: float}> what came from which lot
     */
    public static function take(string $storeId, string $itemId, ?string $lotNo, float $qty, string $kind, string $docNo, ?string $lineId, string $date, ?string $by, string $field = 'lines'): array
    {
        $lots = DyeChemLot::query()->where('store_id', $storeId)->where('item_id', $itemId)
            ->when($lotNo !== null && trim($lotNo) !== '', fn ($q) => $q->where('lot_no', self::lotNo($lotNo)))
            ->orderByRaw('exp_date is null')->orderBy('exp_date')->orderBy('created_at')->orderBy('lot_no')->lockForUpdate()->get();
        $have = self::balances($lots->pluck('id'));
        $total = round(array_sum(array_map(fn ($l) => max(0, $have[$l->id] ?? 0), $lots->all())), 4);
        if ($total + self::EPS < $qty) {
            $item = DB::table('items')->where('id', $itemId)->first(['name', 'item_code']);
            throw ValidationException::withMessages([$field => __('dyehouse.chem.v.short', [
                'item' => $item ? "{$item->name} ({$item->item_code})" : $itemId, 'lot' => $lotNo ? ' '.self::lotNo($lotNo) : '',
                'store' => DB::table('stores')->where('id', $storeId)->value('name'), 'have' => self::qty($total), 'need' => self::qty($qty),
            ])]);
        }
        $out = [];
        $left = $qty;
        foreach ($lots as $lot) {
            $can = min($left, max(0, $have[$lot->id] ?? 0));
            if ($can < self::EPS) {
                continue;
            }
            self::add($lot, $kind, $docNo, $lineId, $date, -$can, null, $by);
            $out[] = ['lot' => $lot, 'qty' => round($can, 4)];
            $left = round($left - $can, 4);
            if ($left < self::EPS) {
                break;
            }
        }

        return $out;
    }

    /** "2026-07" for "2026-07", "Jul/2026" or "07/2026"; null when it names no month. */
    public static function period(?string $text): ?string
    {
        $text = trim((string) $text);
        $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $text, $m)) {
            [$year, $month] = [(int) $m[1], (int) $m[2]];
        } elseif (preg_match('/^(\d{1,2})\/(\d{4})$/', $text, $m)) {
            [$year, $month] = [(int) $m[2], (int) $m[1]];
        } elseif (preg_match('/^([A-Za-z]{3,9})[\/ ](\d{4})$/', $text, $m) && isset($months[strtolower(substr($m[1], 0, 3))])) {
            [$year, $month] = [(int) $m[2], $months[strtolower(substr($m[1], 0, 3))]];
        } else {
            return null;
        }

        return $month >= 1 && $month <= 12 && $year >= 2000 ? sprintf('%04d-%02d', $year, $month) : null;
    }

    /** A struck month takes no more movement. */
    public static function assertOpen(string $date, string $field = 'date'): void
    {
        $period = CarbonImmutable::parse($date)->format('Y-m');
        if (DyeChemClosing::query()->where('period', '>=', $period)->exists()) {
            throw ValidationException::withMessages([$field => __('dyehouse.chem.v.month_closed', ['period' => CarbonImmutable::parse($date)->format('F Y')])]);
        }
    }

    /**
     * Paper in a month the store has not finished: what stops it being struck.
     *
     * @return list<array{doc: string, no: string, date: string, status: string}>
     */
    public static function exceptions(string $period): array
    {
        $from = CarbonImmutable::parse("{$period}-01");
        $span = [$from->toDateString(), $from->endOfMonth()->toDateString()];
        $out = [];
        foreach (DyeChemReceipt::query()->where('status', 'DRAFT')->whereBetween('doc_date', $span)->orderBy('doc_date')->get() as $r) {
            $out[] = ['doc' => "receipt.{$r->kind}", 'no' => $r->doc_no, 'date' => $r->doc_date->toDateString(), 'status' => $r->status];
        }
        foreach (DyeChemReturn::query()->where('status', 'DRAFT')->whereBetween('return_date', $span)->orderBy('return_date')->get() as $r) {
            $out[] = ['doc' => 'return', 'no' => $r->return_no, 'date' => $r->return_date->toDateString(), 'status' => $r->status];
        }
        foreach (DyeChemIssue::query()->whereNotIn('status', ['ISSUED', 'CANCELLED'])->whereBetween('issue_date', $span)->orderBy('issue_date')->get() as $r) {
            $out[] = ['doc' => "issue.{$r->kind}", 'no' => $r->issue_no, 'date' => $r->issue_date->toDateString(), 'status' => $r->status];
        }
        foreach (DyeChemRequisition::query()->whereIn('status', ['WAITING', 'PARTIAL'])->where('approval', '!=', 'REJECTED')->whereBetween('req_date', $span)->orderBy('req_date')->get() as $r) {
            $out[] = ['doc' => 'requisition', 'no' => $r->req_no, 'date' => $r->req_date->toDateString(), 'status' => $r->status];
        }

        return $out;
    }

    /**
     * What we owe a supplier of each item: borrowed (the opening position's
     * Loan Received and every counted loan receipt) less what has gone back on
     * loan-return indents — on any live one when `committed`, else as issued.
     *
     * @return Collection<string, float>
     */
    public static function weOwe(string $supplierId, ?string $exceptIssueId = null, bool $committed = true): Collection
    {
        $in = DyeChemReceiptLine::query()->whereIn('receipt_id', DyeChemReceipt::query()->where('kind', 'LOAN_IN')->where('supplier_id', $supplierId)->whereIn('status', DyeChemReceipt::POSTED)->select('id'))
            ->groupBy('item_id')->selectRaw('item_id, sum(qty) as q')->pluck('q', 'item_id');
        $open = DyeChemLoanOpening::query()->where('supplier_id', $supplierId)->pluck('loan_received', 'item_id');
        $back = self::issuedTo('LOAN_RETURN', $supplierId, $exceptIssueId, $committed);

        return self::net([$in, $open], [$back]);
    }

    /**
     * What a party owes us of each item: lent (the opening position's Loan
     * Issued and every loan issued to them) less what has come back on live
     * loan-return-from-party receipts (one aside, given).
     *
     * @return Collection<string, float>
     */
    public static function theyOwe(string $supplierId, ?string $exceptReceiptId = null): Collection
    {
        $out = self::issuedTo('LOAN_OUT', $supplierId, null, false);
        $open = DyeChemLoanOpening::query()->where('supplier_id', $supplierId)->pluck('loan_issued', 'item_id');
        $back = DyeChemReceiptLine::query()->whereIn('receipt_id', DyeChemReceipt::query()->where('kind', 'LOAN_BACK')->where('supplier_id', $supplierId)->where('status', '!=', 'CANCELLED')
            ->when($exceptReceiptId, fn ($q) => $q->whereKeyNot($exceptReceiptId))->select('id'))->groupBy('item_id')->selectRaw('item_id, sum(qty) as q')->pluck('q', 'item_id');

        return self::net([$out, $open], [$back]);
    }

    /** Per item, what issues of a kind to a supplier have taken: asked on live ones (`committed`), or issued. @return Collection<string, float> */
    private static function issuedTo(string $kind, string $supplierId, ?string $exceptIssueId, bool $committed): Collection
    {
        $issues = DyeChemIssue::query()->where('kind', $kind)->where('supplier_id', $supplierId)->when($exceptIssueId, fn ($q) => $q->whereKeyNot($exceptIssueId))->get(['id', 'status'])->keyBy('id');
        $sum = collect();
        foreach (DyeChemIssueLine::query()->whereIn('issue_id', $issues->keys())->get(['issue_id', 'item_id', 'required_qty', 'issued_qty']) as $l) {
            $live = $committed && $issues[$l->issue_id]->status !== 'CANCELLED';
            $sum[$l->item_id] = round((float) ($sum[$l->item_id] ?? 0) + ($live ? max($l->required_qty, $l->issued_qty) : $l->issued_qty), 4);
        }

        return $sum;
    }

    /** @param list<Collection> $plus @param list<Collection> $minus @return Collection<string, float> */
    private static function net(array $plus, array $minus): Collection
    {
        $out = collect();
        foreach ($plus as $set) {
            foreach ($set as $item => $q) {
                $out[$item] = round((float) ($out[$item] ?? 0) + (float) $q, 4);
            }
        }
        foreach ($minus as $set) {
            foreach ($set as $item => $q) {
                $out[$item] = round((float) ($out[$item] ?? 0) - (float) $q, 4);
            }
        }

        return $out;
    }

    /** @return array<string, string> "Acetic Acid (DC-0003)" by item */
    public static function itemNames(Collection $ids): array
    {
        return DB::table('items')->whereIn('id', $ids->filter()->unique()->values())->get(['id', 'name', 'item_code'])->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->item_code})"])->all();
    }

    /** "Chemical & dyes store (DHK-CHM)": a store as the chemical screens name it, the code telling apart stores of one name. */
    public static function store(?string $id): ?string
    {
        static $names = [];
        $c = DyeBatches::company();
        $names[$c] ??= DB::table('stores')->where('company_id', $c)->get(['id', 'name', 'code'])->mapWithKeys(fn ($x) => [$x->id => $x->code ? "{$x->name} ({$x->code})" : $x->name])->all();

        return $id ? ($names[$c][$id] ?? null) : null;
    }

    public static function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ','), '0'), '.');
    }
}
