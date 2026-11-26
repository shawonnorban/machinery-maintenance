<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitYarnReplaceReq;
use App\Modules\Knitting\Models\KnitYarnReplaceReqLine;
use App\Modules\Knitting\Models\YarnReplacement;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Knitting\Support\YarnTesting;
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
 * Knit Production → Production (AnnotechERP 15/04) → Supplier Yarn
 * Replacement Indent, as Brickland works it: a claim on a spinner (YRR-…)
 * to replace yarn this mill will not knit — the return type, the L/C the
 * lot came in under, the lot the claim is about, and one line per lot going
 * back (the lot number upper-cased, so two spellings of one lot cannot both
 * be on the paper). Saving writes a draft; Submit and Issue are on the
 * register — Issued means the yarn has physically left, the store's word.
 */
class KnitYarnReplaceReqApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $o = YarnTesting::options();

        return ApiResponse::ok(collect($o)->only(['company', 'stores', 'suppliers', 'colour_groups', 'uoms', 'lots', 'yarn_names'])->all() + [
            'return_types' => YarnReplacement::TYPES, 'statuses' => KnitYarnReplaceReq::STATUSES,
            'reqs' => KnitYarnReplaceReq::query()->orderByDesc('req_date')->orderByDesc('req_no')->limit(300)->get(['req_no', 'req_date', 'status'])
                ->map(fn (KnitYarnReplaceReq $r) => ['value' => $r->req_no, 'label' => $r->req_no.' — '.$r->req_date->format('d M Y').' · '.__("knitting.rr.status.{$r->status}")])->values()->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->register(KnitYarnReplaceReq::query()->orderByDesc('req_date')->orderByDesc('req_no')->limit(500)->get()));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.rr.v.fixed', ['no' => $row->req_no, 'status' => __("knitting.rr.status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'return_type' => ['required', Rule::in(YarnReplacement::TYPES)], 'lc_no' => ['nullable', 'string', 'max:40'], 'lot_ref' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.brand' => ['nullable', 'string', 'max:80'],
            'lines.*.lot_no' => ['required', 'string', 'max:40'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'req_date.required' => __('knitting.rr.v.req_date'), 'store_id.required' => __('knitting.rr.v.store'), 'supplier_id.required' => __('knitting.rr.v.supplier'),
            'return_type.required' => __('knitting.rr.v.return_type'), 'lines.*.lot_no.required' => __('knitting.rr.v.lot'),
            'lines.*.qty.required' => __('knitting.rr.v.qty'), 'lines.*.qty.gt' => __('knitting.rr.v.qty'), 'lines.*.uom.required' => __('knitting.rr.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.rr.v.no_lines')]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines): KnitYarnReplaceReq {
            $head = ['req_date' => $data['req_date'], 'store_id' => $data['store_id'], 'supplier_id' => $data['supplier_id'], 'return_type' => $data['return_type'],
                'lc_no' => ($data['lc_no'] ?? null) ?: null, 'lot_ref' => isset($data['lot_ref']) && $data['lot_ref'] !== '' ? mb_strtoupper(trim($data['lot_ref'])) : null,
                'remarks' => $data['remarks'] ?? null];
            $row ??= KnitYarnReplaceReq::query()->create($head + [
                'req_no' => KnitProduction::nextNumber('YRR', 'knit_yarn_replace_reqs', 'req_no', $data['req_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitYarnReplaceReqLine::query()->where('req_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYarnReplaceReqLine::query()->create([
                    'company_id' => $row->company_id, 'req_id' => $row->id, 'sort_order' => $i + 1, 'lot_no' => mb_strtoupper(trim($l['lot_no'])),
                    'qty' => (float) $l['qty'], 'uom' => UomCodes::normalize($l['uom']),
                ] + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** Submit (Draft → Submitted), Issue (Submitted → Issued: the yarn has left), Cancel (before it leaves). */
    public function transition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'issue' => [['SUBMITTED'], 'ISSUED'],
            'cancel' => [['DRAFT', 'SUBMITTED'], 'CANCELLED'],
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.rr.v.cannot', ['no' => $row->req_no, 'status' => __("knitting.rr.status.{$row->status}"), 'action' => __("knitting.rr.action.{$action}")])]);
        }
        $row->update(['status' => $to]);

        return ApiResponse::ok($this->register(collect([$row->refresh()]))[0]);
    }

    private function find(string $no): KnitYarnReplaceReq
    {
        return KnitYarnReplaceReq::query()->where('req_no', $no)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function register(Collection $rows): array
    {
        $lines = KnitYarnReplaceReqLine::query()->whereIn('req_id', $rows->pluck('id'))->get()->groupBy('req_id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->unique())->pluck('name', 'id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id')->unique())->pluck('name', 'id');
        $company = DB::table('companies')->where('id', app(TenantContext::class)->companyId())->value('name');

        return $rows->map(fn (KnitYarnReplaceReq $r) => [
            'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'company' => $company, 'store' => $stores[$r->store_id] ?? null,
            'supplier_id' => $r->supplier_id, 'supplier' => $suppliers[$r->supplier_id] ?? null, 'return_type' => $r->return_type, 'lc_no' => $r->lc_no, 'lot_ref' => $r->lot_ref,
            'lines' => ($lines[$r->id] ?? collect())->count(), 'qty' => round((float) ($lines[$r->id] ?? collect())->sum('qty'), 3), 'status' => $r->status,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitYarnReplaceReq $r): array
    {
        $names = YarnProcurement::names();

        return array_merge($this->register(collect([$r]))[0], [
            'id' => $r->id, 'store_id' => $r->store_id, 'remarks' => $r->remarks,
            'lines' => KnitYarnReplaceReqLine::query()->where('req_id', $r->id)->orderBy('sort_order')->get()->map(fn (KnitYarnReplaceReqLine $l) => collect($l->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'qty', 'uom', 'remarks']))->all() + [
                'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')) ?: null,
            ])->values()->all(),
        ]);
    }
}
