<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\TradeExport\Support\ExportBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → Report (AnnotechERP 22/06): Brickland's Trade & Export
 * Reports — one screen, a report picked from the list, the boxes it reads
 * (company, buyer, export L/C, invoice no, from / to, ex-factory from / to,
 * invoice negotiated?, bill type?, on board?; a box the report does not read
 * is disabled), Preview or Export to Excel.
 *
 * Brickland draws only the parameter screen; every report body here reads
 * the module's own papers: export L/Cs and their orders, commercial invoices,
 * document lodgements, bills of exchange and their realisations, CM receipts,
 * cash incentive claims; on the import side the B2B L/Cs, their PIs, bills of
 * entry, acceptances (IFDBC) and the UDs.
 *
 * A bill realised early and then finally counts once (ExportBook::realised).
 * "From / To" is the report's own date: an L/C's issue date for the L/C
 * registers, the paper's date for the paper lists, as each report states.
 */
class TradeReportApiController extends ApiController
{
    private const INV = ['buyer', 'lc', 'invoice', 'from', 'to', 'xf_from', 'xf_to', 'negotiated', 'bill_type', 'on_board'];

    private const LC = ['buyer', 'lc', 'from', 'to'];

    /** Each report and the boxes it reads. */
    public const REPORTS = [
        'export-invoice' => self::INV,
        'import-export-register' => self::LC,
        'ifdbc-payment-schedule' => self::LC,
        'bblc-liability-status' => self::LC,
        'cost-against-realization' => ['buyer', 'lc', 'from', 'to', 'bill_type'],
        'ifdbc-payment-list' => self::LC,
        'export-lc-list' => self::LC,
        'bblc-summary' => self::LC,
        'lc-wise-realization' => self::LC,
        'lc-wise-cash-incentive' => self::LC,
        'imp-lc-wise-bill-of-entry' => self::LC,
        'import-lc-application' => self::LC,
        'bblc-ifdbc-liabilities' => self::LC,
        'statement-of-import-lc' => self::LC,
        'ifdbc-outstanding' => self::LC,
        'export-payment-followup' => self::INV,
        'monthly-export-summary' => ['buyer', 'lc', 'from', 'to', 'negotiated', 'on_board'],
        'boe-wise-invoices' => ['buyer', 'lc', 'invoice', 'from', 'to', 'bill_type'],
        'ud-summary' => ['lc', 'from', 'to'],
        'weekly-margin-bblc' => ['lc', 'from', 'to'],
        'lc-opening-bblc-ratio' => self::LC,
        'customs-bond' => ['lc', 'from', 'to'],
        'invoice-boe-collection' => ['buyer', 'lc', 'invoice', 'from', 'to', 'bill_type'],
        'export-invoice-profile' => self::INV,
        'b2b-order-summary' => self::LC,
        'doc-export-invoices' => ['buyer', 'lc', 'invoice', 'from', 'to'],
        'bank-wise-margin' => ['from', 'to'],
        'exp-boe-profile' => ['buyer', 'lc', 'from', 'to', 'bill_type'],
        'short-shipment-status' => self::LC,
        'fund-forecast' => ['lc', 'from', 'to'],
        'btb-liability-acceptance' => self::LC,
    ];

    private const MAX_RANGE_DAYS = 366;

    /** Columns whose total means nothing. */
    private const NO_TOTAL = ['rate', 'days', 'bb_limit_pct', 'ratio', 'realized_pct', 'short_pct', 'cost_pct', 'tenor_days', 'part'];

    private ?ExportBook $book = null;

    private array $q = [];

    private ?Collection $suppliers = null;

    public function options(): JsonResponse
    {
        $this->allow('tradeexport.report.view');
        $c = $this->caller()->companyId;
        $b = $this->book();
        $today = CarbonImmutable::now('Asia/Dhaka');

        return ApiResponse::ok([
            'today' => $today->toDateString(),
            'year_start' => $today->startOfYear()->toDateString(),
            'reports' => collect(self::REPORTS)->map(fn ($p, $kind) => ['kind' => $kind, 'params' => $p])->values()->all(),
            'companies' => [['value' => $c, 'label' => $b->company()]],
            'buyers' => collect($b->buyers())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'lcs' => DB::table('tx_export_lcs')->where('company_id', $c)->orderByDesc('issue_date')->get(['id', 'lc_no', 'buyer_id'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->lc_no, 'buyer_id' => $l->buyer_id])->values()->all(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $this->allow('tradeexport.report.view');
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(self::REPORTS))]])['kind'];
        $c = $this->caller()->companyId;
        $q = $request->validate([
            'company' => ['nullable', Rule::in([$c])],
            'buyer' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'lc' => ['nullable', Rule::exists('tx_export_lcs', 'id')->where('company_id', $c)],
            'invoice' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'xf_from' => ['nullable', 'date'], 'xf_to' => ['nullable', 'date'],
            'negotiated' => ['nullable', Rule::in(['YES', 'NO'])],
            'bill_type' => ['nullable', Rule::in(['FDBP', 'FDBC'])],
            'on_board' => ['nullable', Rule::in(['WITH', 'WITHOUT'])],
        ]);
        $q = array_filter(array_intersect_key($q, array_flip(self::REPORTS[$kind])), fn ($v) => $v !== null && $v !== '');
        foreach ([['from', 'to'], ['xf_from', 'xf_to']] as [$a, $z]) {
            if (! empty($q[$a]) && ! empty($q[$z])) {
                if ($q[$z] < $q[$a]) {
                    throw ValidationException::withMessages([$z => __('tradeop.v.rp_range')]);
                }
                if (CarbonImmutable::parse($q[$a])->diffInDays(CarbonImmutable::parse($q[$z])) + 1 > self::MAX_RANGE_DAYS) {
                    throw ValidationException::withMessages([$z => __('tradeop.v.rp_range_max', ['days' => self::MAX_RANGE_DAYS])]);
                }
            }
        }
        $this->q = $q;
        [$columns, $rows] = $this->{'r'.str_replace('-', '', ucwords($kind, '-'))}();
        $columns = array_map(function ($col) {
            [$key, $type] = array_pad(explode(':', $col, 2), 2, 'text');

            return ['key' => $key, 'type' => $type, 'numeric' => in_array($type, ['qty', 'money', 'num', 'pct'], true)];
        }, $columns);
        $rows = array_values(is_array($rows) ? $rows : $rows->all());
        $totals = [];
        foreach ($columns as $col) {
            if ($col['numeric'] && $col['type'] !== 'pct' && ! in_array($col['key'], self::NO_TOTAL, true)) {
                $totals[$col['key']] = round((float) collect($rows)->sum($col['key']), $col['type'] === 'money' ? 2 : 4);
            }
        }

        return ApiResponse::ok(['kind' => $kind, 'columns' => $columns, 'rows' => $rows, 'totals' => $rows ? $totals : []]);
    }

    /* ── Export L/C registers ──────────────────────────────────────── */

    private function rExportLcList(): array
    {
        $b = $this->book();
        $lcs = $this->lcs()->get();
        $uds = $b->udNos($lcs->pluck('id')->all());
        $rows = $lcs->map(fn ($l) => ['lc_no' => $l->lc_no, 'kind' => __("tradeop.kind.{$l->kind}"), 'buyer' => $b->buyers()[$l->buyer_id] ?? '—', 'issue_date' => $l->issue_date, 'expiry_date' => $l->expiry_date, 'last_shipment' => $l->last_shipment_date,
            'currency' => $l->currency, 'lc_value' => (float) $l->lc_value, 'payment_term' => $b->reference('PAYMENT_TERM', $l->payment_term) ?? $l->payment_term, 'bank' => $this->bank($l->receiving_branch_id), 'ud_no' => $uds[$l->id] ?? null, 'status' => __("tradeop.action.{$l->status}")]);

        return [['lc_no', 'kind', 'buyer', 'issue_date:date', 'expiry_date:date', 'last_shipment:date', 'currency', 'lc_value:money', 'payment_term', 'bank', 'ud_no', 'status'], $rows];
    }

    private function rImportExportRegister(): array
    {
        $lcs = $this->lcs()->get();
        $ids = $lcs->pluck('id')->all();
        $inv = $this->invoicedByLc($ids);
        $real = $this->realisedByLc($ids);
        $b2b = $this->b2bByLc($ids);
        $entries = DB::table('tx_bills_of_entry as be')->join('tx_import_lcs as il', 'il.id', '=', 'be.lc_id')->whereIn('il.export_lc_id', $ids)->groupBy('il.export_lc_id')->selectRaw('il.export_lc_id, sum(be.bill_amount) as amt')->pluck('amt', 'export_lc_id');
        $rows = $lcs->map(fn ($l) => $this->lcHead($l) + ['exported' => $inv[$l->id] ?? 0.0, 'realized' => $real[$l->id]['amount'] ?? 0.0, 'b2b_count' => $b2b[$l->id]['count'] ?? 0, 'b2b_value' => $b2b[$l->id]['value'] ?? 0.0,
            'imported' => (float) ($entries[$l->id] ?? 0), 'ratio' => (float) $l->lc_value > 0 ? round(($b2b[$l->id]['value'] ?? 0) / (float) $l->lc_value * 100, 2) : null]);

        return [['lc_no', 'buyer', 'issue_date:date', 'lc_value:money', 'exported:money', 'realized:money', 'b2b_count:num', 'b2b_value:money', 'imported:money', 'ratio:pct'], $rows];
    }

    private function rBblcSummary(): array
    {
        $lcs = $this->lcs()->get();
        $b2b = $this->b2bByLc($lcs->pluck('id')->all());
        $rows = $lcs->map(function ($l) use ($b2b) {
            $limit = $l->bb_limit_pct === null ? null : round((float) $l->lc_value * (float) $l->bb_limit_pct / 100, 2);
            $used = $b2b[$l->id]['value'] ?? 0.0;

            return $this->lcHead($l) + ['bb_limit_pct' => $l->bb_limit_pct === null ? null : (float) $l->bb_limit_pct, 'limit' => $limit, 'b2b_count' => $b2b[$l->id]['count'] ?? 0, 'b2b_value' => $used, 'left' => $limit === null ? null : round($limit - $used, 2)];
        });

        return [['lc_no', 'buyer', 'issue_date:date', 'lc_value:money', 'bb_limit_pct:pct', 'limit:money', 'b2b_count:num', 'b2b_value:money', 'left:money'], $rows];
    }

    private function rLcOpeningBblcRatio(): array
    {
        $lcs = $this->lcs()->get();
        $b2b = $this->b2bByLc($lcs->pluck('id')->all());
        $rows = $lcs->map(function ($l) use ($b2b) {
            $used = $b2b[$l->id]['value'] ?? 0.0;
            $ratio = (float) $l->lc_value > 0 ? round($used / (float) $l->lc_value * 100, 2) : null;

            return $this->lcHead($l) + ['b2b_value' => $used, 'ratio' => $ratio, 'bb_limit_pct' => $l->bb_limit_pct === null ? null : (float) $l->bb_limit_pct,
                'within' => $l->bb_limit_pct === null ? '—' : __('tradeop.yn.'.($ratio !== null && $ratio <= (float) $l->bb_limit_pct + 0.005 ? 'YES' : 'NO'))];
        });

        return [['lc_no', 'buyer', 'issue_date:date', 'lc_value:money', 'b2b_value:money', 'ratio:pct', 'bb_limit_pct:pct', 'within'], $rows];
    }

    private function rLcWiseRealization(): array
    {
        $lcs = $this->lcs()->get();
        $ids = $lcs->pluck('id')->all();
        $inv = $this->invoicedByLc($ids);
        $bills = DB::table('tx_bills_of_exchange')->whereIn('lc_id', $ids)->where('final', true)->groupBy('lc_id')->selectRaw('lc_id, sum(bill_amount) as amt')->pluck('amt', 'lc_id');
        $real = $this->realisedByLc($ids);
        $rows = $lcs->map(function ($l) use ($inv, $bills, $real) {
            $i = $inv[$l->id] ?? 0.0;
            $r = $real[$l->id]['amount'] ?? 0.0;

            return $this->lcHead($l) + ['exported' => $i, 'billed' => (float) ($bills[$l->id] ?? 0), 'realized' => $r, 'collected' => $real[$l->id]['collected'] ?? 0.0, 'pending' => round($i - $r, 2), 'realized_pct' => $i > 0 ? round($r / $i * 100, 2) : null];
        });

        return [['lc_no', 'buyer', 'issue_date:date', 'lc_value:money', 'exported:money', 'billed:money', 'realized:money', 'collected:money', 'pending:money', 'realized_pct:pct'], $rows];
    }

    private function rShortShipmentStatus(): array
    {
        $b = $this->book();
        $lcs = $this->lcs()->get()->keyBy('id');
        $lines = DB::table('tx_export_lc_orders as x')->join('merch_orders as o', 'o.id', '=', 'x.order_id')->whereIn('x.lc_id', $lcs->keys())->get(['x.lc_id', 'x.order_id', 'x.qty', 'x.unit_price', 'o.order_no', 'o.style_no']);
        $shipped = DB::table('tx_commercial_invoice_lines as il')->join('tx_commercial_invoices as i', 'i.id', '=', 'il.invoice_id')->whereIn('i.lc_id', $lcs->keys())->groupBy('i.lc_id', 'il.order_id')->selectRaw('i.lc_id, il.order_id, sum(il.inv_qty) as qty')->get()
            ->mapWithKeys(fn ($r) => ["{$r->lc_id}|{$r->order_id}" => (float) $r->qty]);
        $rows = $lines->map(function ($x) use ($lcs, $shipped, $b) {
            $l = $lcs[$x->lc_id];
            $s = $shipped["{$x->lc_id}|{$x->order_id}"] ?? 0.0;
            $short = max(0.0, (float) $x->qty - $s);

            return ['lc_no' => $l->lc_no, 'buyer' => $b->buyers()[$l->buyer_id] ?? '—', 'order_no' => $x->order_no, 'style_no' => $x->style_no, 'lc_qty' => (float) $x->qty, 'shipped' => $s, 'short_qty' => $short,
                'short_pct' => (float) $x->qty > 0 ? round($short / (float) $x->qty * 100, 2) : null, 'short_value' => round($short * (float) $x->unit_price, 2)];
        })->sortBy(['lc_no', 'order_no']);

        return [['lc_no', 'buyer', 'order_no', 'style_no', 'lc_qty:qty', 'shipped:qty', 'short_qty:qty', 'short_pct:pct', 'short_value:money'], $rows];
    }

    /* ── Export invoices ───────────────────────────────────────────── */

    private function rExportInvoice(): array
    {
        $b = $this->book();
        $rows = $this->invoices()->map(fn ($i) => ['invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date, 'lc_no' => $i->lc_no, 'buyer' => $b->buyers()[$i->buyer_id] ?? '—', 'exp_no' => $i->exp_no, 'ex_factory_date' => $i->ex_factory_date,
            'on_board_date' => $i->on_board_date, 'bl_no' => $i->bl_no, 'qty' => $i->qty, 'ctn' => $i->ctn, 'currency' => $i->currency, 'amount' => (float) $i->invoiced_amount, 'net_amount' => (float) $i->net_amount, 'negotiated' => __('tradeop.yn.'.($i->is_negotiated ? 'YES' : 'NO')), 'boe_no' => $i->boe_no]);

        return [['invoice_no', 'invoice_date:date', 'lc_no', 'buyer', 'exp_no', 'ex_factory_date:date', 'on_board_date:date', 'bl_no', 'qty:qty', 'ctn:num', 'currency', 'amount:money', 'net_amount:money', 'negotiated', 'boe_no'], $rows];
    }

    private function rExportInvoiceProfile(): array
    {
        $b = $this->book();
        $inv = $this->invoices();
        $orders = DB::table('tx_commercial_invoice_lines as il')->join('merch_orders as o', 'o.id', '=', 'il.order_id')->whereIn('il.invoice_id', $inv->pluck('id'))
            ->get(['il.invoice_id', 'o.order_no', 'o.style_no', 'il.hs_code', 'il.net_weight', 'il.gross_weight', 'il.cbm'])->groupBy('invoice_id');
        $rows = $inv->map(function ($i) use ($b, $orders) {
            $o = $orders->get($i->id, collect());

            return ['invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date, 'lc_no' => $i->lc_no, 'buyer' => $b->buyers()[$i->buyer_id] ?? '—', 'order_no' => $o->pluck('order_no')->unique()->implode(', '), 'style_no' => $o->pluck('style_no')->unique()->implode(', '),
                'hs_code' => $o->pluck('hs_code')->filter()->unique()->implode(', ') ?: null, 'exp_no' => $i->exp_no, 'exp_date' => $i->exp_date, 'bl_no' => $i->bl_no, 'bl_date' => $i->bl_date, 'vessel_no' => $i->vessel_no, 'container_no' => $i->container_no,
                'port_of_loading' => $i->port_of_loading, 'port_of_discharge' => $i->port_of_discharge, 'country' => $b->countries()[$i->delivery_country_id] ?? '—', 'shipment_mode' => $i->shipment_mode,
                'qty' => $i->qty, 'net_weight' => (float) $o->sum('net_weight'), 'gross_weight' => (float) $o->sum('gross_weight'), 'cbm' => (float) $o->sum('cbm'), 'net_amount' => (float) $i->net_amount];
        });

        return [['invoice_no', 'invoice_date:date', 'lc_no', 'buyer', 'order_no', 'style_no', 'hs_code', 'exp_no', 'exp_date:date', 'bl_no', 'bl_date:date', 'vessel_no', 'container_no', 'port_of_loading', 'port_of_discharge', 'country', 'shipment_mode', 'qty:qty', 'net_weight:qty', 'gross_weight:qty', 'cbm:qty', 'net_amount:money'], $rows];
    }

    private function rExportPaymentFollowup(): array
    {
        $b = $this->book();
        $inv = $this->invoices();
        $boes = DB::table('tx_bills_of_exchange')->whereIn('id', $inv->pluck('boe_id')->filter()->unique())->get()->keyBy('id');
        $real = $b->realised($boes->keys()->all());
        $rows = $inv->map(function ($i) use ($b, $boes, $real) {
            $e = $i->boe_id ? ($boes[$i->boe_id] ?? null) : null;
            $r = $e ? ($real[$e->id] ?? null) : null;
            $status = $r ? 'REALISED' : ($e ? ($e->final ? 'BILLED' : 'DRAFT_BILL') : 'NOT_BILLED');

            return ['invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date, 'lc_no' => $i->lc_no, 'buyer' => $b->buyers()[$i->buyer_id] ?? '—', 'net_amount' => (float) $i->net_amount, 'target_date' => $i->target_collection_date,
                'boe_no' => $e?->bill_no, 'bill_date' => $e?->bill_date, 'submit_date' => $e ? ($e->bank_submit_date ?? $e->ref_date) : null, 'due_date' => $e?->due_date, 'realized_date' => $r?->realized_date, 'realized' => $r ? $r->invoice_amount : 0.0,
                'status' => __("tradeop.rp.status.{$status}")];
        });

        return [['invoice_no', 'invoice_date:date', 'lc_no', 'buyer', 'net_amount:money', 'target_date:date', 'boe_no', 'bill_date:date', 'submit_date:date', 'due_date:date', 'realized_date:date', 'realized:money', 'status'], $rows];
    }

    private function rMonthlyExportSummary(): array
    {
        $inv = $this->invoices();
        $real = $this->book()->realised($inv->pluck('boe_id')->filter()->unique()->values()->all());
        $rows = $inv->groupBy(fn ($i) => substr((string) $i->invoice_date, 0, 7))->map(fn (Collection $g, $m) => [
            'month' => CarbonImmutable::parse("{$m}-01")->format('M Y'), 'invoices' => $g->count(), 'qty' => $g->sum('qty'), 'ctn' => $g->sum('ctn'), 'amount' => round((float) $g->sum('invoiced_amount'), 2), 'net_amount' => round((float) $g->sum('net_amount'), 2),
            'billed' => round((float) $g->filter(fn ($i) => $i->boe_id)->sum('net_amount'), 2), 'realized' => round((float) $g->pluck('boe_id')->filter()->unique()->sum(fn ($id) => isset($real[$id]) ? $real[$id]->invoice_amount : 0), 2),
        ])->sortKeys();

        return [['month', 'invoices:num', 'qty:qty', 'ctn:num', 'amount:money', 'net_amount:money', 'billed:money', 'realized:money'], $rows];
    }

    private function rDocExportInvoices(): array
    {
        $b = $this->book();
        $bills = DB::table('tx_export_bills as eb')->where('eb.company_id', $this->caller()->companyId)->tap(fn ($w) => $this->dated($w, 'eb.bill_date'))->orderBy('eb.bill_date')->orderBy('eb.bill_no')->get();
        $inv = DB::table('tx_export_bill_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->join('tx_export_lcs as l', 'l.id', '=', 'i.lc_id')->whereIn('x.bill_id', $bills->pluck('id'))
            ->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('l.buyer_id', $v))->when($this->q['lc'] ?? null, fn ($w, $v) => $w->where('l.id', $v))->when($this->q['invoice'] ?? null, fn ($w, $v) => $w->whereRaw('lower(i.invoice_no) like ?', ['%'.mb_strtolower($v).'%']))
            ->get(['x.bill_id', 'i.invoice_no', 'i.net_amount', 'l.lc_no', 'l.buyer_id'])->groupBy('bill_id');
        $rows = $bills->filter(fn ($e) => $inv->has($e->id))->map(function ($e) use ($inv, $b) {
            $g = $inv[$e->id];

            return ['bill_no' => $e->bill_no, 'bill_date' => $e->bill_date, 'lc_no' => $g->pluck('lc_no')->unique()->implode(', '), 'buyer' => $g->pluck('buyer_id')->unique()->map(fn ($id) => $b->buyers()[$id] ?? '—')->implode(', '),
                'invoice_list' => $g->pluck('invoice_no')->implode(', '), 'invoices' => $g->count(), 'net_amount' => round((float) $g->sum('net_amount'), 2), 'charge' => (float) $e->charge, 'final' => __('tradeop.yn.'.($e->final ? 'YES' : 'NO'))];
        });

        return [['bill_no', 'bill_date:date', 'lc_no', 'buyer', 'invoice_list', 'invoices:num', 'net_amount:money', 'charge:money', 'final'], $rows];
    }

    /* ── Bills of exchange and realisation ─────────────────────────── */

    private function rBoeWiseInvoices(): array
    {
        $rows = $this->boes(true)->flatMap(fn ($e) => $e->invoice_rows->map(fn ($i) => ['bill_no' => $e->bill_no, 'bill_date' => $e->bill_date, 'bank_bill_type' => $e->bank_bill_type, 'lc_no' => $e->lc_no, 'invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date, 'exp_no' => $i->exp_no, 'net_amount' => (float) $i->net_amount]));

        return [['bill_no', 'bill_date:date', 'bank_bill_type', 'lc_no', 'invoice_no', 'invoice_date:date', 'exp_no', 'net_amount:money'], $rows];
    }

    private function rInvoiceBoeCollection(): array
    {
        $boes = $this->boes(true);
        $real = $this->book()->realised($boes->pluck('id')->all());
        $cm = DB::table('tx_cm_receipts')->whereIn('boe_id', $boes->pluck('id'))->groupBy('boe_id')->selectRaw('boe_id, sum(amount) as amt')->pluck('amt', 'boe_id');
        $rows = $boes->map(function ($e) use ($real, $cm) {
            $r = $real[$e->id] ?? null;
            $collected = $r ? $r->collected : 0.0;

            return ['bill_no' => $e->bill_no, 'bank_bill_type' => $e->bank_bill_type, 'lc_no' => $e->lc_no, 'invoice_list' => $e->invoice_rows->pluck('invoice_no')->implode(', '), 'invoice_value' => round((float) $e->invoice_rows->sum('net_amount'), 2), 'bill_amount' => (float) $e->bill_amount,
                'realized' => $r ? $r->invoice_amount : 0.0, 'collected' => $collected, 'cm_received' => (float) ($cm[$e->id] ?? 0), 'balance' => round((float) $e->bill_amount - $collected, 2)];
        });

        return [['bill_no', 'bank_bill_type', 'lc_no', 'invoice_list', 'invoice_value:money', 'bill_amount:money', 'realized:money', 'collected:money', 'cm_received:money', 'balance:money'], $rows];
    }

    private function rExpBoeProfile(): array
    {
        $b = $this->book();
        $boes = $this->boes(false);
        $real = $b->realised($boes->pluck('id')->all());
        $rows = $boes->map(fn ($e) => ['bill_no' => $e->bill_no, 'bill_date' => $e->bill_date, 'lc_no' => $e->lc_no, 'buyer' => $b->buyers()[$e->buyer_id] ?? '—', 'bank_bill_type' => $e->bank_bill_type, 'bank_bill_ref_no' => $e->bank_bill_ref_no, 'bank' => $this->bank($e->receiving_branch_id),
            'bill_amount' => (float) $e->bill_amount, 'due_date' => $e->due_date, 'submit_date' => $e->bank_submit_date ?? $e->ref_date, 'payment_date' => $e->payment_recv_date ?? ($real[$e->id]->realized_date ?? null), 'realized' => isset($real[$e->id]) ? $real[$e->id]->invoice_amount : 0.0,
            'final' => __('tradeop.yn.'.($e->final ? 'YES' : 'NO'))]);

        return [['bill_no', 'bill_date:date', 'lc_no', 'buyer', 'bank_bill_type', 'bank_bill_ref_no', 'bank', 'bill_amount:money', 'due_date:date', 'submit_date:date', 'payment_date:date', 'realized:money', 'final'], $rows];
    }

    private function rCostAgainstRealization(): array
    {
        $b = $this->book();
        $boes = DB::table('tx_bills_of_exchange as e')->leftJoin('tx_export_lcs as l', 'l.id', '=', 'e.lc_id')->where('e.company_id', $this->caller()->companyId)
            ->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('e.buyer_id', $v))->when($this->q['lc'] ?? null, fn ($w, $v) => $w->where('e.lc_id', $v))->when($this->q['bill_type'] ?? null, fn ($w, $v) => $w->where('e.bank_bill_type', $v))
            ->get(['e.id', 'e.bill_no', 'e.buyer_id', 'l.lc_no'])->keyBy('id');
        $rows = collect($b->realised($boes->keys()->all()))->filter(fn ($r) => $this->inWindow($r->realized_date))->map(function ($r) use ($boes, $b) {
            $e = $boes[$r->boe_id];
            $cost = round($r->invoice_amount - $r->collected, 2);

            return ['realized_no' => $r->realized_no, 'realized_date' => $r->realized_date, 'bill_no' => $e->bill_no, 'lc_no' => $e->lc_no, 'buyer' => $b->buyers()[$e->buyer_id] ?? '—', 'invoice_amount' => $r->invoice_amount, 'collected' => $r->collected,
                'fbc_amount' => $r->fbc_amount, 'penalty_amount' => $r->penalty_amount, 'cost' => $cost, 'cost_pct' => $r->invoice_amount > 0 ? round($cost / $r->invoice_amount * 100, 2) : null];
        })->sortBy('realized_date');

        return [['realized_no', 'realized_date:date', 'bill_no', 'lc_no', 'buyer', 'invoice_amount:money', 'collected:money', 'fbc_amount:money', 'penalty_amount:money', 'cost:money', 'cost_pct:pct'], $rows];
    }

    private function rLcWiseCashIncentive(): array
    {
        $b = $this->book();
        $lcIds = $this->lcs(false)->pluck('id');
        $claims = DB::table('tx_cash_incentives as ci')->join('tx_export_lcs as l', 'l.id', '=', 'ci.lc_id')->whereIn('ci.lc_id', $lcIds)->tap(fn ($w) => $this->dated($w, 'ci.application_date'))
            ->orderBy('l.lc_no')->orderBy('ci.application_date')->get(['ci.*', 'l.lc_no', 'l.buyer_id']);
        $real = $this->realisedByLc($claims->pluck('lc_id')->unique()->all());
        $rows = $claims->map(fn ($c) => ['lc_no' => $c->lc_no, 'buyer' => $b->buyers()[$c->buyer_id] ?? '—', 'realized' => $real[$c->lc_id]['amount'] ?? 0.0, 'cia_type' => __("tradeop.cia.{$c->cia_type}"), 'part' => (int) $c->claim_part_no, 'claim_sl_no' => $c->claim_sl_no,
            'application_date' => $c->application_date, 'claim_amount' => (float) $c->claim_amount, 'audit_amount' => (float) $c->audit_amount, 'collection_date' => $c->collection_date, 'final' => __('tradeop.yn.'.($c->final ? 'YES' : 'NO'))]);

        return [['lc_no', 'buyer', 'realized:money', 'cia_type', 'part:num', 'claim_sl_no', 'application_date:date', 'claim_amount:money', 'audit_amount:money', 'collection_date:date', 'final'], $rows];
    }

    private function rBankWiseMargin(): array
    {
        $rows = DB::table('tx_bills_of_exchange')->where('company_id', $this->caller()->companyId)->whereNotNull('margin_type')->tap(fn ($w) => $this->dated($w, 'bill_date'))->get(['receiving_branch_id', 'margin_type', 'bill_amount'])
            ->groupBy(fn ($e) => $this->bank($e->receiving_branch_id))->map(fn (Collection $g, $bank) => ['bank' => $bank]
                + collect(['MARGIN', 'COMMISSION', 'INTEREST', 'BANK_CHARGE'])->mapWithKeys(fn ($t) => [strtolower($t) => round((float) $g->where('margin_type', $t)->sum('bill_amount'), 2)])->all()
                + ['total' => round((float) $g->sum('bill_amount'), 2)])->sortKeys();

        return [['bank', 'margin:money', 'commission:money', 'interest:money', 'bank_charge:money', 'total:money'], $rows];
    }

    /* ── Import side: B2B L/Cs, entries, acceptances ───────────────── */

    private function rStatementOfImportLc(): array
    {
        $lcs = $this->importLcs()->get();
        $ids = $lcs->pluck('id');
        $pis = DB::table('tx_import_lc_pis as x')->join('tx_import_pis as p', 'p.id', '=', 'x.pi_id')->whereIn('x.lc_id', $ids)->groupBy('x.lc_id')->selectRaw('x.lc_id, sum(p.net_amount) as amt')->pluck('amt', 'lc_id');
        $entries = DB::table('tx_bills_of_entry')->whereIn('lc_id', $ids)->groupBy('lc_id')->selectRaw('lc_id, sum(bill_amount) as amt')->pluck('amt', 'lc_id');
        $acc = $this->acceptedByLc($ids->all());
        $rows = $lcs->map(fn ($l) => $this->importHead($l) + ['commodity' => $l->commodity_type, 'pi_value' => (float) ($pis[$l->id] ?? 0), 'received' => (float) ($entries[$l->id] ?? 0),
            'accepted' => $acc[$l->id]['accepted'] ?? 0.0, 'paid' => $acc[$l->id]['paid'] ?? 0.0, 'lc_status' => __("tradeop.lcstatus.{$l->lc_status}")]);

        return [['lc_no', 'ref_no', 'issue_date:date', 'supplier', 'export_lc', 'commodity', 'lc_value:money', 'pi_value:money', 'received:money', 'accepted:money', 'paid:money', 'lc_status'], $rows];
    }

    private function rBblcLiabilityStatus(): array
    {
        $lcs = $this->importLcs()->get();
        $acc = $this->acceptedByLc($lcs->pluck('id')->all());
        $rows = $lcs->map(function ($l) use ($acc) {
            $a = $acc[$l->id] ?? ['accepted' => 0.0, 'paid' => 0.0];

            return $this->importHead($l) + ['accepted' => $a['accepted'], 'paid' => $a['paid'], 'liability' => round($a['accepted'] - $a['paid'], 2), 'unaccepted' => round(max(0.0, (float) $l->lc_value - $a['accepted']), 2)];
        });

        return [['lc_no', 'issue_date:date', 'supplier', 'export_lc', 'lc_value:money', 'accepted:money', 'paid:money', 'liability:money', 'unaccepted:money'], $rows];
    }

    private function rBblcIfdbcLiabilities(): array
    {
        $lcs = $this->importLcs()->get();
        $acc = $this->acceptances(null, $lcs->pluck('id')->all())->groupBy('lc_id');
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $rows = $lcs->map(function ($l) use ($acc, $today) {
            $g = $acc->get($l->id, collect());
            $ifdbc = (float) $g->sum('ifdbc_amount');
            $paid = (float) $g->sum('paid_amount');
            $next = $g->filter(fn ($a) => (float) $a->ifdbc_amount - (float) $a->paid_amount > 0.005)->map(fn ($a) => $a->ext_maturity_date ?? $a->maturity_date)->filter()->sort()->first();

            return $this->importHead($l) + ['ifdbc' => $ifdbc, 'paid' => $paid, 'outstanding' => round($ifdbc - $paid, 2), 'next_maturity' => $next, 'overdue' => __('tradeop.yn.'.($next && $next < $today ? 'YES' : 'NO'))];
        });

        return [['lc_no', 'issue_date:date', 'supplier', 'export_lc', 'lc_value:money', 'ifdbc:money', 'paid:money', 'outstanding:money', 'next_maturity:date', 'overdue'], $rows];
    }

    private function rImportLcApplication(): array
    {
        $b = $this->book();
        $commodities = DB::table('tx_import_lc_commodities')->whereIn('lc_id', $this->importLcs()->pluck('l.id'))->orderBy('sort_order')->get(['lc_id', 'name'])->groupBy('lc_id');
        $rows = $this->importLcs()->get()->map(fn ($l) => ['ref_no' => $l->ref_no, 'lc_no' => $this->importNo($l), 'lc_type' => $b->reference('LC_TYPE', $l->lc_type) ?? $l->lc_type, 'supplier' => $this->supplier($l->supplier_id), 'issuing_bank' => $this->bank($l->sender_branch_id),
            'lien_bank' => $this->bank($l->lean_branch_id), 'lcaf_no' => $l->lcaf_no, 'irc_no' => $l->irc_no, 'bin_no' => $l->bin_no, 'btma_cert_no' => $l->btma_cert_no, 'commodity' => $commodities->get($l->id, collect())->pluck('name')->implode(', ') ?: $l->commodity_type,
            'inco_term' => $l->inco_term, 'port_of_loading' => $l->port_of_loading, 'port_of_discharge' => $l->port_of_discharge, 'last_shipment' => $l->last_shipment_date, 'expire_date' => $l->expire_date, 'tenor_days' => (int) $l->tenor_days, 'currency' => $l->currency, 'lc_value' => (float) $l->lc_value]);

        return [['ref_no', 'lc_no', 'lc_type', 'supplier', 'issuing_bank', 'lien_bank', 'lcaf_no', 'irc_no', 'bin_no', 'btma_cert_no', 'commodity', 'inco_term', 'port_of_loading', 'port_of_discharge', 'last_shipment:date', 'expire_date:date', 'tenor_days:num', 'currency', 'lc_value:money'], $rows];
    }

    private function rB2bOrderSummary(): array
    {
        $lcs = $this->importLcs()->get()->keyBy('id');
        $lines = DB::table('tx_import_lc_pis as x')->join('tx_import_pis as p', 'p.id', '=', 'x.pi_id')->join('tx_import_pi_lines as pl', 'pl.pi_id', '=', 'p.id')->whereIn('x.lc_id', $lcs->keys())
            ->orderBy('p.pi_no')->orderBy('pl.sort_order')->get(['x.lc_id', 'p.pi_no', 'pl.po_no', 'pl.item', 'pl.qty', 'pl.uom', 'pl.unit_price', 'pl.amount']);
        $rows = $lines->map(function ($r) use ($lcs) {
            $l = $lcs[$r->lc_id];

            return ['lc_no' => $this->importNo($l), 'supplier' => $this->supplier($l->supplier_id), 'export_lc' => $l->export_lc_no, 'pi_no' => $r->pi_no, 'po_no' => $r->po_no ?: '—', 'item' => $r->item, 'qty' => (float) $r->qty, 'uom' => $r->uom, 'rate' => (float) $r->unit_price, 'amount' => (float) $r->amount];
        });

        return [['lc_no', 'supplier', 'export_lc', 'pi_no', 'po_no', 'item', 'qty:qty', 'uom', 'rate:money', 'amount:money'], $rows];
    }

    private function rImpLcWiseBillOfEntry(): array
    {
        $lcs = $this->importLcs(false)->get()->keyBy('id');
        $rows = DB::table('tx_bills_of_entry')->whereIn('lc_id', $lcs->keys())->tap(fn ($w) => $this->dated($w, 'be_date'))->orderBy('be_date')->orderBy('be_no')->get()->map(function ($e) use ($lcs) {
            $l = $lcs[$e->lc_id];

            return ['lc_no' => $this->importNo($l), 'supplier' => $this->supplier($l->supplier_id), 'be_no' => $e->be_no, 'be_date' => $e->be_date, 'custom' => trim($e->custom_code.' '.$e->custom_name), 'currency' => $e->currency, 'bill_amount' => (float) $e->bill_amount,
                'rate' => (float) $e->exchange_rate, 'equivalent' => (float) $e->equivalent_amount, 'final' => __('tradeop.yn.'.($e->final ? 'YES' : 'NO'))];
        });

        return [['lc_no', 'supplier', 'be_no', 'be_date:date', 'custom', 'currency', 'bill_amount:money', 'rate:money', 'equivalent:money', 'final'], $rows];
    }

    private function rCustomsBond(): array
    {
        $lcs = $this->importLcs(false)->get()->keyBy('id');
        $uds = DB::table('tx_ud_lcs as x')->join('tx_uds as u', 'u.id', '=', 'x.ud_id')->whereIn('x.import_lc_id', $lcs->keys())->pluck('u.ud_no', 'x.import_lc_id');
        $rows = DB::table('tx_bill_of_entry_lines as bl')->join('tx_bills_of_entry as be', 'be.id', '=', 'bl.be_id')->join('tx_import_pi_lines as pl', 'pl.id', '=', 'bl.pi_line_id')->whereIn('be.lc_id', $lcs->keys())->tap(fn ($w) => $this->dated($w, 'be.be_date'))
            ->orderBy('be.be_date')->orderBy('be.be_no')->orderBy('bl.sort_order')->get(['be.be_no', 'be.be_date', 'be.custom_code', 'be.custom_name', 'be.lc_id', 'pl.item', 'pl.uom', 'bl.mrr_no', 'bl.challan_no', 'bl.receive_qty', 'bl.receive_amount'])
            ->map(fn ($r) => ['be_no' => $r->be_no, 'be_date' => $r->be_date, 'custom' => trim($r->custom_code.' '.$r->custom_name), 'lc_no' => $this->importNo($lcs[$r->lc_id]), 'ud_no' => $uds[$r->lc_id] ?? null, 'item' => $r->item, 'mrr_no' => $r->mrr_no, 'challan_no' => $r->challan_no,
                'qty' => (float) $r->receive_qty, 'uom' => $r->uom, 'amount' => (float) $r->receive_amount]);

        return [['be_no', 'be_date:date', 'custom', 'lc_no', 'ud_no', 'item', 'mrr_no', 'challan_no', 'qty:qty', 'uom', 'amount:money'], $rows];
    }

    private function rIfdbcPaymentSchedule(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $rows = $this->acceptances('maturity')->map(function ($a) use ($today) {
            $due = $a->ext_maturity_date ?? $a->maturity_date;

            return $this->acceptanceHead($a) + ['maturity_date' => $due, 'days' => $due ? (int) $today->diffInDays(CarbonImmutable::parse($due), false) : null, 'paid' => (float) $a->paid_amount, 'due' => round((float) $a->ifdbc_amount - (float) $a->paid_amount, 2)];
        })->sortBy('maturity_date');

        return [['boe_no', 'lc_no', 'supplier', 'ifdbc_no', 'acceptance_date:date', 'ifdbc:money', 'maturity_date:date', 'days:num', 'paid:money', 'due:money'], $rows];
    }

    private function rIfdbcPaymentList(): array
    {
        $rows = $this->acceptances('paid')->filter(fn ($a) => (float) $a->paid_amount > 0)->map(fn ($a) => $this->acceptanceHead($a) + ['paid_date' => $a->paid_date, 'paid' => (float) $a->paid_amount, 'payment_type' => __("tradeop.pay.{$a->payment_type}")])->sortBy('paid_date');

        return [['boe_no', 'lc_no', 'supplier', 'ifdbc_no', 'acceptance_date:date', 'ifdbc:money', 'paid_date:date', 'paid:money', 'payment_type'], $rows];
    }

    private function rIfdbcOutstanding(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $rows = $this->acceptances('maturity')->filter(fn ($a) => (float) $a->ifdbc_amount - (float) $a->paid_amount > 0.005)->map(function ($a) use ($today) {
            $due = $a->ext_maturity_date ?? $a->maturity_date;
            $late = $due ? (int) CarbonImmutable::parse($due)->diffInDays($today, false) : 0;

            return $this->acceptanceHead($a) + ['maturity_date' => $due, 'outstanding' => round((float) $a->ifdbc_amount - (float) $a->paid_amount, 2), 'days' => max(0, $late)];
        })->sortBy('maturity_date');

        return [['boe_no', 'lc_no', 'supplier', 'ifdbc_no', 'acceptance_date:date', 'ifdbc:money', 'maturity_date:date', 'outstanding:money', 'days:num'], $rows];
    }

    private function rBtbLiabilityAcceptance(): array
    {
        $rows = $this->acceptances('acceptance')->map(fn ($a) => $this->acceptanceHead($a) + ['export_lc' => $a->export_lc_no, 'invoiced' => (float) $a->invoiced_amount, 'maturity_date' => $a->ext_maturity_date ?? $a->maturity_date,
            'payment_type' => __("tradeop.pay.{$a->payment_type}"), 'final' => __('tradeop.yn.'.($a->final ? 'YES' : 'NO'))])->sortBy('acceptance_date');

        return [['boe_no', 'acceptance_date:date', 'lc_no', 'export_lc', 'supplier', 'invoiced:money', 'ifdbc_no', 'ifdbc:money', 'maturity_date:date', 'payment_type', 'final'], $rows];
    }

    private function rUdSummary(): array
    {
        $c = $this->caller()->companyId;
        $uds = DB::table('tx_uds')->where('company_id', $c)->tap(fn ($w) => $this->dated($w, 'ud_date'))->orderBy('ud_date')->orderBy('ud_no')->get();
        $links = DB::table('tx_ud_lcs as x')->join('tx_import_lcs as il', 'il.id', '=', 'x.import_lc_id')->leftJoin('tx_export_lcs as l', 'l.id', '=', 'il.export_lc_id')->whereIn('x.ud_id', $uds->pluck('id'))
            ->get(['x.ud_id', 'il.lc_value', 'il.export_lc_id', 'l.lc_no'])->groupBy('ud_id');
        $out = DB::table('tx_ud_transfers')->whereIn('from_ud_id', $uds->pluck('id'))->where('status', 'APPROVED')->groupBy('from_ud_id')->selectRaw('from_ud_id, sum(transfer_qty) as q')->pluck('q', 'from_ud_id');
        $in = DB::table('tx_ud_transfers')->whereIn('to_ud_id', $uds->pluck('id'))->where('status', 'APPROVED')->groupBy('to_ud_id')->selectRaw('to_ud_id, sum(transfer_qty) as q')->pluck('q', 'to_ud_id');
        $lc = $this->q['lc'] ?? null;
        $rows = $uds->filter(fn ($u) => ! $lc || $links->get($u->id, collect())->contains('export_lc_id', $lc))->map(function ($u) use ($links, $out, $in) {
            $g = $links->get($u->id, collect());

            return ['ud_no' => $u->ud_no, 'ud_date' => $u->ud_date, 'authority' => $u->authority, 'export_lc' => $g->pluck('lc_no')->filter()->unique()->implode(', ') ?: null, 'b2b_count' => $g->count(), 'b2b_value' => round((float) $g->sum('lc_value'), 2),
                'transfer_out' => (float) ($out[$u->id] ?? 0), 'transfer_in' => (float) ($in[$u->id] ?? 0), 'final' => __('tradeop.yn.'.($u->final ? 'YES' : 'NO'))];
        });

        return [['ud_no', 'ud_date:date', 'authority', 'export_lc', 'b2b_count:num', 'b2b_value:money', 'transfer_out:qty', 'transfer_in:qty', 'final'], $rows];
    }

    /* ── Statements over time ──────────────────────────────────────── */

    private function rWeeklyMarginBblc(): array
    {
        $c = $this->caller()->companyId;
        $week = fn ($d) => CarbonImmutable::parse($d)->startOfWeek(CarbonImmutable::SATURDAY)->toDateString();
        $b2b = $this->importLcs(false)->tap(fn ($w) => $this->dated($w, 'l.issue_date'))->get(['l.issue_date', 'l.lc_value']);
        $acc = $this->acceptances('acceptance');
        $margin = DB::table('tx_bills_of_exchange')->where('company_id', $c)->whereNotNull('margin_type')->tap(fn ($w) => $this->dated($w, 'bill_date'))->get(['bill_date', 'bill_amount']);
        $weeks = collect()->merge($b2b->pluck('issue_date'))->merge($acc->pluck('acceptance_date'))->merge($margin->pluck('bill_date'))->map($week)->unique()->sort()->values();
        $rows = $weeks->map(fn ($w) => ['week' => $w, 'b2b_count' => $b2b->filter(fn ($l) => $week($l->issue_date) === $w)->count(), 'b2b_value' => round((float) $b2b->filter(fn ($l) => $week($l->issue_date) === $w)->sum('lc_value'), 2),
            'accepted' => round((float) $acc->filter(fn ($a) => $week($a->acceptance_date) === $w)->sum('ifdbc_amount'), 2), 'margin' => round((float) $margin->filter(fn ($m) => $week($m->bill_date) === $w)->sum('bill_amount'), 2)]);

        return [['week:date', 'b2b_count:num', 'b2b_value:money', 'accepted:money', 'margin:money'], $rows];
    }

    private function rFundForecast(): array
    {
        $c = $this->caller()->companyId;
        $b = $this->book();
        $boes = DB::table('tx_bills_of_exchange')->where('company_id', $c)->where('final', true)->where('is_advance', false)->when($this->q['lc'] ?? null, fn ($w, $v) => $w->where('lc_id', $v))->get(['id', 'due_date', 'bill_amount']);
        $real = $b->realised($boes->pluck('id')->all());
        $inflow = $boes->reject(fn ($e) => isset($real[$e->id]))->filter(fn ($e) => $this->inWindow($e->due_date))->groupBy(fn ($e) => substr((string) $e->due_date, 0, 7))->map(fn ($g) => (float) $g->sum('bill_amount'));
        $outflow = $this->acceptances(null)->map(fn ($a) => ['due' => $a->ext_maturity_date ?? $a->maturity_date, 'left' => (float) $a->ifdbc_amount - (float) $a->paid_amount])
            ->filter(fn ($a) => $a['due'] && $a['left'] > 0.005 && $this->inWindow($a['due']))->groupBy(fn ($a) => substr((string) $a['due'], 0, 7))->map(fn ($g) => (float) $g->sum('left'));
        $rows = $inflow->keys()->merge($outflow->keys())->unique()->sort()->values()->map(fn ($m) => ['month' => CarbonImmutable::parse("{$m}-01")->format('M Y'), 'inflow' => round($inflow[$m] ?? 0, 2), 'outflow' => round($outflow[$m] ?? 0, 2), 'net' => round(($inflow[$m] ?? 0) - ($outflow[$m] ?? 0), 2)]);

        return [['month', 'inflow:money', 'outflow:money', 'net:money'], $rows];
    }

    /* ── Sources ───────────────────────────────────────────────────── */

    /** Export L/Cs by buyer, L/C and (optionally) issue date. */
    private function lcs(bool $dated = true): Builder
    {
        return DB::table('tx_export_lcs')->where('company_id', $this->caller()->companyId)
            ->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('buyer_id', $v))->when($this->q['lc'] ?? null, fn ($w, $v) => $w->where('id', $v))
            ->when($dated, fn ($w) => $this->dated($w, 'issue_date'))->orderBy('issue_date')->orderBy('lc_no');
    }

    /** B2B (import) L/Cs not in draft, by the export L/C they back, its buyer and (optionally) issue date. */
    private function importLcs(bool $dated = true): Builder
    {
        return DB::table('tx_import_lcs as l')->leftJoin('tx_export_lcs as el', 'el.id', '=', 'l.export_lc_id')->where('l.company_id', $this->caller()->companyId)->where('l.status', '!=', 'DRAFT')
            ->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('l.buyer_id', $v))->when($this->q['lc'] ?? null, fn ($w, $v) => $w->where('l.export_lc_id', $v))
            ->when($dated, fn ($w) => $this->dated($w, 'l.issue_date'))->orderBy('l.issue_date')->orderBy('l.ref_no')->select('l.*', 'el.lc_no as export_lc_no');
    }

    /**
     * Import acceptances (IFDBC), by their B2B L/C's filters and a date:
     * `maturity` (extended or not), `paid`, `acceptance`, or none.
     */
    private function acceptances(?string $date, ?array $lcIds = null): Collection
    {
        $lcs = $lcIds ?? $this->importLcs(false)->pluck('l.id')->all();

        return DB::table('tx_import_acceptances as a')->join('tx_import_lcs as l', 'l.id', '=', 'a.lc_id')->leftJoin('tx_export_lcs as el', 'el.id', '=', 'l.export_lc_id')->whereIn('a.lc_id', $lcs)
            ->when($date === 'acceptance', fn ($w) => $this->dated($w, 'a.acceptance_date'))->when($date === 'paid', fn ($w) => $this->dated($w, 'a.paid_date'))
            ->when($date === 'maturity', fn ($w) => $this->dated($w, DB::raw('coalesce(a.ext_maturity_date, a.maturity_date)')))
            ->get(['a.*', 'l.supplier_id', 'l.ref_no', 'l.lc_bank_code', 'l.lc_year', 'l.lc_code', 'l.lc_sl_no', 'el.lc_no as export_lc_no']);
    }

    /** Commercial invoices by every invoice box, with qty, cartons and their bill. */
    private function invoices(): Collection
    {
        $q = $this->q;
        $rows = DB::table('tx_commercial_invoices as i')->join('tx_export_lcs as l', 'l.id', '=', 'i.lc_id')->leftJoin('tx_boe_invoices as x', 'x.invoice_id', '=', 'i.id')->leftJoin('tx_bills_of_exchange as e', 'e.id', '=', 'x.boe_id')
            ->where('i.company_id', $this->caller()->companyId)
            ->when($q['buyer'] ?? null, fn ($w, $v) => $w->where('l.buyer_id', $v))->when($q['lc'] ?? null, fn ($w, $v) => $w->where('i.lc_id', $v))
            ->when($q['invoice'] ?? null, fn ($w, $v) => $w->whereRaw('lower(i.invoice_no) like ?', ['%'.mb_strtolower($v).'%']))
            ->tap(fn ($w) => $this->dated($w, 'i.invoice_date'))
            ->when($q['xf_from'] ?? null, fn ($w, $v) => $w->where('i.ex_factory_date', '>=', $v))->when($q['xf_to'] ?? null, fn ($w, $v) => $w->where('i.ex_factory_date', '<=', $v))
            ->when($q['negotiated'] ?? null, fn ($w, $v) => $w->where('i.is_negotiated', $v === 'YES'))
            ->when($q['bill_type'] ?? null, fn ($w, $v) => $w->where('e.bank_bill_type', $v))
            ->when($q['on_board'] ?? null, fn ($w, $v) => $v === 'WITH' ? $w->whereNotNull('i.on_board_date') : $w->whereNull('i.on_board_date'))
            ->orderBy('i.invoice_date')->orderBy('i.invoice_no')->get(['i.*', 'l.lc_no', 'l.buyer_id', 'e.id as boe_id', 'e.bill_no as boe_no']);
        $lines = DB::table('tx_commercial_invoice_lines')->whereIn('invoice_id', $rows->pluck('id'))->groupBy('invoice_id')->selectRaw('invoice_id, sum(inv_qty) as qty, sum(ctn_qty) as ctn')->get()->keyBy('invoice_id');

        return $rows->map(function ($i) use ($lines) {
            $i->qty = (float) ($lines[$i->id]->qty ?? 0);
            $i->ctn = (int) ($lines[$i->id]->ctn ?? 0);

            return $i;
        });
    }

    /** Bills of exchange (not advances when invoices are wanted), with their L/C and invoices. */
    private function boes(bool $withInvoices): Collection
    {
        $q = $this->q;
        $rows = DB::table('tx_bills_of_exchange as e')->leftJoin('tx_export_lcs as l', 'l.id', '=', 'e.lc_id')->where('e.company_id', $this->caller()->companyId)
            ->when($withInvoices, fn ($w) => $w->where('e.is_advance', false))
            ->when($q['buyer'] ?? null, fn ($w, $v) => $w->where('e.buyer_id', $v))->when($q['lc'] ?? null, fn ($w, $v) => $w->where('e.lc_id', $v))->when($q['bill_type'] ?? null, fn ($w, $v) => $w->where('e.bank_bill_type', $v))
            ->tap(fn ($w) => $this->dated($w, 'e.bill_date'))->orderBy('e.bill_date')->orderBy('e.bill_no')->get(['e.*', 'l.lc_no']);
        $inv = DB::table('tx_boe_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->whereIn('x.boe_id', $rows->pluck('id'))->orderBy('i.invoice_no')->get(['x.boe_id', 'i.invoice_no', 'i.invoice_date', 'i.exp_no', 'i.net_amount'])->groupBy('boe_id');
        $needle = isset($q['invoice']) ? mb_strtolower($q['invoice']) : null;

        return $rows->map(function ($e) use ($inv) {
            $e->invoice_rows = $inv->get($e->id, collect());

            return $e;
        })->filter(fn ($e) => ! $needle || $e->invoice_rows->contains(fn ($i) => str_contains(mb_strtolower($i->invoice_no), $needle)))->values();
    }

    /** @return array<string, float> Export L/C id => invoiced (net). */
    private function invoicedByLc(array $lcIds): array
    {
        return DB::table('tx_commercial_invoices')->whereIn('lc_id', $lcIds)->groupBy('lc_id')->selectRaw('lc_id, sum(net_amount) as amt')->pluck('amt', 'lc_id')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<string, array{amount: float, collected: float}> Export L/C id => realised. */
    private function realisedByLc(array $lcIds): array
    {
        $boes = DB::table('tx_bills_of_exchange')->whereIn('lc_id', $lcIds)->pluck('lc_id', 'id');
        $out = [];
        foreach ($this->book()->realised($boes->keys()->all()) as $id => $r) {
            $lc = $boes[$id];
            $out[$lc]['amount'] = ($out[$lc]['amount'] ?? 0.0) + $r->invoice_amount;
            $out[$lc]['collected'] = ($out[$lc]['collected'] ?? 0.0) + $r->collected;
        }

        return $out;
    }

    /** @return array<string, array{count: int, value: float}> Export L/C id => its B2B L/Cs not in draft. */
    private function b2bByLc(array $lcIds): array
    {
        return DB::table('tx_import_lcs')->whereIn('export_lc_id', $lcIds)->where('status', '!=', 'DRAFT')->groupBy('export_lc_id')->selectRaw('export_lc_id, count(*) as n, sum(lc_value) as v')->get()
            ->mapWithKeys(fn ($r) => [$r->export_lc_id => ['count' => (int) $r->n, 'value' => round((float) $r->v, 2)]])->all();
    }

    /** @return array<string, array{accepted: float, paid: float}> Import L/C id => accepted (IFDBC) and paid. */
    private function acceptedByLc(array $lcIds): array
    {
        return DB::table('tx_import_acceptances')->whereIn('lc_id', $lcIds)->groupBy('lc_id')->selectRaw('lc_id, sum(ifdbc_amount) as a, sum(coalesce(paid_amount, 0)) as p')->get()
            ->mapWithKeys(fn ($r) => [$r->lc_id => ['accepted' => round((float) $r->a, 2), 'paid' => round((float) $r->p, 2)]])->all();
    }

    /* ── Rows and helpers ──────────────────────────────────────────── */

    private function lcHead(object $l): array
    {
        return ['lc_no' => $l->lc_no, 'buyer' => $this->book()->buyers()[$l->buyer_id] ?? '—', 'issue_date' => $l->issue_date, 'lc_value' => (float) $l->lc_value];
    }

    private function importHead(object $l): array
    {
        return ['lc_no' => $this->importNo($l), 'ref_no' => $l->ref_no, 'issue_date' => $l->issue_date, 'supplier' => $this->supplier($l->supplier_id), 'export_lc' => $l->export_lc_no, 'lc_value' => (float) $l->lc_value];
    }

    private function acceptanceHead(object $a): array
    {
        return ['boe_no' => $a->boe_no, 'lc_no' => $this->importNo($a), 'supplier' => $this->supplier($a->supplier_id), 'ifdbc_no' => $a->ifdbc_no, 'acceptance_date' => $a->acceptance_date, 'ifdbc' => (float) $a->ifdbc_amount];
    }

    private function importNo(object $l): string
    {
        return implode('', array_filter([$l->lc_bank_code ?? null, $l->lc_year ?? null, $l->lc_code ?? null, $l->lc_sl_no ?? null], fn ($v) => $v !== null && $v !== '')) ?: (string) $l->ref_no;
    }

    private function supplier(?string $id): string
    {
        $this->suppliers ??= DB::table('mp_suppliers')->where('company_id', $this->caller()->companyId)->pluck('name', 'id');

        return $id ? ($this->suppliers[$id] ?? '—') : '—';
    }

    private function bank(?string $branchId): string
    {
        return $branchId ? explode(' — ', $this->book()->branches()[$branchId] ?? '—')[0] : '—';
    }

    private function dated(Builder $w, mixed $column): void
    {
        $w->when($this->q['from'] ?? null, fn ($x, $v) => $x->where($column, '>=', $v))->when($this->q['to'] ?? null, fn ($x, $v) => $x->where($column, '<=', $v));
    }

    private function inWindow(?string $date): bool
    {
        return $date !== null && (empty($this->q['from']) || $date >= $this->q['from']) && (empty($this->q['to']) || $date <= $this->q['to']);
    }

    private function book(): ExportBook
    {
        return $this->book ??= new ExportBook($this->caller()->companyId);
    }
}
