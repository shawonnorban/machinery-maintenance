<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Models\YarnPurchaseOrderLine;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * What Knit Production → Yarn Procurement (AnnotechERP 15/02) shares: the
 * pick lists Brickland's yarn sheets offer — the yarn item (a fibre
 * composition), count, colour, unit, the spinners, stores, buyers and orders,
 * the order's terms — a yarn line's rules, the document numbers and the
 * yarn still owed on placed orders (a comparison's L/C open pipeline).
 */
final class YarnProcurement
{
    /** A yarn line's colour, as Brickland lists it. */
    public const COLOURS = [
        'Average Colour', 'Black / Roya', 'Blue', 'Dark Shade', 'Dyed', 'Extra Dark', 'Grey', 'Heather', 'Light Shade', 'Medium Shade', 'Melange / Y', 'Mixed',
        'Natural', 'Off White', 'PC / CVC', 'Pigment Dyed', 'RFD (Ready for Dyeing)', 'Raw', 'Solid', 'White / Optical', 'Yarn Dyed',
    ];

    public const SOURCES = ['LOCAL', 'IMPORT'];

    /** How a purchase order is paid: L/C, T/T, Cash, Cheque. */
    public const METHODS = ['LC', 'TT', 'CASH', 'CHEQUE'];

    public const INCO_TERMS = ['FOB', 'CIF', 'CFR', 'EXW', 'DDP'];

    public const PAYMENT_TERMS = ['LC-SIGHT', 'LC-30', 'LC-60', 'LC-120', 'TT-AFTER', 'TT-ADV'];

    public const SHIP_MODES = ['SEA', 'AIR', 'ROAD'];

    /** Everything a yarn sheet picks from. */
    public static function options(): array
    {
        $company = app(TenantContext::class)->companyId();
        $orders = Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('created_at')->limit(500)->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no', 'buyer_id']);

        return [
            'company' => ['value' => $company, 'label' => DB::table('companies')->where('id', $company)->value('name')],
            'stores' => Store::query()->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'name', 'short_name', 'code'])->map(fn (Buyer $b) => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')])->values()->all(),
            // An order as Brickland's Style/Order# names it: 529 · LPP-2026-000008.
            'orders' => $orders->map(fn (Order $o) => ['value' => $o->id, 'label' => trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' · '.$o->order_no, ' ·'), 'buyer_id' => $o->buyer_id,
                'style' => $o->work_style_no ?: $o->style_no, 'order_no' => $o->order_no])->values()->all(),
            'compositions' => DB::table('fiber_compositions')->where('company_id', $company)->where('active', true)->orderBy('description')->get(['id', 'code', 'description'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            'counts' => KnitYarnCount::query()->where('active', true)->orderBy('display_order')->orderBy('description')->get()->map(fn (KnitYarnCount $c) => ['value' => $c->id, 'label' => (string) $c->description])->values()->all(),
            'colours' => self::COLOURS,
            'uoms' => array_map(fn (array $u) => ['value' => $u['code'], 'label' => $u['code']], UomCodes::options($company)),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'currencies' => DB::table('currencies')->where('company_id', $company)->where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'exchange_rate'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => "{$c->code} — {$c->name}", 'code' => $c->code, 'rate' => $c->exchange_rate !== null ? (float) $c->exchange_rate : null])->values()->all(),
            'sources' => self::SOURCES, 'methods' => self::METHODS, 'inco_terms' => self::INCO_TERMS, 'payment_terms' => self::PAYMENT_TERMS, 'ship_modes' => self::SHIP_MODES,
        ];
    }

    /**
     * A yarn line's rules under `lines.*.` (item, count, colour, brand, unit, dates, remarks).
     *
     * @return array<string, mixed>
     */
    public static function lineRules(string $prefix): array
    {
        $company = app(TenantContext::class)->companyId();

        return [
            "{$prefix}.composition_id" => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            "{$prefix}.yarn_count_id" => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            "{$prefix}.colour" => ['nullable', Rule::in(self::COLOURS)],
            "{$prefix}.brand" => ['nullable', 'string', 'max:80'],
            "{$prefix}.uom" => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            "{$prefix}.knit_start_date" => ['nullable', 'date'],
            "{$prefix}.ship_date" => ['nullable', 'date'],
            "{$prefix}.remarks" => ['nullable', 'string', 'max:300'],
        ];
    }

    /** The yarn fields a line carries from sheet to sheet. */
    public const YARN = ['composition_id', 'yarn_count_id', 'colour', 'brand', 'uom', 'knit_start_date', 'ship_date', 'remarks'];

    /**
     * YR-2026100001, YCS-2026100001, YPO-2026100001: the prefix, the year and
     * month, then the company's next document that month; held to the end of the save.
     */
    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        $head = $prefix.'-'.CarbonImmutable::parse($date)->format('Ym');
        $company = app(TenantContext::class)->companyId();
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$head}:{$company}"]);
        $n = 1 + (int) DB::table($table)->where('company_id', $company)->where($column, 'like', $head.'%')->pluck($column)->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * L/C open pipeline: the yarn of this item and count still owed on placed
     * purchase orders — ordered less received.
     */
    public static function pipeline(?string $compositionId, ?string $countId): float
    {
        $open = YarnPurchaseOrder::query()->whereIn('status', YarnPurchaseOrder::OPEN)->pluck('id');

        return round((float) YarnPurchaseOrderLine::query()->whereIn('po_id', $open)
            ->where('composition_id', $compositionId)->where('yarn_count_id', $countId)
            ->get(['qty', 'received_qty'])->sum(fn ($l) => max(0, (float) $l->qty - (float) $l->received_qty)), 4);
    }

    /** Present stock of this item and count across the yarn stores (AnnotechERP 15/03's ledger). */
    public static function presentStock(?string $compositionId, ?string $countId): float
    {
        return YarnStore::stockOf($compositionId, $countId);
    }

    /** The names a line's ids stand for, for lists and prints. */
    public static function names(): array
    {
        $company = app(TenantContext::class)->companyId();

        return [
            'compositions' => DB::table('fiber_compositions')->where('company_id', $company)->pluck('description', 'id')->all(),
            'counts' => KnitYarnCount::query()->pluck('description', 'id')->all(),
        ];
    }
}
