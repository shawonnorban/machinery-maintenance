<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Support;

use App\Modules\Assembly\Models\AsmTrimsMove;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Accessories Store (AnnotechERP 20/08), what the receipt,
 * issue and return papers share: their numbers (MRR- / ISS- / RTN- + year,
 * month and a running four digits), the names on them (stores, suppliers,
 * trims items, orders, sewing lines), a store's stock of an item (its moves
 * added up), and booking a move in or out.
 */
final class TrimsStore
{
    private ?array $names = null;

    public function __construct(private readonly string $companyId) {}

    /** The next number of a kind for the paper's month: MRR-2026110001. */
    public function nextNo(string $prefix, string $table, string $column, string $date): string
    {
        $stem = $prefix.'-'.str_replace('-', '', substr($date, 0, 7));
        $last = DB::table($table)->where('company_id', $this->companyId)->where($column, 'like', $stem.'%')->orderByDesc($column)->value($column);
        $n = $last ? (int) substr($last, strlen($stem)) + 1 : 1;

        return $stem.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array{stores: array<string, string>, suppliers: array<string, string>, items: array<string, object>, orders: array<string, object>, lines: array<string, string>, company: ?string} */
    public function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }
        $c = $this->companyId;

        return $this->names = [
            'stores' => DB::table('stores')->where('company_id', $c)->pluck('name', 'id')->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->pluck('name', 'id')->all(),
            'items' => DB::table('mp_items')->where('company_id', $c)->where('kind', 'TRIMS')->get(['id', 'item_code', 'name', 'uom', 'category', 'description', 'active'])->keyBy('id')->all(),
            'orders' => DB::table('merch_orders')->where('company_id', $c)->get(['id', 'order_no', 'style_no', 'po_no', 'buyer_id'])->keyBy('id')->all(),
            'lines' => DB::table('planning_lines')->where('company_id', $c)->get(['id', 'code', 'name'])->mapWithKeys(fn ($l) => [$l->id => trim($l->code.' · '.$l->name)])->all(),
            'company' => DB::table('companies')->where('id', $c)->value('name'),
        ];
    }

    public function item(?string $id): ?string
    {
        $i = $id ? ($this->names()['items'][$id] ?? null) : null;

        return $i ? trim(($i->item_code ? $i->item_code.' — ' : '').$i->name) : null;
    }

    /** "868MI — ORD-11565705 [868MI]", as Brickland names an order / style. */
    public function order(?string $id): ?string
    {
        $o = $id ? ($this->names()['orders'][$id] ?? null) : null;

        return $o ? trim(($o->style_no ?: '—').' — '.$o->order_no.' ['.($o->style_no ?: $o->po_no ?: '—').']') : null;
    }

    /**
     * A store's stock of each item: its moves added up.
     *
     * @return Collection<string, float>
     */
    public static function stock(string $storeId, ?array $itemIds = null): Collection
    {
        return AsmTrimsMove::query()->where('store_id', $storeId)->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds))
            ->groupBy('item_id')->selectRaw('item_id, sum(qty) as q')->pluck('q', 'item_id')->map(fn ($q) => round((float) $q, 4));
    }

    /** Book a move; a move out first checks the store holds that much. */
    public function move(string $storeId, string $itemId, string $kind, string $docNo, ?string $lineId, string $date, float $qty, float $rate, ?string $userId): void
    {
        if ($qty < 0) {
            $have = (float) (self::stock($storeId, [$itemId])[$itemId] ?? 0);
            if ($have + $qty < -0.00005) {
                throw ValidationException::withMessages(['lines' => __('assembly.v.ta_short', ['item' => $this->item($itemId) ?? $itemId, 'store' => $this->names()['stores'][$storeId] ?? '', 'have' => self::fmt($have), 'need' => self::fmt(-$qty)])]);
            }
        }
        AsmTrimsMove::query()->create(['store_id' => $storeId, 'item_id' => $itemId, 'move_date' => $date, 'kind' => $kind, 'doc_no' => $docNo, 'doc_line_id' => $lineId,
            'qty' => $qty, 'value' => round($qty * $rate, 4), 'created_by' => $userId]);
    }

    public static function fmt(float $q): string
    {
        return rtrim(rtrim(number_format($q, 3, '.', ','), '0'), '.');
    }
}
