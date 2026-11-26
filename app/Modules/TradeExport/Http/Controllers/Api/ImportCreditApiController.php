<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\TradeExport\Models\TxExportLc;
use App\Modules\TradeExport\Models\TxImportLc;
use App\Modules\TradeExport\Models\TxImportPi;
use App\Modules\TradeExport\Support\ExportBook;
use App\Modules\TradeExport\Support\ImportBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → Import (AnnotechERP 22/03): the supplier's proforma invoice
 * built from its purchase orders (a line never past what its PO has left), and
 * the back-to-back L/C opened on an export L/C — within the export L/C's
 * % BB Limit, past Create Draft carrying approved PIs of its supplier.
 */
class ImportCreditApiController extends ApiController
{
    private ?ExportBook $export = null;

    private ?ImportBook $import = null;

    public function options(): JsonResponse
    {
        $this->allowEither('tradeexport.import.view', 'tradeexport.export.view');
        $c = $this->caller()->companyId;
        $b = $this->export();

        return ApiResponse::ok([
            'today' => now('Asia/Dhaka')->toDateString(),
            'company' => ['value' => $c, 'label' => $b->company()],
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.' ('.$s->code.')'])->all(),
            'buyers' => collect($b->buyers())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'branches' => collect($b->branches())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'banks' => DB::table('tx_banks')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all(),
            'refs' => $b->referenceOptions(),
            'countries' => DB::table('origin_countries')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all(),
            'currencies' => DB::table('currencies')->where('company_id', $c)->where('active', true)->orderBy('code')->pluck('code')->map(fn ($v) => ['value' => $v, 'label' => $v])->all(),
            'rates' => $b->rates(),
            'uoms' => UomCodes::options($c),
            'commodities' => ImportBook::COMMODITIES,
            'customs' => ImportBook::CUSTOMS,
            'materials' => array_keys(ImportBook::MATERIALS),
            'authorities' => ImportBook::AUTHORITIES,
            'export_lcs' => DB::table('tx_export_lcs')->where('company_id', $c)->where('status', '!=', 'DRAFT')->orderByDesc('issue_date')->get(['id', 'lc_no', 'buyer_id'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->lc_no, 'buyer_id' => $r->buyer_id])->all(),
            'import_pis' => DB::table('tx_import_pis as p')->leftJoin('tx_import_lc_pis as x', 'x.pi_id', '=', 'p.id')->where('p.company_id', $c)->where('p.status', 'APPROVED')->orderByDesc('p.pi_date')->get(['p.id', 'p.pi_no', 'p.tracking_no', 'p.supplier_id', 'p.export_lc_id', 'x.lc_id'])
                ->map(fn ($r) => ['value' => $r->id, 'label' => $r->pi_no, 'tracking_no' => $r->tracking_no, 'supplier_id' => $r->supplier_id, 'export_lc_id' => $r->export_lc_id, 'lc_id' => $r->lc_id])->all(),
            'import_lcs' => DB::table('tx_import_lcs')->where('company_id', $c)->where('status', '!=', 'DRAFT')->orderByDesc('issue_date')->get(['id', 'ref_no', 'lc_bank_code', 'lc_year', 'lc_code', 'lc_sl_no'])->map(fn ($r) => ['value' => $r->id, 'label' => $this->lcNo($r)])->all(),
            'users' => DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')->where('cu.company_id', $c)->orderBy('u.name')->get(['u.id', 'u.name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            'me' => $this->caller()->auditUserId(),
        ]);
    }

    /* ── Import L/C Proforma Invoice ───────────────────────────────── */

    public function pis(): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        $names = DB::table('mp_suppliers')->where('company_id', $this->caller()->companyId)->pluck('name', 'id');
        $rows = TxImportPi::query()->orderByDesc('pi_date')->orderByDesc('tracking_no')->get();
        $lines = DB::table('tx_import_pi_lines')->whereIn('pi_id', $rows->pluck('id'))->get(['pi_id', 'po_no'])->groupBy('pi_id');
        $lcs = DB::table('tx_import_lc_pis as x')->join('tx_import_lcs as l', 'l.id', '=', 'x.lc_id')->whereIn('x.pi_id', $rows->pluck('id'))->get(['x.pi_id', 'l.ref_no', 'l.lc_bank_code', 'l.lc_year', 'l.lc_code', 'l.lc_sl_no'])->keyBy('pi_id');

        return ApiResponse::ok($rows->map(fn (TxImportPi $p) => ['id' => $p->id, 'pi_no' => $p->pi_no, 'tracking_no' => $p->tracking_no, 'status' => $p->status, 'po_no' => $lines->get($p->id, collect())->pluck('po_no')->filter()->unique()->implode(', '), 'created_on' => $p->created_at?->timezone('Asia/Dhaka')->toDateString(), 'approved_on' => $p->approved_at?->timezone('Asia/Dhaka')->toDateString(),
            'supplier_id' => $p->supplier_id, 'supplier' => $names[$p->supplier_id] ?? '—', 'pi_date' => $p->pi_date->toDateString(), 'pi_amount' => $p->pi_amount, 'export_lc_id' => $p->export_lc_id, 'lc_info' => isset($lcs[$p->id]) ? $this->lcNo($lcs[$p->id]) : null, 'approved' => $p->status === 'APPROVED', 'locked' => $p->status === 'APPROVED'])->values()->all());
    }

    public function supplierPos(Request $request): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        $supplier = $request->validate(['supplier' => ['required', 'string']])['supplier'];

        return ApiResponse::ok($this->import()->pos($supplier));
    }

    public function poLines(Request $request, string $kind, string $po): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        abort_unless(in_array($kind, ['ACC', 'FAB', 'YARN'], true), 404);

        return ApiResponse::ok($this->import()->poLines($kind, $po, $request->query('except')));
    }

    public function pi(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.view');

        return ApiResponse::ok($this->piBody(TxImportPi::query()->findOrFail($id)));
    }

    public function savePi(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $c = $this->caller()->companyId;
        $p = $id ? TxImportPi::query()->findOrFail($id) : null;
        if ($p?->status === 'APPROVED') {
            throw ValidationException::withMessages(['pi_no' => __('tradeop.v.locked', ['no' => $p->pi_no])]);
        }
        $d = $request->validate([
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'pi_no' => ['required', 'string', 'max:60', Rule::unique('tx_import_pis', 'pi_no')->where('company_id', $c)->where('supplier_id', $request->input('supplier_id'))->ignore($p?->id)],
            'export_lc_id' => ['nullable', Rule::exists('tx_export_lcs', 'id')->where('company_id', $c)], 'pi_date' => ['required', 'date'], 'pi_amount' => ['required', 'numeric', 'gt:0'], 'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.po_kind' => ['nullable', Rule::in(['ACC', 'FAB', 'YARN'])], 'lines.*.po_line_id' => ['nullable', 'string', 'max:26'], 'lines.*.po_no' => ['nullable', 'string', 'max:60'], 'lines.*.item' => ['required', 'string', 'max:300'], 'lines.*.qty' => ['required', 'numeric', 'gt:0'], 'lines.*.uom' => ['required', 'string', 'max:16'], 'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ], ['lines.required' => __('tradeop.v.ipi_lines')], ExportBook::attrs());
        $lines = array_values($d['lines']);
        foreach (collect($lines)->filter(fn ($l) => ! empty($l['po_line_id']))->groupBy('po_kind') as $kind => $group) {
            $rows = $this->import()->lines((string) $kind, $group->pluck('po_line_id')->all());
            $taken = $this->import()->taken((string) $kind, array_keys($rows), $p?->id);
            foreach ($group->groupBy('po_line_id') as $lineId => $same) {
                if (! isset($rows[$lineId])) {
                    throw ValidationException::withMessages(['lines' => __('tradeop.v.ipi_po_line')]);
                }
                $left = round($rows[$lineId]['po_qty'] - (float) ($taken[$lineId] ?? 0), 4);
                if ($same->sum('qty') > $left + 0.0005) {
                    throw ValidationException::withMessages(['lines' => __('tradeop.v.ipi_qty', ['po' => $same->first()['po_no'], 'item' => $rows[$lineId]['item'], 'left' => $this->export()->num($left)])]);
                }
            }
        }
        $net = round(array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_price'], $lines)), 4);
        $saved = DB::transaction(function () use ($d, $p, $c, $lines, $net) {
            $body = collect($d)->except(['lines'])->all() + ['company_id' => $c, 'net_amount' => $net];
            if ($p) {
                $p->update($body);
            } else {
                $p = TxImportPi::query()->create($body + ['tracking_no' => $this->import()->nextNo('IPI', 'tx_import_pis', 'tracking_no', $d['pi_date']), 'created_by' => $this->caller()->auditUserId()]);
            }
            DB::table('tx_import_pi_lines')->where('pi_id', $p->id)->delete();
            foreach ($lines as $i => $l) {
                DB::table('tx_import_pi_lines')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'pi_id' => $p->id, 'po_kind' => $l['po_kind'] ?? null, 'po_line_id' => $l['po_line_id'] ?? null, 'po_no' => $l['po_no'] ?? null, 'item' => $l['item'], 'qty' => $l['qty'], 'uom' => $l['uom'], 'unit_price' => $l['unit_price'], 'amount' => round($l['qty'] * $l['unit_price'], 4), 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $p;
        });

        return $id ? ApiResponse::ok($this->piBody($saved->fresh())) : ApiResponse::created($this->piBody($saved->fresh()));
    }

    public function piApprove(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $p = TxImportPi::query()->findOrFail($id);
        if (abs($p->net_amount - $p->pi_amount) > 0.005) {
            throw ValidationException::withMessages(['pi_amount' => __('tradeop.v.pi_total', ['total' => $this->export()->num($p->net_amount), 'amount' => $this->export()->num($p->pi_amount)])]);
        }
        $p->update(['status' => 'APPROVED', 'approved_at' => now()]);

        return ApiResponse::ok(['id' => $p->id, 'status' => 'APPROVED']);
    }

    public function piDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $p = TxImportPi::query()->findOrFail($id);
        if ($p->status === 'APPROVED') {
            throw ValidationException::withMessages(['pi_no' => __('tradeop.v.locked', ['no' => $p->pi_no])]);
        }
        $p->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Import L/C Opening ────────────────────────────────────────── */

    public function lcs(): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        $b = $this->export();
        $rows = TxImportLc::query()->orderByDesc('issue_date')->orderByDesc('ref_no')->get();
        $names = DB::table('mp_suppliers')->where('company_id', $this->caller()->companyId)->pluck('name', 'id');
        $exports = DB::table('tx_export_lcs')->whereIn('id', $rows->pluck('export_lc_id')->filter())->pluck('lc_no', 'id');
        $pis = DB::table('tx_import_lc_pis as x')->join('tx_import_pis as p', 'p.id', '=', 'x.pi_id')->whereIn('x.lc_id', $rows->pluck('id'))->get(['x.lc_id', 'p.pi_no'])->groupBy('lc_id');
        $ifdbc = DB::table('tx_import_acceptances')->whereIn('lc_id', $rows->pluck('id'))->groupBy('lc_id')->selectRaw('lc_id, sum(ifdbc_amount) as a')->pluck('a', 'lc_id');

        return ApiResponse::ok($rows->map(fn (TxImportLc $l) => ['id' => $l->id, 'ref_no' => $l->ref_no, 'final' => $l->status === 'COMPLETE', 'status' => $l->status, 'lc_no' => $this->lcNo($l), 'export_lc' => $exports[$l->export_lc_id] ?? null, 'lc_value' => $l->lc_value, 'bank_ref_no' => $l->bank_ref_no, 'ifdbc_amount' => (float) ($ifdbc[$l->id] ?? 0), 'issue_date' => $l->issue_date->toDateString(), 'expire_date' => $l->expire_date->toDateString(),
            'pi_no' => $pis->get($l->id, collect())->pluck('pi_no')->implode(', '), 'applicant' => $b->company(), 'commodity' => $l->commodity_type, 'supplier_id' => $l->supplier_id, 'supplier' => $names[$l->supplier_id] ?? '—', 'lc_type' => $l->lc_type, 'bank_id' => $b->branchBank($l->sender_branch_id), 'locked' => $l->status === 'COMPLETE'])->values()->all());
    }

    public function lc(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.view');

        return ApiResponse::ok($this->lcBody(TxImportLc::query()->findOrFail($id)));
    }

    public function saveLc(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $c = $this->caller()->companyId;
        $b = $this->export();
        $l = $id ? TxImportLc::query()->findOrFail($id) : null;
        if ($l?->status === 'COMPLETE') {
            throw ValidationException::withMessages(['ref_no' => __('tradeop.v.locked', ['no' => $l->ref_no])]);
        }
        $branch = fn (bool $req) => [$req ? 'required' : 'nullable', Rule::exists('tx_bank_branches', 'id')->where('company_id', $c)];
        $country = fn () => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $c)];
        $cash = $request->boolean('is_cash');
        $d = $request->validate([
            'ref_no' => ['required', 'string', 'max:60', Rule::unique('tx_import_lcs', 'ref_no')->where('company_id', $c)->ignore($l?->id)], 'lc_type' => ['required', $b->refRule('LC_TYPE')], 'is_cash' => ['boolean'], 'commodity_type' => ['required', Rule::in(ImportBook::COMMODITIES)], 'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)], 'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'export_lc_id' => [$cash ? 'nullable' : 'required', Rule::exists('tx_export_lcs', 'id')->where('company_id', $c)], 'sender_branch_id' => $branch(true), 'receiving_branch_id' => $branch(false), 'lean_branch_id' => $branch(false), 'same_receiver_bank' => ['boolean'], 'issue_date' => ['required', 'date'], 'expire_date' => ['required', 'date', 'after_or_equal:issue_date'], 'ref_date' => ['nullable', 'date'], 'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)],
            'inco_term' => ['required', $b->refRule('INCO_TERM')], 'delivery_place' => ['nullable', $b->refRule('DELIVERY_PLACE')], 'payment_term_description' => ['nullable', 'string'], 'lcaf_no' => ['nullable', 'string', 'max:60'], 'insurance' => ['nullable', 'string', 'max:160'], 'credit_tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'], 'partial_shipment' => ['boolean'], 'trans_shipment' => ['boolean'], 'btma_cert_no' => ['nullable', 'string', 'max:60'], 'btma_cert_date' => ['nullable', 'date'],
            'port_of_loading' => ['required', $b->refRule('DELIVERY_PLACE')], 'port_of_discharge' => ['required', $b->refRule('DELIVERY_PLACE')], 'last_shipment_date' => ['required', 'date', 'before_or_equal:expire_date'], 'time_of_shipment' => ['nullable', 'string', 'max:80'], 'country_of_origin_id' => $country(), 'bank_ref_no' => ['nullable', 'string', 'max:60'], 'lc_bank_code' => ['nullable', 'string', 'max:12'], 'lc_year' => ['nullable', 'string', 'max:6'], 'lc_code' => ['nullable', 'string', 'max:12'], 'lc_sl_no' => ['nullable', 'string', 'max:12'],
            'place_of_expiry_id' => $country(), 'place_of_issue_id' => $country(), 'lc_value' => ['required', 'numeric', 'gt:0'], 'payment_term' => ['required', $b->refRule('PAYMENT_TERM')], 'tenor_days' => ['nullable', 'integer', 'min:0'], 'irc_no' => ['nullable', 'string', 'max:60'], 'shipment_mode' => ['nullable', $b->refRule('SHIPMENT_MODE')], 'freight_cost' => ['nullable', 'numeric', 'min:0'], 'lc_status' => ['required', Rule::in(ImportBook::LC_STATUSES)], 'bin_no' => ['nullable', 'string', 'max:60'], 'status' => ['required', Rule::in(['DRAFT', 'SUBMITTED', 'COMPLETE'])],
            'commodities' => ['array'], 'commodities.*.name' => ['required', 'string', 'max:160'], 'commodities.*.qty' => ['required', 'numeric', 'gt:0'], 'commodities.*.unit' => ['required', 'string', UomCodes::rule($c)], 'pis' => ['array'], 'pis.*' => ['required', Rule::exists('tx_import_pis', 'id')->where('company_id', $c)],
        ], ['expire_date.after_or_equal' => __('tradeop.v.lc_expiry'), 'last_shipment_date.before_or_equal' => __('tradeop.v.lc_ship')], ExportBook::attrs());
        $pis = array_values(array_unique($d['pis'] ?? []));
        if ($d['status'] !== 'DRAFT' && ! $pis) {
            throw ValidationException::withMessages(['pis' => __('tradeop.v.ilc_pi')]);
        }
        foreach (TxImportPi::query()->whereIn('id', $pis)->get(['pi_no', 'supplier_id', 'status']) as $pi) {
            if ($pi->supplier_id !== $d['supplier_id']) {
                throw ValidationException::withMessages(['pis' => __('tradeop.v.ilc_pi_supplier', ['no' => $pi->pi_no])]);
            }
            if ($pi->status !== 'APPROVED') {
                throw ValidationException::withMessages(['pis' => __('tradeop.v.ilc_pi_status', ['no' => $pi->pi_no])]);
            }
        }
        $taken = DB::table('tx_import_lc_pis as x')->join('tx_import_lcs as l', 'l.id', '=', 'x.lc_id')->join('tx_import_pis as p', 'p.id', '=', 'x.pi_id')->whereIn('x.pi_id', $pis)->when($l, fn ($q) => $q->where('x.lc_id', '!=', $l->id))->first(['p.pi_no', 'l.ref_no']);
        if ($taken) {
            throw ValidationException::withMessages(['pis' => __('tradeop.v.lc_pi_taken', ['no' => $taken->pi_no, 'lc' => $taken->ref_no])]);
        }
        if (! empty($d['export_lc_id'])) {
            $exp = TxExportLc::query()->findOrFail($d['export_lc_id']);
            if ($exp->bb_limit_pct !== null && $exp->bb_limit_pct > 0) {
                $limit = round($exp->lc_value * $exp->bb_limit_pct / 100, 4);
                $used = (float) TxImportLc::query()->where('export_lc_id', $exp->id)->when($l, fn ($q) => $q->where('id', '!=', $l->id))->sum('lc_value');
                if ($used + (float) $d['lc_value'] > $limit + 0.005) {
                    throw ValidationException::withMessages(['lc_value' => __('tradeop.v.ilc_bb', ['lc' => $exp->lc_no, 'pct' => $exp->bb_limit_pct, 'left' => $b->num(max(0.0, $limit - $used))])]);
                }
            }
        }
        $saved = DB::transaction(function () use ($d, $l, $c, $pis, $cash) {
            $body = collect($d)->except(['pis', 'commodities'])->all() + ['company_id' => $c];
            foreach (['same_receiver_bank', 'partial_shipment', 'trans_shipment'] as $flag) {
                $body[$flag] = (bool) ($d[$flag] ?? false);
            }
            $body['is_cash'] = $cash;
            $body['credit_tolerance_pct'] = (float) ($d['credit_tolerance_pct'] ?? 5);
            $body['tenor_days'] = (int) ($d['tenor_days'] ?? 0);
            if ($l) {
                $l->update($body);
            } else {
                $l = TxImportLc::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            }
            DB::table('tx_import_lc_commodities')->where('lc_id', $l->id)->delete();
            foreach (array_values($d['commodities'] ?? []) as $i => $x) {
                DB::table('tx_import_lc_commodities')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'lc_id' => $l->id, 'name' => $x['name'], 'qty' => $x['qty'], 'unit' => UomCodes::normalize($x['unit']), 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('tx_import_lc_pis')->where('lc_id', $l->id)->delete();
            foreach ($pis as $pi) {
                DB::table('tx_import_lc_pis')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'lc_id' => $l->id, 'pi_id' => $pi, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $l;
        });

        return $id ? ApiResponse::ok($this->lcBody($saved->fresh())) : ApiResponse::created($this->lcBody($saved->fresh()));
    }

    public function lcDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $l = TxImportLc::query()->findOrFail($id);
        if ($l->status !== 'DRAFT') {
            throw ValidationException::withMessages(['ref_no' => __('tradeop.v.delete_draft', ['no' => $l->ref_no])]);
        }
        $l->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** The PI as the PI & Product Details grid shows it. */
    public function piForLc(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.view');

        return ApiResponse::ok($this->piRows([$id])[0] ?? abort(404));
    }

    /** The B2B Summary of an export L/C: its back-to-back L/Cs and its % BB Limit. */
    public function b2bSummary(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $e = TxExportLc::query()->findOrFail($id);
        $names = DB::table('mp_suppliers')->where('company_id', $this->caller()->companyId)->pluck('name', 'id');
        $rows = TxImportLc::query()->where('export_lc_id', $e->id)->orderBy('issue_date')->get();
        $used = (float) $rows->sum('lc_value');
        $limit = $e->bb_limit_pct ? round($e->lc_value * $e->bb_limit_pct / 100, 4) : null;

        return ApiResponse::ok(['lc_no' => $e->lc_no, 'lc_value' => $e->lc_value, 'bb_limit_pct' => $e->bb_limit_pct, 'limit' => $limit, 'used' => $used, 'left' => $limit === null ? null : round($limit - $used, 4),
            'rows' => $rows->map(fn (TxImportLc $l) => ['lc_no' => $this->lcNo($l), 'supplier' => $names[$l->supplier_id] ?? '—', 'commodity' => $l->commodity_type, 'issue_date' => $l->issue_date->toDateString(), 'lc_value' => $l->lc_value, 'status' => $l->status])->values()->all()]);
    }

    /* ── Bodies ────────────────────────────────────────────────────── */

    private function lcNo(object $l): string
    {
        return implode('', array_filter([$l->lc_bank_code ?? null, $l->lc_year ?? null, $l->lc_code ?? null, $l->lc_sl_no ?? null], fn ($v) => $v !== null && $v !== '')) ?: (string) $l->ref_no;
    }

    /** @return array<string, mixed> */
    private function piBody(TxImportPi $p): array
    {
        return array_merge($p->only($p->getFillable()), ['id' => $p->id, 'pi_date' => $p->pi_date->toDateString(), 'locked' => $p->status === 'APPROVED', 'lines' => DB::table('tx_import_pi_lines')->where('pi_id', $p->id)->orderBy('sort_order')->get()->map(fn ($l) => ['po_kind' => $l->po_kind, 'po_line_id' => $l->po_line_id, 'po_no' => $l->po_no, 'item' => $l->item, 'qty' => (float) $l->qty, 'uom' => $l->uom, 'unit_price' => (float) $l->unit_price])->all()]);
    }

    /** @return list<array<string, mixed>> */
    private function piRows(array $ids): array
    {
        $lines = DB::table('tx_import_pi_lines')->whereIn('pi_id', $ids)->orderBy('sort_order')->get(['pi_id', 'item', 'qty', 'amount'])->groupBy('pi_id');

        return TxImportPi::query()->whereIn('id', $ids)->orderBy('pi_no')->get()->map(fn (TxImportPi $p) => ['id' => $p->id, 'pi_no' => $p->pi_no, 'tracking_no' => $p->tracking_no, 'pi_date' => $p->pi_date->toDateString(), 'item' => $lines->get($p->id, collect())->pluck('item')->unique()->implode(', '), 'qty' => (float) $lines->get($p->id, collect())->sum('qty'), 'value' => (float) $lines->get($p->id, collect())->sum('amount')])->values()->all();
    }

    /** @return array<string, mixed> */
    private function lcBody(TxImportLc $l): array
    {
        $dates = ['issue_date', 'expire_date', 'ref_date', 'btma_cert_date', 'last_shipment_date'];
        $pis = DB::table('tx_import_lc_pis')->where('lc_id', $l->id)->pluck('pi_id')->all();

        return array_merge($l->only($l->getFillable()), collect($dates)->mapWithKeys(fn ($k) => [$k => $l->{$k}?->toDateString()])->all(), ['id' => $l->id, 'lc_no' => $this->lcNo($l), 'locked' => $l->status === 'COMPLETE', 'pis' => $pis, 'pi_rows' => $this->piRows($pis),
            'commodities' => DB::table('tx_import_lc_commodities')->where('lc_id', $l->id)->orderBy('sort_order')->get(['name', 'qty', 'unit'])->map(fn ($x) => ['name' => $x->name, 'qty' => (float) $x->qty, 'unit' => $x->unit])->all()]);
    }

    private function export(): ExportBook
    {
        return $this->export ??= new ExportBook($this->caller()->companyId);
    }

    private function import(): ImportBook
    {
        return $this->import ??= new ImportBook($this->caller()->companyId);
    }
}
