<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitSamplePlan;
use App\Modules\Knitting\Models\KnitSamplePlanLine;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\KnitSamples;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
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
 * Knit Production → Production (AnnotechERP 15/04) → Solid and Yarn-Dyed
 * Sample Knit Plan, as Brickland works them: the sample room's knit plan
 * (SYR-S-… / SYR-Y-…), one line per order or KP — what the sample room
 * wrote about the yarn, and the registered yarn the store issues against.
 * Saving keeps a draft; Submit Requisition on the register turns it into
 * the Yarn Store's own requisition (15/03) and the plan stops being
 * editable; Issued and Closed are what that requisition has come to. Cancel
 * withdraws the plan — a requisition already raised stays with the store.
 */
class KnitSamplePlanApiController extends ApiController
{
    private const PREFIX = ['SOLID' => 'SYR-S', 'YD' => 'SYR-Y'];

    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok(KnitSamples::options() + [
            'plans' => KnitSamplePlan::query()->orderByDesc('plan_date')->orderByDesc('plan_no')->limit(500)->get(['plan_no', 'kind', 'plan_date', 'status'])
                ->map(fn (KnitSamplePlan $p) => ['value' => $p->plan_no, 'kind' => $p->kind, 'label' => $p->plan_no.' — '.$p->plan_date->format('d M Y')])->values()->all(),
        ]);
    }

    public function index(Request $request, string $kind): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'buyer' => ['nullable', 'string'], 'store' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(KnitSamplePlan::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rows = KnitSamplePlan::query()->where('kind', $kind)
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['store']), fn ($q) => $q->where('store_id', $f['store']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('plan_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('plan_date', '<=', $f['to']))
            ->orderByDesc('plan_date')->orderByDesc('plan_no')->limit(300)->get();
        $out = $this->register($rows);
        if (! empty($f['status'])) {
            $out = array_values(array_filter($out, fn ($r) => $r['status'] === $f['status']));
        }

        return ApiResponse::ok($out);
    }

    public function show(string $kind, string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($kind, $no)));
    }

    public function save(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->find($kind, $no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.sk.v.fixed', ['no' => $row->plan_no, 'status' => __('knitting.sk.status.'.$this->status($row))])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'plan_date' => ['required', 'date'], 'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'lines.*.style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'lines.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'lines.*.style_text' => ['nullable', 'string', 'max:80'],
            'lines.*.fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.colour_name' => ['nullable', 'string', 'max:80'], 'lines.*.yarn_details' => ['nullable', 'string', 'max:200'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.brand' => ['nullable', 'string', 'max:80'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'lines.*.finish_dia' => ['nullable', 'string', 'max:24'],
            'lines.*.machine_dia' => ['nullable', 'string', 'max:24'], 'lines.*.gauge' => ['nullable', 'string', 'max:24'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'plan_date.required' => __('knitting.sk.v.date'), 'lines.*.qty.required' => __('knitting.sk.v.qty'), 'lines.*.qty.gt' => __('knitting.sk.v.qty'),
            'lines.*.uom.required' => __('knitting.sk.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.sk.v.no_lines')]);
        }
        $row = DB::transaction(function () use ($row, $kind, $data, $lines): KnitSamplePlan {
            $head = ['plan_date' => $data['plan_date'], 'buyer_id' => $data['buyer_id'] ?? null, 'store_id' => $data['store_id'] ?? null, 'req_type' => $data['req_type'] ?? null,
                'remarks' => $data['remarks'] ?? null];
            $row ??= KnitSamplePlan::query()->create($head + [
                'kind' => $kind, 'plan_no' => KnitProduction::nextNumber(self::PREFIX[$kind], 'knit_sample_plans', 'plan_no', $data['plan_date']), 'status' => 'DRAFT',
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitSamplePlanLine::query()->where('plan_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                // An order names its buyer and style.
                if (! empty($l['order_id'])) {
                    $order = Order::query()->find($l['order_id']);
                    $l['buyer_id'] = ($l['buyer_id'] ?? null) ?: $order?->buyer_id;
                    $l['style_id'] = ($l['style_id'] ?? null) ?: $order?->style_id;
                }
                KnitSamplePlanLine::query()->create(['company_id' => $row->company_id, 'plan_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom'])]
                    + collect($l)->only(['buyer_id', 'style_id', 'order_id', 'style_text', 'fabric_type_id', 'colour_id', 'colour_name', 'yarn_details', 'composition_id',
                        'yarn_count_id', 'colour_group', 'brand', 'gsm', 'finish_dia', 'machine_dia', 'gauge', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** Submit Requisition: the plan becomes the Yarn Store's requisition. Cancel withdraws the plan. */
    public function transition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($kind, $no);
        $status = $this->status($row);
        $ok = match ($action) {
            'submit' => $status === 'DRAFT',
            'cancel' => in_array($status, ['DRAFT', 'REQ_SUBMITTED'], true),
        };
        if (! $ok) {
            throw ValidationException::withMessages(['status' => __('knitting.sk.v.cannot', ['no' => $row->plan_no, 'status' => __("knitting.sk.status.{$status}"), 'action' => __("knitting.sk.action.{$action}")])]);
        }
        if ($action === 'cancel') {
            $row->update(['status' => 'CANCELLED']);

            return ApiResponse::ok($this->register(collect([$row->refresh()]))[0]);
        }
        $lines = KnitSamplePlanLine::query()->where('plan_id', $row->id)->orderBy('sort_order')->get();
        $errors = [];
        if (! $row->store_id) {
            $errors['store_id'] = __('knitting.sk.v.store');
        }
        if (! $row->req_type) {
            $errors['req_type'] = __('knitting.sk.v.req_type');
        }
        foreach ($lines as $i => $l) {
            if (! $l->composition_id && ! $l->yarn_count_id) {
                $errors["lines.{$i}.composition_id"] = __('knitting.sk.v.registered', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($row, $lines): void {
            $req = YarnIssueReq::query()->create([
                'req_no' => YarnStore::nextNumber('YIR', 'knit_yarn_issue_reqs', 'req_no', $row->plan_date->toDateString()), 'req_date' => $row->plan_date, 'req_type' => $row->req_type,
                'store_id' => $row->store_id, 'buyer_id' => $row->buyer_id, 'program_no' => $row->plan_no, 'remarks' => $row->remarks, 'status' => 'OPEN',
                'created_by' => $this->caller()->auditUserId(),
            ]);
            foreach ($lines as $i => $l) {
                YarnIssueReqLine::query()->create([
                    'company_id' => $row->company_id, 'req_id' => $req->id, 'sort_order' => $i + 1, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id,
                    'colour' => $l->colour_group, 'brand' => $l->brand, 'uom' => $l->uom, 'req_qty' => $l->qty, 'remarks' => $l->yarn_details,
                ]);
            }
            $row->update(['status' => 'REQ_SUBMITTED', 'issue_req_id' => $req->id]);
        });

        return ApiResponse::ok($this->register(collect([$row->refresh()]))[0]);
    }

    private function find(string $kind, string $no): KnitSamplePlan
    {
        return KnitSamplePlan::query()->where('kind', $kind)->where('plan_no', $no)->firstOrFail();
    }

    /** What the plan has come to: once submitted, Issued and Closed are the requisition's. */
    private function status(KnitSamplePlan $p, ?YarnIssueReq $req = null): string
    {
        if ($p->status !== 'REQ_SUBMITTED') {
            return $p->status;
        }
        $req ??= $p->issue_req_id ? YarnIssueReq::query()->find($p->issue_req_id) : null;

        return match ($req?->status) {
            'PARTLY_ISSUED', 'FULLY_ISSUED' => 'ISSUED',
            'CLOSED' => 'CLOSED',
            default => 'REQ_SUBMITTED',
        };
    }

    /** @return list<array<string, mixed>> */
    private function register(Collection $rows): array
    {
        $lines = KnitSamplePlanLine::query()->whereIn('plan_id', $rows->pluck('id'))->get()->groupBy('plan_id');
        $reqs = YarnIssueReq::query()->whereIn('id', $rows->pluck('issue_req_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->filter())->pluck('name', 'id');

        return $rows->map(fn (KnitSamplePlan $p) => [
            'plan_no' => $p->plan_no, 'plan_date' => $p->plan_date->toDateString(), 'buyer_id' => $p->buyer_id, 'buyer' => $buyers[$p->buyer_id] ?? null,
            'store_id' => $p->store_id, 'store' => $stores[$p->store_id] ?? null, 'lines' => ($lines[$p->id] ?? collect())->count(),
            'qty' => round((float) ($lines[$p->id] ?? collect())->sum('qty'), 3), 'req_no' => $reqs[$p->issue_req_id]->req_no ?? null, 'remarks' => $p->remarks,
            'status' => $this->status($p, $reqs[$p->issue_req_id] ?? null),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitSamplePlan $p): array
    {
        $names = YarnProcurement::names();
        $lines = KnitSamplePlanLine::query()->where('plan_id', $p->id)->orderBy('sort_order')->get();
        $buyers = Buyer::query()->whereIn('id', $lines->pluck('buyer_id')->push($p->buyer_id)->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $styles = DB::table('merch_styles')->whereIn('id', $lines->pluck('style_id')->filter())->pluck('style_ref', 'id');
        $orders = Order::query()->whereIn('id', $lines->pluck('order_id')->filter())->get()->keyBy('id');
        $types = DB::table('fabric_types')->whereIn('id', $lines->pluck('fabric_type_id')->filter())->pluck('code', 'id');
        $colours = DB::table('colours')->whereIn('id', $lines->pluck('colour_id')->filter())->pluck('name', 'id');

        return array_merge($this->register(collect([$p]))[0], [
            'id' => $p->id, 'kind' => $p->kind, 'req_type' => $p->req_type, 'company' => DB::table('companies')->where('id', $p->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitSamplePlanLine $l) => collect($l->only(['buyer_id', 'style_id', 'order_id', 'style_text', 'fabric_type_id', 'colour_id', 'colour_name',
                'yarn_details', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'qty', 'uom', 'gsm', 'finish_dia', 'machine_dia', 'gauge', 'remarks']))->all() + [
                    'buyer' => $buyers[$l->buyer_id] ?? null, 'style' => $styles[$l->style_id] ?? null,
                    'order' => isset($orders[$l->order_id]) ? KnitProduction::orderLabel($orders[$l->order_id]) : null,
                    'fabric_type' => $types[$l->fabric_type_id] ?? null, 'colour' => $colours[$l->colour_id] ?? null,
                    'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')) ?: null,
                ])->values()->all(),
        ]);
    }
}
