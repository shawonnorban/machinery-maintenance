<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitScBill;
use App\Modules\Knitting\Models\KnitScBillLine;
use App\Modules\Knitting\Models\KnitScDebitMemo;
use App\Modules\Knitting\Models\KnitScDebitMemoLine;
use App\Modules\Knitting\Models\KnitScProgram;
use App\Modules\Knitting\Models\KnitScProgramLine;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptFabric;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract
 * money papers, as Brickland works them:
 *
 * - In-House / Out-House Sub-Contract Bill and Out-House Yarn Dyeing Bill:
 *   one paper in three kinds, each charge line booked to the party's own
 *   programme (in-house programme receipt / S/C programme / Y/D programme).
 *   Saving writes a draft; Submit claims it (and marks the Ref.Bill# it
 *   replaces Revised), Approve makes it payable; Cancel before approval
 *   (restoring a bill it had revised). Amount = qty × rate, net = gross −
 *   deduction + VAT, recomputed from what is stored.
 * - Out-House Debit Memo: charged lines against the party's challans;
 *   Submit → Approve (claimable) → Settle, or Cancel before approval.
 */
class KnitScBillApiController extends ApiController
{
    private const SLUGS = ['in-house' => 'IN_HOUSE', 'out-house' => 'OUT_HOUSE', 'yd' => 'YD'];

    private const PREFIX = ['IN_HOUSE' => 'SIB', 'OUT_HOUSE' => 'SOB', 'YD' => 'YDB'];

    private const LINE_KEY = ['IN_HOUSE' => 'sc_receipt_id', 'OUT_HOUSE' => 'sc_program_id', 'YD' => 'yd_program_id'];

    public function options(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $o = YarnProcurement::options();

        return ApiResponse::ok(collect($o)->only(['company', 'buyers', 'orders', 'suppliers', 'uoms', 'currencies'])->all() + [
            'colours' => KnitProduction::options()['colours'], 'statuses' => KnitScBill::STATUSES, 'memo_statuses' => KnitScDebitMemo::STATUSES,
            'parts' => KnitScDebitMemo::PARTS, 'programs' => $this->programs(),
        ]);
    }

    /* ---------------- bills ---------------- */

    public function bills(string $kind): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $rows = KnitScBill::query()->where('kind', $this->kind($kind))->orderByDesc('bill_date')->orderByDesc('bill_no')->limit(500)->get();
        $qty = KnitScBillLine::query()->whereIn('bill_id', $rows->pluck('id'))->groupBy('bill_id')->selectRaw('bill_id, count(*) as n')->get()->keyBy('bill_id');
        $units = $this->units(KnitScBillLine::class, 'bill_id', $rows->pluck('id'));
        $parties = $this->parties($rows->pluck('party_id'));
        $refs = KnitScBill::query()->whereIn('id', $rows->pluck('ref_bill_id')->filter())->pluck('bill_no', 'id');
        $cur = $this->currencies();

        return ApiResponse::ok($rows->map(fn (KnitScBill $b) => [
            'bill_no' => $b->bill_no, 'bill_date' => $b->bill_date->toDateString(), 'party_id' => $b->party_id, 'party' => $parties[$b->party_id] ?? null,
            'party_bill_no' => $b->party_bill_no, 'ref_bill_no' => $refs[$b->ref_bill_id] ?? null, 'currency' => $cur[$b->currency_id] ?? null,
            'lines' => (int) ($qty[$b->id]->n ?? 0), 'qty' => $units[$b->id] ?? [],
            'gross' => round($b->gross, 2), 'deduction' => round($b->deduction, 2), 'vat' => round($b->vat, 2), 'net' => round($b->net, 2), 'status' => $b->status,
        ])->values()->all());
    }

    public function showBill(string $kind, string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentBill($this->bill($kind, $no)));
    }

    public function saveBill(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $k = $this->kind($kind);
        $row = $no ? $this->bill($kind, $no) : null;
        $row && $this->assertStatus($row->bill_no, $row->status, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'bill_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'party_bill_no' => ['nullable', 'string', 'max:60'], 'ref_bill_no' => ['nullable', 'string', 'max:24'], 'rev_descr' => ['nullable', 'required_with:ref_bill_no', 'string', 'max:300'],
            'currency_id' => ['nullable', Rule::exists('currencies', 'id')->where('company_id', $company)],
            'deduction' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'vat' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.program_no' => ['required', 'string', 'max:24'],
            'lines.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)], 'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)], 'lines.*.colour_name' => ['nullable', 'string', 'max:80'],
            'lines.*.shade' => ['nullable', 'string', 'max:40'], 'lines.*.challan_no' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'bill_date.required' => __('knitting.scb.v.date'), 'party_id.required' => __('knitting.scb.v.party'), 'rev_descr.required_with' => __('knitting.scb.v.rev_descr'),
            'lines.*.program_no.required' => __("knitting.scb.v.program_{$k}"), 'lines.*.qty.required' => __('knitting.scb.v.qty'), 'lines.*.qty.gt' => __('knitting.scb.v.qty'),
            'lines.*.uom.required' => __('knitting.scb.v.uom'), 'lines.*.rate.required' => __('knitting.scb.v.rate'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.scb.v.no_lines')]);
        }
        $programs = collect($this->programs()[$k])->keyBy('value');
        $out = [];
        foreach ($lines as $i => $l) {
            $p = $programs[$l['program_no']] ?? null;
            if (! $p) {
                throw ValidationException::withMessages(["lines.{$i}.program_no" => __('knitting.scb.v.program_unknown', ['n' => $i + 1, 'no' => $l['program_no']])]);
            }
            if ($p['party_id'] !== $data['party_id']) {
                throw ValidationException::withMessages(["lines.{$i}.program_no" => __('knitting.scb.v.program_party', ['n' => $i + 1, 'no' => $l['program_no']])]);
            }
            $out[] = [self::LINE_KEY[$k] => $p['id'], 'qty' => round((float) $l['qty'], 4), 'rate' => round((float) $l['rate'], 4),
                'amount' => round((float) $l['qty'] * (float) $l['rate'], 4), 'uom' => UomCodes::normalize($l['uom'])]
                + collect($l)->only(['order_id', 'description', 'colour_id', 'colour_name', 'shade', 'challan_no', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['order_id' => $p['order_id'] ?? null];
        }
        $ref = null;
        if (! empty($data['ref_bill_no'])) {
            $ref = KnitScBill::query()->where('kind', $k)->where('bill_no', $data['ref_bill_no'])->first();
            if (! $ref || $ref->party_id !== $data['party_id'] || $ref->status !== 'SUBMITTED' || $ref->id === $row?->id) {
                throw ValidationException::withMessages(['ref_bill_no' => __('knitting.scb.v.ref_bill')]);
            }
        }
        if (! empty($data['party_bill_no']) && KnitScBill::query()->where('kind', $k)->where('party_id', $data['party_id'])->where('party_bill_no', $data['party_bill_no'])
            ->whereNotIn('status', ['CANCELLED', 'REVISED'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->when($ref, fn ($q) => $q->whereKeyNot($ref->id))->exists()) {
            throw ValidationException::withMessages(['party_bill_no' => __('knitting.scb.v.party_bill_taken', ['no' => $data['party_bill_no']])]);
        }
        $gross = round(array_sum(array_column($out, 'amount')), 4);
        $deduction = round((float) ($data['deduction'] ?? 0), 4);
        $vat = round((float) ($data['vat'] ?? 0), 4);
        if ($deduction > $gross + $vat + 0.00005) {
            throw ValidationException::withMessages(['deduction' => __('knitting.scb.v.deduction', ['gross' => number_format($gross + $vat, 2)])]);
        }
        $row = DB::transaction(function () use ($row, $data, $out, $k, $ref, $gross, $deduction, $vat): KnitScBill {
            $head = collect($data)->only(['bill_date', 'party_id', 'party_bill_no', 'rev_descr', 'currency_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['party_bill_no' => null, 'rev_descr' => null, 'currency_id' => null, 'remarks' => null]
                + ['ref_bill_id' => $ref?->id, 'gross' => $gross, 'deduction' => $deduction, 'vat' => $vat, 'net' => round($gross - $deduction + $vat, 4)];
            if (! $ref) {
                $head['rev_descr'] = null;
            }
            $row ??= KnitScBill::query()->create($head + ['kind' => $k, 'bill_no' => KnitProduction::nextNumber(self::PREFIX[$k], 'knit_sc_bills', 'bill_no', $data['bill_date']),
                'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitScBillLine::query()->where('bill_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitScBillLine::query()->create($l + ['company_id' => $row->company_id, 'bill_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentBill($row->refresh())) : ApiResponse::created($this->presentBill($row->refresh()));
    }

    public function billTransition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->bill($kind, $no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'approve' => [['SUBMITTED'], 'APPROVED'],
            'cancel' => [['DRAFT', 'SUBMITTED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->bill_no, $row->status, $from, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            $ref = $row->ref_bill_id ? KnitScBill::query()->lockForUpdate()->find($row->ref_bill_id) : null;
            if ($action === 'submit') {
                if ($ref && $ref->status !== 'SUBMITTED') {
                    throw ValidationException::withMessages(['status' => __('knitting.scb.v.ref_moved', ['no' => $ref->bill_no, 'status' => __("knitting.scb.status.{$ref->status}")])]);
                }
                $ref?->update(['status' => 'REVISED']);
                $row->update(['submitted_at' => now()]);
            }
            if ($action === 'approve') {
                $row->update(['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()]);
            }
            if ($action === 'cancel' && $row->status === 'SUBMITTED' && $ref?->status === 'REVISED') {
                $ref->update(['status' => 'SUBMITTED']);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentBill($row->refresh()));
    }

    /* ---------------- debit memos ---------------- */

    public function memos(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $rows = KnitScDebitMemo::query()->orderByDesc('memo_date')->orderByDesc('memo_no')->limit(500)->get();
        $qty = KnitScDebitMemoLine::query()->whereIn('memo_id', $rows->pluck('id'))->groupBy('memo_id')->selectRaw('memo_id, count(*) as n')->get()->keyBy('memo_id');
        $units = $this->units(KnitScDebitMemoLine::class, 'memo_id', $rows->pluck('id'));
        $parties = $this->parties($rows->pluck('party_id'));
        $cur = $this->currencies();

        return ApiResponse::ok($rows->map(fn (KnitScDebitMemo $m) => [
            'memo_no' => $m->memo_no, 'memo_date' => $m->memo_date->toDateString(), 'party_id' => $m->party_id, 'party' => $parties[$m->party_id] ?? null,
            'reason' => $m->reason, 'currency' => $cur[$m->currency_id] ?? null, 'lines' => (int) ($qty[$m->id]->n ?? 0), 'qty' => $units[$m->id] ?? [],
            'total' => round($m->total, 2), 'status' => $m->status,
        ])->values()->all());
    }

    public function showMemo(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentMemo(KnitScDebitMemo::query()->where('memo_no', $no)->firstOrFail()));
    }

    public function saveMemo(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? KnitScDebitMemo::query()->where('memo_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->memo_no, $row->status, ['DRAFT'], 'save', 'memo_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'memo_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'currency_id' => ['nullable', Rule::exists('currencies', 'id')->where('company_id', $company)], 'reason' => ['nullable', 'string', 'max:300'],
            'remarks' => ['nullable', 'string', 'max:2000'], 'lines' => ['present', 'array', 'max:300'],
            'lines.*.part' => ['nullable', Rule::in(KnitScDebitMemo::PARTS)], 'lines.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)], 'lines.*.colour_name' => ['nullable', 'string', 'max:80'],
            'lines.*.description' => ['required', 'string', 'max:200'], 'lines.*.challan_no' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'memo_date.required' => __('knitting.scb.v.memo_date'), 'party_id.required' => __('knitting.scb.v.memo_party'), 'lines.*.description.required' => __('knitting.scb.v.description'),
            'lines.*.qty.required' => __('knitting.scb.v.qty'), 'lines.*.qty.gt' => __('knitting.scb.v.qty'), 'lines.*.uom.required' => __('knitting.scb.v.uom'),
            'lines.*.rate.required' => __('knitting.scb.v.rate'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.scb.v.no_memo_lines')]);
        }
        $out = array_map(fn (array $l) => ['qty' => round((float) $l['qty'], 4), 'rate' => round((float) $l['rate'], 4), 'amount' => round((float) $l['qty'] * (float) $l['rate'], 4),
            'uom' => UomCodes::normalize($l['uom']), 'description' => trim($l['description'])]
            + collect($l)->only(['part', 'order_id', 'colour_id', 'colour_name', 'challan_no', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all(), $lines);
        $row = DB::transaction(function () use ($row, $data, $out): KnitScDebitMemo {
            $head = collect($data)->only(['memo_date', 'party_id', 'currency_id', 'reason', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['currency_id' => null, 'reason' => null, 'remarks' => null] + ['total' => round(array_sum(array_column($out, 'amount')), 4)];
            $row ??= KnitScDebitMemo::query()->create($head + ['memo_no' => KnitProduction::nextNumber('DMO', 'knit_sc_debit_memos', 'memo_no', $data['memo_date']),
                'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitScDebitMemoLine::query()->where('memo_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitScDebitMemoLine::query()->create($l + ['company_id' => $row->company_id, 'memo_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentMemo($row->refresh())) : ApiResponse::created($this->presentMemo($row->refresh()));
    }

    public function memoTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = KnitScDebitMemo::query()->where('memo_no', $no)->firstOrFail();
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'approve' => [['SUBMITTED'], 'APPROVED'],
            'settle' => [['APPROVED'], 'SETTLED'],
            'cancel' => [['DRAFT', 'SUBMITTED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->memo_no, $row->status, $from, $action, 'memo_status');
        $row->update(['status' => $to] + ($action === 'approve' ? ['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->presentMemo($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function kind(string $slug): string
    {
        return self::SLUGS[$slug] ?? abort(404);
    }

    private function bill(string $kind, string $no): KnitScBill
    {
        return KnitScBill::query()->where('kind', $this->kind($kind))->where('bill_no', $no)->firstOrFail();
    }

    private function assertStatus(string $no, string $status, array $from, string $action, string $group = 'status'): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.scb.v.cannot', [
                'no' => $no, 'status' => __("knitting.scb.{$group}.{$status}"), 'action' => __("knitting.scb.action.{$action}"),
            ])]);
        }
    }

    /**
     * Quantity per unit on each paper — kilograms and pieces never add up.
     *
     * @return array<string, array<string, float>>
     */
    private function units(string $model, string $key, $ids): array
    {
        $out = [];
        foreach ($model::query()->whereIn($key, collect($ids)->values())->groupBy($key, 'uom')->selectRaw("{$key} as k, uom, sum(qty) as q")->get() as $r) {
            $out[$r->k][$r->uom] = round((float) $r->q, 3);
        }

        return $out;
    }

    private function parties($ids): Collection
    {
        return DB::table('mp_suppliers')->whereIn('id', collect($ids)->filter()->unique()->values())->pluck('name', 'id');
    }

    private function currencies(): Collection
    {
        return DB::table('currencies')->where('company_id', app(TenantContext::class)->companyId())->pluck('code', 'id');
    }

    /**
     * Each kind's programmes a charge may be booked to, with the party they
     * belong to, the order, the first line's fabric / colour / rate (what a
     * new charge line starts from), what was planned and what has been
     * billed on live bills.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function programs(): array
    {
        $billed = fn (string $col) => KnitScBillLine::query()->whereNotNull($col)
            ->whereIn('bill_id', KnitScBill::query()->whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED'])->pluck('id'))
            ->groupBy($col)->selectRaw("{$col} as p, sum(qty) as q")->pluck('q', 'p');
        $colours = DB::table('colours')->where('company_id', app(TenantContext::class)->companyId())->pluck('name', 'id');

        $receipts = KnitScReceipt::query()->whereNotIn('status', ['DRAFT', 'CANCELLED'])->orderByDesc('prog_date')->limit(500)->get();
        $fabrics = KnitScReceiptFabric::query()->whereIn('receipt_id', $receipts->pluck('id'))->orderBy('sl')->get()->groupBy('receipt_id');
        $inBilled = $billed('sc_receipt_id');
        $progs = KnitScProgram::query()->where('status', 'APPROVED')->orderByDesc('program_no')->limit(500)->get();
        $progLines = KnitScProgramLine::query()->whereIn('program_id', $progs->pluck('id'))->orderBy('sort_order')->get()->groupBy('program_id');
        $outBilled = $billed('sc_program_id');
        $yds = KnitYdProgram::query()->where('status', '!=', 'CANCELLED')->orderByDesc('program_date')->limit(500)->get();
        $ydLines = KnitYdProgramLine::query()->whereIn('program_id', $yds->pluck('id'))->orderBy('sort_order')->get()->groupBy('program_id');
        $ydBilled = $billed('yd_program_id');

        return [
            'IN_HOUSE' => $receipts->map(function (KnitScReceipt $r) use ($fabrics, $inBilled, $colours) {
                $f = ($fabrics[$r->id] ?? collect())->first();

                return ['value' => $r->program_no, 'id' => $r->id, 'party_id' => $r->party_id, 'order_id' => $f?->order_id, 'description' => $f?->party_ref,
                    'colour_id' => $f?->colour_id, 'colour_name' => $f?->colour_name ?: ($colours[$f?->colour_id] ?? null), 'rate' => $f ? (float) $f->rate : null, 'uom' => $f?->uom,
                    'planned' => round((float) ($fabrics[$r->id] ?? collect())->sum('qty'), 3), 'billed' => round((float) ($inBilled[$r->id] ?? 0), 3)];
            })->values()->all(),
            'OUT_HOUSE' => $progs->map(function (KnitScProgram $p) use ($progLines, $outBilled, $colours) {
                $mine = $progLines[$p->id] ?? collect();
                $f = $mine->first();

                return ['value' => $p->program_no, 'id' => $p->id, 'party_id' => $p->party_id, 'order_id' => $p->order_id, 'description' => $f?->fabric,
                    'colour_id' => $f?->colour_id, 'colour_name' => $colours[$f?->colour_id] ?? null, 'rate' => $f ? (float) $f->rate : null, 'uom' => $f?->uom,
                    'planned' => round((float) $mine->sum('qty'), 3), 'received' => round((float) $mine->sum('received_qty'), 3), 'billed' => round((float) ($outBilled[$p->id] ?? 0), 3)];
            })->values()->all(),
            'YD' => $yds->map(function (KnitYdProgram $p) use ($ydLines, $ydBilled) {
                $mine = $ydLines[$p->id] ?? collect();
                $f = $mine->first();

                return ['value' => $p->program_no, 'id' => $p->id, 'party_id' => $p->supplier_id, 'order_id' => $p->order_id, 'description' => $f ? 'Yarn dyeing' : null,
                    'colour_id' => $f?->colour_id, 'colour_name' => $f?->colour_name, 'rate' => $f ? (float) $f->rate : null, 'uom' => $f?->uom,
                    'planned' => round((float) $mine->sum('qty'), 3), 'billed' => round((float) ($ydBilled[$p->id] ?? 0), 3)];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function head(string $companyId, string $partyId, ?string $currencyId): array
    {
        $party = DB::table('mp_suppliers')->where('id', $partyId)->first(['name', 'address']);

        return ['company' => DB::table('companies')->where('id', $companyId)->value('name'), 'party' => $party?->name, 'party_address' => $party?->address,
            'currency' => $currencyId ? DB::table('currencies')->where('id', $currencyId)->value('code') : null];
    }

    /** @return array<string, mixed> */
    private function presentBill(KnitScBill $b): array
    {
        $key = self::LINE_KEY[$b->kind];
        $lines = KnitScBillLine::query()->where('bill_id', $b->id)->orderBy('sort_order')->get();
        $nos = match ($b->kind) {
            'IN_HOUSE' => KnitScReceipt::query()->whereIn('id', $lines->pluck($key))->pluck('program_no', 'id'),
            'OUT_HOUSE' => KnitScProgram::query()->whereIn('id', $lines->pluck($key))->pluck('program_no', 'id'),
            default => KnitYdProgram::query()->whereIn('id', $lines->pluck($key))->pluck('program_no', 'id'),
        };
        $orders = DB::table('merch_orders')->whereIn('id', $lines->pluck('order_id')->filter())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return ['id' => $b->id, 'bill_no' => $b->bill_no, 'kind' => $b->kind, 'bill_date' => $b->bill_date->toDateString(), 'party_id' => $b->party_id,
            'party_bill_no' => $b->party_bill_no, 'ref_bill_no' => $b->ref_bill_id ? KnitScBill::query()->whereKey($b->ref_bill_id)->value('bill_no') : null,
            'revised_by' => KnitScBill::query()->where('ref_bill_id', $b->id)->whereNotIn('status', ['DRAFT', 'CANCELLED'])->value('bill_no'),
            'rev_descr' => $b->rev_descr, 'currency_id' => $b->currency_id, 'gross' => round($b->gross, 2), 'deduction' => round($b->deduction, 2), 'vat' => round($b->vat, 2),
            'net' => round($b->net, 2), 'remarks' => $b->remarks, 'status' => $b->status] + $this->head($b->company_id, $b->party_id, $b->currency_id) + [
                'lines' => $lines->map(function (KnitScBillLine $l) use ($key, $nos, $orders) {
                    $o = $orders[$l->order_id] ?? null;

                    return collect($l->only(['order_id', 'description', 'colour_id', 'colour_name', 'shade', 'challan_no', 'qty', 'uom', 'rate', 'amount', 'remarks']))->all()
                        + ['program_no' => $nos[$l->{$key}] ?? null, 'order' => $o ? trim(($o->work_style_no ?: $o->style_no).' · '.$o->order_no, ' ·') : null];
                })->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentMemo(KnitScDebitMemo $m): array
    {
        $lines = KnitScDebitMemoLine::query()->where('memo_id', $m->id)->orderBy('sort_order')->get();
        $orders = DB::table('merch_orders')->whereIn('id', $lines->pluck('order_id')->filter())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return ['id' => $m->id, 'memo_no' => $m->memo_no, 'memo_date' => $m->memo_date->toDateString(), 'party_id' => $m->party_id, 'currency_id' => $m->currency_id,
            'reason' => $m->reason, 'remarks' => $m->remarks, 'total' => round($m->total, 2), 'status' => $m->status] + $this->head($m->company_id, $m->party_id, $m->currency_id) + [
                'lines' => $lines->map(function (KnitScDebitMemoLine $l) use ($orders) {
                    $o = $orders[$l->order_id] ?? null;

                    return collect($l->only(['part', 'order_id', 'colour_id', 'colour_name', 'description', 'challan_no', 'qty', 'uom', 'rate', 'amount', 'remarks']))->all()
                        + ['order' => $o ? trim(($o->work_style_no ?: $o->style_no).' · '.$o->order_no, ' ·') : null];
                })->values()->all(),
            ];
    }
}
