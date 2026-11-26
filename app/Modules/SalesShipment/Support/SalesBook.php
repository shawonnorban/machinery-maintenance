<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Support;

use App\Modules\SalesShipment\Models\SsParticular;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02): what every screen
 * of the sub-module reads the same way — the names it prints (customers as
 * "name (code)", currencies, sections, items, stores, countries, users), the
 * day-numbered papers (SO-20261107-0001), the figures counted per order size
 * (ordered, held by a live challan, gone out, come back, net delivered) and
 * per challan line (delivered, returned, billed), and the month lock.
 */
final class SalesBook
{
    /** @var array<string, array<string, string>> */
    private array $cache = [];

    public function __construct(private readonly string $companyId) {}

    /* ── Names ─────────────────────────────────────────────────────── */

    /** @return array<string, string> */
    public function customers(): array
    {
        return $this->cache['customers'] ??= DB::table('mp_suppliers')->where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->name.' ('.$s->code.')'])->all();
    }

    /** @return array<string, string> */
    public function customerNames(): array
    {
        return $this->cache['customer_names'] ??= DB::table('mp_suppliers')->where('company_id', $this->companyId)->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function addresses(): array
    {
        return $this->cache['addresses'] ??= DB::table('mp_suppliers')->where('company_id', $this->companyId)->pluck('address', 'id')->map(fn ($a) => (string) $a)->all();
    }

    /** @return array<string, string> */
    public function currencies(): array
    {
        return $this->cache['currencies'] ??= DB::table('currencies')->where('company_id', $this->companyId)->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all();
    }

    /** @return array<string, string> */
    public function currencyCodes(): array
    {
        return $this->cache['currency_codes'] ??= DB::table('currencies')->where('company_id', $this->companyId)->pluck('code', 'id')->all();
    }

    /** @return array<string, string> Section — its department, as Brickland lists them. */
    public function sections(): array
    {
        return $this->cache['sections'] ??= DB::table('sections')->leftJoin('departments', 'departments.id', '=', 'sections.department_id')
            ->where('sections.company_id', $this->companyId)->orderBy('sections.name')->get(['sections.id', 'sections.name', 'departments.name as dept'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->dept ? ' — '.$s->dept : '')])->all();
    }

    /** @return array<string, string> */
    public function sectionNames(): array
    {
        return $this->cache['section_names'] ??= DB::table('sections')->where('company_id', $this->companyId)->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function items(): array
    {
        return $this->cache['items'] ??= DB::table('mp_items')->where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name', 'item_code'])
            ->mapWithKeys(fn ($i) => [$i->id => $i->name.($i->item_code ? ' ('.$i->item_code.')' : '')])->all();
    }

    /** @return array<string, string> */
    public function itemNames(): array
    {
        return $this->cache['item_names'] ??= DB::table('mp_items')->where('company_id', $this->companyId)->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function stores(): array
    {
        return $this->cache['stores'] ??= DB::table('stores')->where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->code ? ' ('.$s->code.')' : '')])->all();
    }

    /** @return array<string, string> */
    public function countries(): array
    {
        return $this->cache['countries'] ??= DB::table('origin_countries')->where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')'])->all();
    }

    /** @return array<string, string> The particulars a line can carry — Brickland's three until the company names more. */
    public function particulars(): array
    {
        if (! isset($this->cache['particulars'])) {
            if (! DB::table('ss_particulars')->where('company_id', $this->companyId)->exists()) {
                foreach (['Carton Dimension', 'Poly Thickness', 'Print Placement'] as $name) {
                    SsParticular::query()->create(['company_id' => $this->companyId, 'name' => $name, 'active' => true]);
                }
            }
            $this->cache['particulars'] = DB::table('ss_particulars')->where('company_id', $this->companyId)->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
        }

        return $this->cache['particulars'];
    }

    public function user(?string $id): ?string
    {
        if (! $id) {
            return null;
        }
        $this->cache['users'] ??= [];

        return $this->cache['users'][$id] ??= (string) DB::table('users')->where('id', $id)->value('name');
    }

    public function company(): string
    {
        return $this->cache['company']['name'] ??= (string) DB::table('companies')->where('id', $this->companyId)->value('name');
    }

    /** "Rahim Uddin · 07 Nov 2026", or null when the step has not been taken. */
    public function trail(?string $by, mixed $at): ?string
    {
        return $at ? trim(($this->user($by) ?: '—').' · '.CarbonImmutable::parse($at)->timezone('Asia/Dhaka')->format('d M Y')) : null;
    }

    /* ── Numbers ───────────────────────────────────────────────────── */

    /** The next day-numbered paper: PREFIX-YYYYMMDD-0001, the date's own sequence. */
    public function nextNo(string $prefix, string $table, string $column, string $date): string
    {
        $stem = $prefix.'-'.str_replace('-', '', substr($date, 0, 10)).'-';
        $last = DB::table($table)->where('company_id', $this->companyId)->where($column, 'like', $stem.'%')->orderByDesc($column)->value($column);

        return $stem.str_pad((string) ($last ? (int) substr($last, strlen($stem)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    /* ── Figures ───────────────────────────────────────────────────── */

    /**
     * Per order size: `held` — on every challan not cancelled (drafts too, so
     * two desks cannot promise the same pieces), less `except` challan;
     * `out` — gone through the gate (submitted or delivered); `returned` —
     * on confirmed returns; `net` = out − returned.
     *
     * @param  list<string>  $orderIds
     * @return Collection<string, array{held: float, out: float, returned: float, net: float}>
     */
    public function sizeFigures(array $orderIds, ?string $exceptChallan = null): Collection
    {
        if (! $orderIds) {
            return collect();
        }
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orderIds)->pluck('id');
        $lines = DB::table('ss_challan_lines as l')->join('ss_challans as c', 'c.id', '=', 'l.challan_id')->whereIn('l.size_id', $sizes)->where('c.state', '!=', 'CANCELLED')
            ->get(['l.size_id', 'l.qty', 'c.state', 'c.id as challan_id']);
        $returned = DB::table('ss_challan_return_lines as rl')->join('ss_challan_returns as r', 'r.id', '=', 'rl.return_id')->join('ss_challan_lines as l', 'l.id', '=', 'rl.challan_line_id')
            ->whereIn('l.size_id', $sizes)->where('r.state', 'CONFIRMED')->groupBy('l.size_id')->selectRaw('l.size_id, sum(rl.qty) as qty')->pluck('qty', 'size_id');

        return $sizes->mapWithKeys(function ($id) use ($lines, $returned, $exceptChallan) {
            $mine = $lines->where('size_id', $id);
            $out = (float) $mine->whereIn('state', ['SUBMITTED', 'DELIVERED'])->sum('qty');
            $back = (float) ($returned[$id] ?? 0);

            return [$id => ['held' => (float) $mine->where('challan_id', '!=', $exceptChallan)->sum('qty'), 'out' => $out, 'returned' => $back, 'net' => $out - $back]];
        });
    }

    /**
     * Per challan line: delivered, returned (on returns not cancelled, less
     * `exceptReturn`), confirmed returns, and billed (on bills not rejected,
     * less `exceptBill`).
     *
     * @param  list<string>  $lineIds
     * @return Collection<string, array{delivered: float, returned: float, confirmed: float, billed: float}>
     */
    public function lineFigures(array $lineIds, ?string $exceptReturn = null, ?string $exceptBill = null): Collection
    {
        if (! $lineIds) {
            return collect();
        }
        $qty = DB::table('ss_challan_lines')->whereIn('id', $lineIds)->pluck('qty', 'id');
        $ret = DB::table('ss_challan_return_lines as rl')->join('ss_challan_returns as r', 'r.id', '=', 'rl.return_id')->whereIn('rl.challan_line_id', $lineIds)->where('r.state', '!=', 'CANCELLED')
            ->get(['rl.challan_line_id', 'rl.qty', 'r.state', 'r.id']);
        $bill = DB::table('ss_bill_lines as bl')->join('ss_bills as b', 'b.id', '=', 'bl.bill_id')->whereIn('bl.challan_line_id', $lineIds)->where('b.state', '!=', 'REJECTED')
            ->get(['bl.challan_line_id', 'bl.qty', 'b.id']);

        return $qty->mapWithKeys(fn ($q, $id) => [$id => [
            'delivered' => (float) $q,
            'returned' => (float) $ret->where('challan_line_id', $id)->where('id', '!=', $exceptReturn)->sum('qty'),
            'confirmed' => (float) $ret->where('challan_line_id', $id)->where('state', 'CONFIRMED')->sum('qty'),
            'billed' => (float) $bill->where('challan_line_id', $id)->where('id', '!=', $exceptBill)->sum('qty'),
        ]]);
    }

    /** "Item · Colour · Size" of order sizes, keyed by size id. @return array<string, string> */
    public function sizeLabels(array $sizeIds): array
    {
        $items = $this->itemNames();

        return DB::table('ss_sales_order_sizes as s')->join('ss_sales_order_lines as l', 'l.id', '=', 's.line_id')->whereIn('s.id', $sizeIds)
            ->get(['s.id', 's.size', 'l.item_id', 'l.gmt_color', 'l.sl_no', 'l.style_ref'])
            ->mapWithKeys(fn ($r) => [$r->id => implode(' · ', array_filter(['#'.$r->sl_no, $items[$r->item_id] ?? '—', $r->gmt_color, $r->size]))])->all();
    }

    /* ── Month lock ────────────────────────────────────────────────── */

    /** 2026-07 from "2026-07" or "Jul/2026"; null when it is neither. */
    public static function period(string $text): ?string
    {
        $text = trim($text);
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $text)) {
            return $text;
        }
        if (preg_match('/^([A-Za-z]{3})\/(\d{4})$/', $text, $m)) {
            $i = array_search(ucfirst(strtolower($m[1])), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], true);

            return $i === false ? null : sprintf('%s-%02d', $m[2], $i + 1);
        }

        return null;
    }

    /** Refuses a paper dated inside a closed month. */
    public function assertOpen(mixed $date, string $field = 'date'): void
    {
        if (! $date) {
            return;
        }
        $period = substr(CarbonImmutable::parse($date)->toDateString(), 0, 7);
        if (DB::table('ss_month_closes')->where('company_id', $this->companyId)->where('period', $period)->exists()) {
            throw ValidationException::withMessages([$field => __('salesshipment.v.month_closed', ['period' => CarbonImmutable::parse($period.'-01')->format('M/Y')])]);
        }
    }
}
