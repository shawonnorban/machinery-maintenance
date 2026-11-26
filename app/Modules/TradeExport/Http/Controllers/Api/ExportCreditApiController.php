<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\TradeExport\Models\TxExportLc;
use App\Modules\TradeExport\Models\TxExportPi;
use App\Modules\TradeExport\Support\ExportBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → Operations (AnnotechERP 22/02): what the export papers pick
 * from, the advance proforma invoice (Create Draft → Submit → Complete LC, or
 * Cancelled) and the export L/C or sales contract opened against PIs and
 * orders. Past Create Draft an L/C must carry its orders' value, or have the
 * difference ignored; a PI's lines must add up to its amount.
 */
class ExportCreditApiController extends ApiController
{
    private const MONEY = 'decimal:0,4';

    private ?ExportBook $book = null;

    public function options(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $c = $this->caller()->companyId;
        $b = $this->book();

        return ApiResponse::ok([
            'today' => now('Asia/Dhaka')->toDateString(),
            'company' => ['value' => $c, 'label' => $b->company()],
            'buyers' => collect($b->buyers())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'currencies' => DB::table('currencies')->where('company_id', $c)->where('active', true)->orderBy('code')->pluck('code')->map(fn ($v) => ['value' => $v, 'label' => $v])->all(),
            'rates' => $b->rates(),
            'countries' => DB::table('origin_countries')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all(),
            'branches' => collect($b->branches())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'banks' => DB::table('tx_banks')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all(),
            'refs' => $b->referenceOptions(),
            'applicants' => DB::table('tx_lc_applicants')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'buyer_id'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->name, 'buyer_id' => $r->buyer_id])->all(),
            'orders' => $b->orderOptions(),
            'item_groups' => ExportBook::ITEM_GROUPS,
            'item_types' => $b->itemTypes(),
            'commodities' => ExportBook::COMMODITIES,
            'transport_modes' => ExportBook::TRANSPORT_MODES,
            'container_sizes' => ExportBook::CONTAINER_SIZES,
            'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name')->unique()->values()->all(),
            'sizes' => DB::table('sizes')->where('company_id', $c)->where('active', true)->orderBy('display_order')->pluck('name')->unique()->values()->all(),
            'uoms' => UomCodes::options($c),
            'accounts' => DB::table('acc_accounts')->where('company_id', $c)->where('active', true)->where('postable', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->code.' — '.$r->name])->all(),
            'open_pis' => DB::table('tx_export_pis')->where('company_id', $c)->whereIn('status', ['SUBMITTED', 'COMPLETE'])->orderByDesc('pi_date')->get(['id', 'pi_no', 'buyer_id', 'net_amount', 'currency'])
                ->map(fn ($r) => ['value' => $r->id, 'label' => $r->pi_no, 'buyer_id' => $r->buyer_id, 'net_amount' => (float) $r->net_amount, 'currency' => $r->currency])->all(),
            'lcs' => DB::table('tx_export_lcs')->where('company_id', $c)->where('status', '!=', 'DRAFT')->orderByDesc('issue_date')->get(['id', 'lc_no', 'buyer_id'])->map(fn ($r) => ['value' => $r->id, 'label' => $r->lc_no, 'buyer_id' => $r->buyer_id])->all(),
        ]);
    }

    /* ── Export L/C Proforma Invoice ───────────────────────────────── */

    public function pis(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();

        return ApiResponse::ok(TxExportPi::query()->orderByDesc('pi_date')->orderByDesc('pi_no')->get()->map(fn (TxExportPi $p) => [
            'id' => $p->id, 'pi_no' => $p->pi_no, 'final' => $p->status === 'COMPLETE', 'status' => $p->status, 'company' => $b->company(), 'buyer_id' => $p->buyer_id, 'buyer' => $b->buyers()[$p->buyer_id] ?? '—',
            'country' => $b->buyerCountry($p->buyer_id), 'total_amount' => $p->total_amount, 'advance_amount' => $p->advance_amount, 'discount_amount' => $p->discount_amount, 'net_amount' => $p->net_amount,
            'pi_date' => $p->pi_date->toDateString(), 'locked' => in_array($p->status, ['COMPLETE', 'CANCELLED'], true),
        ])->values()->all());
    }

    public function pi(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $p = TxExportPi::query()->findOrFail($id);

        return ApiResponse::ok($this->piBody($p));
    }

    public function savePi(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $b = $this->book();
        $p = $id ? TxExportPi::query()->findOrFail($id) : null;
        if ($p && in_array($p->status, ['COMPLETE', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['pi_no' => __('tradeop.v.locked', ['no' => $p->pi_no])]);
        }
        $d = $request->validate([
            'pi_no' => ['required', 'string', 'max:60', Rule::unique('tx_export_pis', 'pi_no')->where('company_id', $c)->ignore($p?->id)],
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $c)], 'buyer_attention' => ['required', 'string', 'max:160'], 'pi_date' => ['required', 'date'], 'pi_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)], 'commodity_type' => ['nullable', Rule::in(ExportBook::COMMODITIES)], 'inco_term' => ['nullable', $b->refRule('INCO_TERM')], 'delivery_place' => ['nullable', $b->refRule('DELIVERY_PLACE')],
            'payment_term' => ['nullable', $b->refRule('PAYMENT_TERM')], 'expected_delivery_date' => ['nullable', 'date'], 'bank_branch_id' => ['nullable', Rule::exists('tx_bank_branches', 'id')->where('company_id', $c)],
            'origin_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $c)], 'packing' => ['nullable', 'string', 'max:160'], 'shipment_mode' => ['nullable', $b->refRule('SHIPMENT_MODE')],
            'transport_mode' => ['nullable', Rule::in(ExportBook::TRANSPORT_MODES)], 'partial_shipment' => ['boolean'], 'transhipment' => ['boolean'], 'last_shipment_date' => ['nullable', 'date'], 'shipment_schedule' => ['nullable', 'string'],
            'validity_days' => ['nullable', 'integer', 'min:0'], 'place_of_signature' => ['nullable', 'string', 'max:120'], 'tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'], 'tolerance_description' => ['nullable', 'string', 'max:200'],
            'payment_way' => ['nullable', 'string'], 'delivery_terms' => ['nullable', 'string'], 'port_of_loading' => ['nullable', $b->refRule('DELIVERY_PLACE')], 'port_of_discharge' => ['nullable', 'string', 'max:120'], 'insurance' => ['nullable', 'string'],
            'shipment_terms' => ['nullable', 'string'], 'remarks' => ['nullable', 'string'], 'advance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'], 'discount_type' => ['required', Rule::in(['PERCENT', 'AMOUNT'])], 'discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['DRAFT', 'SUBMITTED', 'COMPLETE', 'CANCELLED'])],
            'lines' => ['array'], 'lines.*.item_group' => ['required', Rule::in(ExportBook::ITEM_GROUPS)], 'lines.*.item_type' => ['nullable', 'string', 'max:80'], 'lines.*.description' => ['required', 'string', 'max:300'], 'lines.*.sku' => ['nullable', 'string', 'max:60'],
            'lines.*.color' => ['nullable', 'string', 'max:120'], 'lines.*.size' => ['nullable', 'string', 'max:60'], 'lines.*.qty' => ['required', 'numeric', 'gt:0'], 'lines.*.uom' => ['required', 'string', UomCodes::rule($c)], 'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ], [], ExportBook::attrs());
        $lines = array_values($d['lines'] ?? []);
        $total = round(array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_price'], $lines)), 4);
        if ($d['status'] !== 'DRAFT' && $d['status'] !== 'CANCELLED' && abs($total - (float) $d['pi_amount']) > 0.005) {
            throw ValidationException::withMessages(['lines' => __('tradeop.v.pi_total', ['total' => $b->num($total), 'amount' => $b->num((float) $d['pi_amount'])])]);
        }
        if ($d['status'] === 'CANCELLED' && $p && DB::table('tx_export_lc_pis')->where('pi_id', $p->id)->exists()) {
            throw ValidationException::withMessages(['status' => __('tradeop.v.pi_linked', ['no' => $p->pi_no])]);
        }
        $pct = (float) ($d['advance_pct'] ?? 0);
        $value = (float) ($d['discount_value'] ?? 0);
        $discount = round($d['discount_type'] === 'PERCENT' ? $total * $value / 100 : $value, 4);
        if ($discount > $total) {
            throw ValidationException::withMessages(['discount_value' => __('tradeop.v.pi_discount')]);
        }
        $saved = DB::transaction(function () use ($d, $p, $c, $lines, $total, $pct, $value, $discount) {
            $body = collect($d)->except(['lines'])->all() + ['company_id' => $c];
            $body = array_merge($body, ['total_amount' => $total, 'advance_pct' => $pct, 'advance_amount' => round($total * $pct / 100, 4), 'discount_value' => $value, 'discount_amount' => $discount, 'net_amount' => round($total - $discount, 4), 'partial_shipment' => (bool) ($d['partial_shipment'] ?? false), 'transhipment' => (bool) ($d['transhipment'] ?? false)]);
            if ($p) {
                $p->update($body);
            } else {
                $p = TxExportPi::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            }
            DB::table('tx_export_pi_lines')->where('pi_id', $p->id)->delete();
            foreach ($lines as $i => $l) {
                DB::table('tx_export_pi_lines')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'pi_id' => $p->id, 'item_group' => $l['item_group'], 'item_type' => $l['item_type'] ?? null, 'description' => $l['description'], 'sku' => $l['sku'] ?? null, 'color' => $l['color'] ?? null, 'size' => $l['size'] ?? null,
                    'qty' => $l['qty'], 'uom' => UomCodes::normalize($l['uom']), 'unit_price' => $l['unit_price'], 'total_price' => round($l['qty'] * $l['unit_price'], 4), 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $p;
        });

        return $id ? ApiResponse::ok($this->piBody($saved->fresh())) : ApiResponse::created($this->piBody($saved->fresh()));
    }

    public function piDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $p = TxExportPi::query()->findOrFail($id);
        if ($p->status !== 'DRAFT' || DB::table('tx_export_lc_pis')->where('pi_id', $p->id)->exists()) {
            throw ValidationException::withMessages(['pi_no' => __('tradeop.v.delete_draft', ['no' => $p->pi_no])]);
        }
        $p->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Export L/C & Contract Entry ───────────────────────────────── */

    public function lcs(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();
        $rows = TxExportLc::query()->orderByDesc('issue_date')->orderByDesc('lc_no')->get();
        $uds = $b->udNos($rows->pluck('id')->all());

        return ApiResponse::ok($rows->map(fn (TxExportLc $l) => [
            'id' => $l->id, 'lc_no' => $l->lc_no, 'final' => $l->status === 'COMPLETE', 'status' => $l->status, 'issue_date' => $l->issue_date->toDateString(), 'lc_value' => $l->lc_value, 'kind' => $l->kind, 'merge_state' => $l->merge_state,
            'company' => $b->company(), 'buyer_id' => $l->buyer_id, 'buyer' => $b->buyers()[$l->buyer_id] ?? '—', 'country' => $b->countries()[$l->place_of_expiry_id] ?? '—', 'payment_term' => $l->payment_term, 'payment_term_label' => $b->reference('PAYMENT_TERM', $l->payment_term),
            'receiving_branch_id' => $l->receiving_branch_id, 'amendment_no' => $l->amendment_no, 'ud_no' => $uds[$l->id] ?? null, 'locked' => $l->status === 'COMPLETE',
        ])->values()->all());
    }

    public function lc(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->lcBody(TxExportLc::query()->findOrFail($id)));
    }

    public function saveLc(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $b = $this->book();
        $l = $id ? TxExportLc::query()->findOrFail($id) : null;
        if ($l && $l->status === 'COMPLETE') {
            throw ValidationException::withMessages(['lc_no' => __('tradeop.v.locked', ['no' => $l->lc_no])]);
        }
        $branch = fn (bool $req) => [$req ? 'required' : 'nullable', Rule::exists('tx_bank_branches', 'id')->where('company_id', $c)];
        $d = $request->validate([
            'kind' => ['required', Rule::in(['LC', 'SC'])], 'lc_no' => ['required', 'string', 'max:60', Rule::unique('tx_export_lcs', 'lc_no')->where('company_id', $c)->ignore($l?->id)], 'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'issue_date' => ['required', 'date'], 'expiry_date' => ['required', 'date', 'after_or_equal:issue_date'], 'place_of_expiry_id' => ['required', Rule::exists('origin_countries', 'id')->where('company_id', $c)], 'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)],
            'sender_branch_id' => $branch(true), 'receiving_branch_id' => $branch(true), 'advising_branch_id' => $branch(true), 'same_receiver_bank' => ['boolean'], 'inco_term' => ['required', $b->refRule('INCO_TERM')], 'delivery_place' => ['nullable', $b->refRule('DELIVERY_PLACE')],
            'payment_term_description' => ['nullable', 'string'], 'credit_tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'], 'port_of_loading' => ['required', $b->refRule('TRANSIT_PORT')], 'port_of_discharge' => ['required', 'string', 'max:120'],
            'partial_shipment' => ['boolean'], 'trans_shipment' => ['boolean'], 'last_shipment_date' => ['required', 'date', 'before_or_equal:expiry_date'], 'notify_party' => ['required', 'string', 'max:200'], 'other_info' => ['boolean'], 'is_advance' => ['boolean'],
            'applicant_id' => ['nullable', Rule::exists('tx_lc_applicants', 'id')->where('company_id', $c)], 'lc_amount' => ['nullable', 'numeric', 'min:0'], 'payment_term' => ['required', $b->refRule('PAYMENT_TERM')], 'payment_days' => ['nullable', 'integer', 'min:0'], 'bb_limit_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipment_mode' => ['nullable', $b->refRule('SHIPMENT_MODE')], 'lc_value' => ['required', 'numeric', 'gt:0'], 'ignore_difference' => ['boolean'], 'lc_charge' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', Rule::in(['DRAFT', 'SUBMITTED', 'COMPLETE'])],
            'pis' => ['required', 'array', 'min:1'], 'pis.*' => ['required', Rule::exists('tx_export_pis', 'id')->where('company_id', $c)], 'orders' => ['array'], 'orders.*.order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'orders.*.qty' => ['required', 'numeric', 'gt:0'], 'orders.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ], ['pis.required' => __('tradeop.v.lc_pi'), 'expiry_date.after_or_equal' => __('tradeop.v.lc_expiry'), 'last_shipment_date.before_or_equal' => __('tradeop.v.lc_ship')], ExportBook::attrs());

        if ($l && $b->lcHasInvoices($l->id)) {
            if ($d['buyer_id'] !== $l->buyer_id) {
                throw ValidationException::withMessages(['buyer_id' => __('tradeop.v.lc_in_use', ['no' => $l->lc_no])]);
            }
            $invoiced = (float) DB::table('tx_commercial_invoices')->where('lc_id', $l->id)->sum('invoiced_amount');
            if ((float) $d['lc_value'] < $invoiced) {
                throw ValidationException::withMessages(['lc_value' => __('tradeop.v.lc_below', ['no' => $l->lc_no, 'amount' => $b->num($invoiced)])]);
            }
        }
        $pis = array_values(array_unique($d['pis']));
        foreach (DB::table('tx_export_pis')->whereIn('id', $pis)->get(['pi_no', 'buyer_id', 'status']) as $pi) {
            if ($pi->buyer_id !== $d['buyer_id']) {
                throw ValidationException::withMessages(['pis' => __('tradeop.v.lc_pi_buyer', ['no' => $pi->pi_no])]);
            }
            if (! in_array($pi->status, ['SUBMITTED', 'COMPLETE'], true)) {
                throw ValidationException::withMessages(['pis' => __('tradeop.v.lc_pi_status', ['no' => $pi->pi_no])]);
            }
        }
        $taken = DB::table('tx_export_lc_pis as x')->join('tx_export_lcs as l', 'l.id', '=', 'x.lc_id')->join('tx_export_pis as p', 'p.id', '=', 'x.pi_id')->whereIn('x.pi_id', $pis)->when($l, fn ($q) => $q->where('x.lc_id', '!=', $l->id))->first(['p.pi_no', 'l.lc_no']);
        if ($taken) {
            throw ValidationException::withMessages(['pis' => __('tradeop.v.lc_pi_taken', ['no' => $taken->pi_no, 'lc' => $taken->lc_no])]);
        }
        $orders = collect($d['orders'] ?? [])->values();
        if ($orders->pluck('order_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['orders' => __('tradeop.v.lc_order_twice')]);
        }
        foreach (DB::table('merch_orders')->whereIn('id', $orders->pluck('order_id'))->get(['order_no', 'buyer_id']) as $o) {
            if ($o->buyer_id !== $d['buyer_id']) {
                throw ValidationException::withMessages(['orders' => __('tradeop.v.lc_order_buyer', ['no' => $o->order_no])]);
            }
        }
        $po = round($orders->sum(fn ($o) => $o['qty'] * $o['unit_price']), 4);
        if ($d['status'] !== 'DRAFT' && empty($d['ignore_difference']) && abs($po - (float) $d['lc_value']) > 0.005) {
            throw ValidationException::withMessages(['lc_value' => __('tradeop.v.lc_diff', ['lc' => $b->num((float) $d['lc_value']), 'po' => $b->num($po)])]);
        }
        $saved = DB::transaction(function () use ($d, $l, $c, $pis, $orders, $po) {
            $body = collect($d)->except(['pis', 'orders'])->all() + ['company_id' => $c];
            foreach (['same_receiver_bank', 'partial_shipment', 'trans_shipment', 'other_info', 'is_advance', 'ignore_difference'] as $flag) {
                $body[$flag] = (bool) ($d[$flag] ?? false);
            }
            $body = array_merge($body, ['credit_tolerance_pct' => (float) ($d['credit_tolerance_pct'] ?? 3), 'lc_amount' => (float) ($d['lc_amount'] ?? 0), 'payment_days' => (int) ($d['payment_days'] ?? 0), 'po_value' => $po]);
            if ($l) {
                $l->update($body);
            } else {
                $l = TxExportLc::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            }
            DB::table('tx_export_lc_pis')->where('lc_id', $l->id)->delete();
            DB::table('tx_export_lc_orders')->where('lc_id', $l->id)->delete();
            foreach ($pis as $pi) {
                DB::table('tx_export_lc_pis')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'lc_id' => $l->id, 'pi_id' => $pi, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($orders as $o) {
                DB::table('tx_export_lc_orders')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'lc_id' => $l->id, 'order_id' => $o['order_id'], 'qty' => $o['qty'], 'unit_price' => $o['unit_price'], 'created_at' => now(), 'updated_at' => now()]);
            }

            return $l;
        });

        return $id ? ApiResponse::ok($this->lcBody($saved->fresh())) : ApiResponse::created($this->lcBody($saved->fresh()));
    }

    public function lcDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $l = TxExportLc::query()->findOrFail($id);
        if ($l->status !== 'DRAFT' || $this->book()->lcHasInvoices($l->id)) {
            throw ValidationException::withMessages(['lc_no' => __('tradeop.v.delete_draft', ['no' => $l->lc_no])]);
        }
        $l->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** The PI's lines, for the Link With PI grid of an L/C. */
    public function piLines(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $p = TxExportPi::query()->findOrFail($id);

        return ApiResponse::ok($this->book()->piRows([$p->id]));
    }

    /* ── Bodies ────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function piBody(TxExportPi $p): array
    {
        return array_merge($p->only($p->getFillable()), ['id' => $p->id, 'pi_date' => $p->pi_date->toDateString(), 'expected_delivery_date' => $p->expected_delivery_date?->toDateString(), 'last_shipment_date' => $p->last_shipment_date?->toDateString(),
            'locked' => in_array($p->status, ['COMPLETE', 'CANCELLED'], true),
            'lines' => DB::table('tx_export_pi_lines')->where('pi_id', $p->id)->orderBy('sort_order')->get(['item_group', 'item_type', 'description', 'sku', 'color', 'size', 'qty', 'uom', 'unit_price', 'total_price'])->map(fn ($r) => ['item_group' => $r->item_group, 'item_type' => $r->item_type, 'description' => $r->description, 'sku' => $r->sku, 'color' => $r->color, 'size' => $r->size, 'qty' => (float) $r->qty, 'uom' => $r->uom, 'unit_price' => (float) $r->unit_price, 'total_price' => (float) $r->total_price])->all()]);
    }

    /** @return array<string, mixed> */
    private function lcBody(TxExportLc $l): array
    {
        $b = $this->book();
        $pis = DB::table('tx_export_lc_pis')->where('lc_id', $l->id)->pluck('pi_id')->all();
        $orders = DB::table('tx_export_lc_orders as x')->join('merch_orders as o', 'o.id', '=', 'x.order_id')->where('x.lc_id', $l->id)->get(['x.order_id', 'x.qty', 'x.unit_price', 'o.order_no', 'o.style_no', 'o.unit']);

        return array_merge($l->only($l->getFillable()), ['id' => $l->id, 'issue_date' => $l->issue_date->toDateString(), 'expiry_date' => $l->expiry_date->toDateString(), 'last_shipment_date' => $l->last_shipment_date->toDateString(), 'prc_date' => $l->prc_date?->toDateString(), 'prc_entry_date' => $l->prc_entry_date?->toDateString(), 'locked' => $l->status === 'COMPLETE',
            'pis' => $pis, 'pi_rows' => $b->piRows($pis), 'orders' => $orders->map(fn ($o) => ['order_id' => $o->order_id, 'order_no' => $o->order_no, 'style_no' => $o->style_no, 'unit' => $o->unit, 'qty' => (float) $o->qty, 'unit_price' => (float) $o->unit_price])->all()]);
    }

    private function book(): ExportBook
    {
        return $this->book ??= new ExportBook($this->caller()->companyId);
    }
}
