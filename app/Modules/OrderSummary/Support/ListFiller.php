<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\LookupValue;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\PreBudget\Support\MerchLists;
use Illuminate\Support\Str;

/**
 * Order Summary (AnnotechERP 32): a dropdown value the column's list does
 * not hold yet, added to that list — a lookup list (merchandisers, statuses…)
 * gets a new value, Supplier Master a new supplier (Yarn Mill, Woven Fabric
 * Mill; user, 2026-11-19). Other lists (buyers, colours…) keep the text as
 * written. Used by the Excel import and `ordersummary:fill-lists`.
 */
final class ListFiller
{
    /** Excel's "nothing here" words: kept as text, never made a list value. */
    private const PLACEHOLDERS = ['n/a', 'na', '-', '--', 'nil', 'none', 'tba', 'tbc', 'tbd'];

    /** @var array<string, array<string, string>> source → lower-case value → value as the list spells it */
    private array $lists = [];

    /** @var array<string, int> column label → values added */
    public array $added = [];

    public function __construct(private readonly Sheet $sheet, private readonly ?string $userId) {}

    /** The value as its list spells it; added to the list when the list can take it. */
    public function keep(OsColumn $c, string $v): string
    {
        $v = mb_substr(trim($v), 0, 255);
        $source = (string) $c->source;
        if ($source === '' || $v === '' || in_array(mb_strtolower($v), self::PLACEHOLDERS, true)) {
            return $v;
        }
        $this->lists[$source] ??= collect($this->sheet->options($source))->mapWithKeys(fn ($o) => [mb_strtolower($o['value']) => $o['value']])->all();
        $hit = $this->lists[$source][mb_strtolower($v)] ?? null;
        if ($hit !== null) {
            return $hit;
        }
        if (str_starts_with($source, 'pb:')) {
            // A shared list (Order Type, Item, Wash, Fabric…): matched as both sheets match it, else added to it.
            $list = substr($source, 3);
            $known = MerchLists::find($list, $v, array_values($this->lists[$source]));
            $name = $known ?? MerchLists::ensure($this->sheet->companyId, $list, $v, $this->userId);
            if ($known === null) {
                $this->added[$c->label] = ($this->added[$c->label] ?? 0) + 1;
            }

            return $this->lists[$source][mb_strtolower($v)] = $this->lists[$source][mb_strtolower($name)] = $name;
        }
        if (str_starts_with($source, 'lookup:') && $this->addLookup(substr($source, 7), $v, count($this->lists[$source]))
            || $source === 'suppliers' && $this->addSupplier($v, $c->key)) {
            $this->added[$c->label] = ($this->added[$c->label] ?? 0) + 1;
        }

        return $this->lists[$source][mb_strtolower($v)] = $v;
    }

    private function addLookup(string $code, string $v, int $n): bool
    {
        // The company's own copy of the list when it has one, else the shared list.
        $table = LookupTable::query()->visible()->where('code', $code)->orderByRaw('company_id is null')->first();
        if (! $table) {
            return false;
        }
        $valueCode = Str::upper(Str::limit(Str::slug($v, '_'), 40, '')) ?: 'V';
        if (LookupValue::query()->where('lookup_table_id', $table->id)->where('code', $valueCode)->exists()) {
            $valueCode .= '_'.Str::upper(Str::random(5));
        }
        LookupValue::query()->create([
            'company_id' => $this->sheet->companyId, 'lookup_table_id' => $table->id, 'code' => $valueCode,
            'name' => $v, 'sort_order' => 500 + $n, 'active' => true,
        ]);

        return true;
    }

    /** A new Supplier Master row: the next SUP- number, Local (Non-EPZ), what the column says it supplies. */
    private function addSupplier(string $v, string $key): bool
    {
        $codes = MaterialSupplier::query()->where('code', 'like', 'SUP-%')->pluck('code');
        $next = $codes->map(fn ($c) => (int) preg_replace('/\D/', '', $c))->max() ?? 10000;
        MaterialSupplier::query()->create([
            'code' => 'SUP-'.max($next + 1, 10001), 'name' => $v, 'supplier_type' => 'LOCAL_NON_EPZ',
            'goods_service' => str_contains($key, 'woven') ? 'Woven fabric' : (str_contains($key, 'yarn') ? 'Yarn' : null),
            'active' => true, 'created_by' => $this->userId,
        ]);

        return true;
    }
}
