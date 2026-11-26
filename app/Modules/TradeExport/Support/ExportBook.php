<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Trade & Export → Operations (AnnotechERP 22/02): what the export papers read
 * the same way — company, buyers, branches ("Bank — Branch"), countries, the
 * reference lists, orders with what they are worth, and the fixed choices.
 */
final class ExportBook
{
    public const ITEM_GROUPS = ['Garments', 'Fabric', 'Yarn', 'Trims & Accessories', 'Packaging', 'Dyes & Chemicals', 'General Supplies', 'Machine Spares', 'Stationary Item', 'Plant & Machinery', 'Tools & Equipment'];

    public const COMMODITIES = ['Yarn', 'Fabric', 'Accessories', 'Chemicals', 'Dyes', 'Machinery', 'Spare Parts'];

    public const TRANSPORT_MODES = ['BY SEA', 'BY AIR', 'BY ROAD', 'BY RAIL', 'BY COURIER', 'MULTIMODAL'];

    public const CONTAINER_SIZES = ['20 FT', '40 FT', '40 HC', '45 HC', 'LCL'];

    public const BANK_BILL_TYPES = ['FDBP', 'FDBC', 'DA', 'DP', 'IP'];

    /** @return array<string, string> Field names for validation messages, on the header and inside each grid. */
    public static function attrs(): array
    {
        $out = [];
        foreach (__('tradeop.attr') as $key => $label) {
            $out[$key] = $label;
            foreach (['lines', 'orders', 'distribution'] as $grid) {
                $out["{$grid}.*.{$key}"] = $label;
            }
        }

        return $out;
    }

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly string $companyId) {}

    public function company(): string
    {
        return $this->cache['company'] ??= (string) DB::table('companies')->where('id', $this->companyId)->value('name');
    }

    /** @return array<string, string> Buyer id => "Name (Code)". */
    public function buyers(): array
    {
        return $this->cache['buyers'] ??= DB::table('buyers')->where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($b) => [$b->id => $b->name.($b->code ? ' ('.$b->code.')' : '')])->all();
    }

    /**
     * Each bill of exchange's realisation as it stands, from final papers
     * only: the final realisation where there is one, else the early one.
     * A bill realised early and then finally counts once. `collected` is
     * the final's collection, or the early's invoice amount.
     *
     * @return array<string, object{boe_id: string, type: string, realized_no: string, realized_date: string, rate_ods: float, invoice_amount: float, collected: float, fbc_amount: float, penalty_amount: float}>
     */
    public function realised(array $boeIds): array
    {
        if (! $boeIds) {
            return [];
        }

        return DB::table('tx_bill_realisations')->where('company_id', $this->companyId)->whereIn('boe_id', $boeIds)->where('final', true)
            ->orderByRaw("case when type = 'FINAL' then 0 else 1 end")->orderByDesc('realized_date')
            ->get(['boe_id', 'type', 'realized_no', 'realized_date', 'rate_ods', 'invoice_amount', 'collection_amount', 'fbc_amount', 'penalty_amount'])
            ->groupBy('boe_id')->map(function ($g) {
                $r = $g->first();
                $r->rate_ods = (float) $r->rate_ods;
                $r->invoice_amount = (float) $r->invoice_amount;
                $r->collected = $r->type === 'FINAL' ? (float) $r->collection_amount : $r->invoice_amount;
                $r->fbc_amount = (float) $r->fbc_amount;
                $r->penalty_amount = (float) $r->penalty_amount;

                return $r;
            })->all();
    }

    public function buyerCountry(string $buyerId): string
    {
        $this->cache['buyer_country'] ??= DB::table('buyers')->where('company_id', $this->companyId)->pluck('country_id', 'id')->all();

        return $this->countries()[$this->cache['buyer_country'][$buyerId] ?? ''] ?? '—';
    }

    /** @return array<string, string> */
    public function countries(): array
    {
        return $this->cache['countries'] ??= DB::table('origin_countries')->where('company_id', $this->companyId)->pluck('name', 'id')->all();
    }

    /** @return array<string, string> Branch id => "Bank — Branch". */
    public function branches(): array
    {
        return $this->cache['branches'] ??= DB::table('tx_bank_branches as b')->join('tx_banks as k', 'k.id', '=', 'b.bank_id')->where('b.company_id', $this->companyId)->where('b.active', true)->orderBy('k.name')->orderBy('b.name')
            ->get(['b.id', 'b.name', 'k.name as bank'])->mapWithKeys(fn ($r) => [$r->id => $r->bank.' — '.$r->name])->all();
    }

    public function branchBank(?string $branchId): ?string
    {
        if (! $branchId) {
            return null;
        }
        $this->cache['branch_bank'] ??= DB::table('tx_bank_branches')->where('company_id', $this->companyId)->pluck('bank_id', 'id')->all();

        return $this->cache['branch_bank'][$branchId] ?? null;
    }

    /** @return array<string, list<array{value: string, label: string}>> Each reference list, "Name (CODE)". */
    public function referenceOptions(): array
    {
        TradeExportDefaults::references($this->companyId);

        return DB::table('tx_reference_entries')->where('company_id', $this->companyId)->where('active', true)->orderBy('display_order')->get(['list', 'code', 'name'])
            ->groupBy('list')->map(fn ($g) => $g->map(fn ($r) => ['value' => $r->code, 'label' => $r->name.' ('.$r->code.')'])->values()->all())->all();
    }

    public function reference(string $list, ?string $code): ?string
    {
        if (! $code) {
            return null;
        }
        TradeExportDefaults::references($this->companyId);
        $this->cache['refs'] ??= DB::table('tx_reference_entries')->where('company_id', $this->companyId)->get(['list', 'code', 'name'])->mapWithKeys(fn ($r) => [$r->list.'|'.$r->code => $r->name])->all();

        return $this->cache['refs'][$list.'|'.$code] ?? $code;
    }

    public function refRule(string $list): Exists
    {
        TradeExportDefaults::references($this->companyId);

        return Rule::exists('tx_reference_entries', 'code')->where('company_id', $this->companyId)->where('list', $list);
    }

    /** @return array<string, float> Currency => its latest commercial rate. */
    public function rates(): array
    {
        return DB::table('tx_exchange_rates')->where('company_id', $this->companyId)->orderBy('effective_date')->get(['currency', 'rate'])->mapWithKeys(fn ($r) => [$r->currency => (float) $r->rate])->all();
    }

    /** @return list<array<string, mixed>> Orders as the papers pick them. */
    public function orderOptions(): array
    {
        return DB::table('merch_orders')->where('company_id', $this->companyId)->orderByDesc('order_date')->orderByDesc('order_no')->get(['id', 'order_no', 'style_no', 'buyer_id', 'total_qty', 'unit', 'unit_price', 'item'])
            ->map(fn ($o) => ['value' => $o->id, 'label' => $o->order_no.' — '.$o->style_no, 'order_no' => $o->order_no, 'style_no' => $o->style_no, 'buyer_id' => $o->buyer_id, 'qty' => (float) $o->total_qty, 'unit' => $o->unit, 'unit_price' => (float) ($o->unit_price ?? 0), 'item' => $o->item])->values()->all();
    }

    /** @return list<string> The store's and the procurement masters' item types. */
    public function itemTypes(): array
    {
        return DB::table('mp_items')->where('company_id', $this->companyId)->where('active', true)->distinct()->pluck('category')
            ->merge(DB::table('item_categories')->where('company_id', $this->companyId)->where('active', true)->pluck('name'))->unique()->sort()->values()->all();
    }

    /** @return array<string, string> Export L/C id => the UDs issued over its B2B L/Cs. */
    public function udNos(array $lcIds): array
    {
        return DB::table('tx_ud_lcs as x')->join('tx_uds as u', 'u.id', '=', 'x.ud_id')->join('tx_import_lcs as l', 'l.id', '=', 'x.import_lc_id')->whereIn('l.export_lc_id', $lcIds)->get(['l.export_lc_id', 'u.ud_no'])
            ->groupBy('export_lc_id')->map(fn ($g) => $g->pluck('ud_no')->unique()->implode(', '))->all();
    }

    public function lcHasInvoices(string $lcId): bool
    {
        return DB::table('tx_commercial_invoices')->where('lc_id', $lcId)->exists();
    }

    /** @return list<array<string, mixed>> The Link With PI grid: each line of the given PIs. */
    public function piRows(array $piIds): array
    {
        if (! $piIds) {
            return [];
        }

        return DB::table('tx_export_pi_lines as l')->join('tx_export_pis as p', 'p.id', '=', 'l.pi_id')->whereIn('l.pi_id', $piIds)->orderBy('p.pi_no')->orderBy('l.sort_order')
            ->get(['p.id as pi_id', 'p.pi_no', 'l.item_group', 'l.item_type', 'l.description', 'l.uom', 'l.qty', 'l.unit_price', 'l.total_price', 'p.advance_amount', 'p.discount_amount', 'p.net_amount'])
            ->map(fn ($r) => ['pi_id' => $r->pi_id, 'pi_no' => $r->pi_no, 'item_group' => $r->item_group, 'item_type' => $r->item_type, 'description' => $r->description, 'uom' => $r->uom, 'qty' => (float) $r->qty, 'unit_price' => (float) $r->unit_price, 'total_price' => (float) $r->total_price, 'advance_amount' => (float) $r->advance_amount, 'discount_amount' => (float) $r->discount_amount, 'net_amount' => (float) $r->net_amount])->all();
    }

    public function num(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }
}
