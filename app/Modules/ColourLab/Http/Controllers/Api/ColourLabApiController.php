<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Http\Controllers\Api;

use App\Modules\ColourLab\Models\LabCertRegister;
use App\Modules\ColourLab\Models\LabFabricTest;
use App\Modules\ColourLab\Models\LabTrial;
use App\Modules\ColourLab\Models\LabTrialLine;
use App\Modules\ColourLab\Support\ColourLab;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Colour Lab → Operations (AnnotechERP 16/01), the two screens with lines or
 * a workflow: Chemical Trial Workbench (a trial's dyes and chemicals, the
 * dose and cost claimed against found, each line's result; Start, Complete,
 * Cancel) and In-House Fabric Testing (a lot's dia, GSM, shrinkage,
 * spirality, stitch length and fastness; Submit, then Approve or Reject).
 * The certificate screens are screen-kit resources.
 */
class ColourLabApiController extends ApiController
{
    /** Everything the lab's forms pick from. */
    public function options(): JsonResponse
    {
        $this->allow('colourlab.view');
        $company = ColourLab::company();
        $pairs = fn (array $map) => collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
        $buyers = Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'name', 'short_name', 'code']);

        return ApiResponse::ok([
            'floors' => $pairs(ColourLab::floors()), 'users' => $pairs(ColourLab::users()), 'cert_types' => $pairs(ColourLab::certTypes(true)),
            'equipment' => $pairs(ColourLab::equipment(true)), 'certificates' => $pairs(ColourLab::certificates(true)),
            'departments' => DB::table('item_departments')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'countries' => DB::table('origin_countries')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values()->all(),
            'brands' => DB::table('items')->where('company_id', $company)->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand')->values()->all(),
            'buyers' => $buyers->map(fn (Buyer $b) => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')])->values()->all(),
            'orders' => Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('created_at')->limit(500)->get(['id', 'order_no', 'style_no', 'work_style_no', 'buyer_id'])
                ->map(fn (Order $o) => ['value' => $o->id, 'label' => trim(($o->work_style_no ?: $o->style_no).' · '.$o->order_no, ' ·'), 'buyer_id' => $o->buyer_id, 'style' => $o->work_style_no ?: $o->style_no])->values()->all(),
            'fabric_types' => DB::table('fabric_types')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => "{$f->code} — {$f->name}"])->values()->all(),
            'compositions' => DB::table('fiber_compositions')->where('company_id', $company)->where('active', true)->orderBy('description')->get(['id', 'description'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            // Filed documents (Company Management): what Upload Doc's 📎 picks from.
            'documents' => DB::table('company_documents')->where('company_id', $company)->orderBy('title')->get(['id', 'title', 'document_no', 'document_type', 'expiry_date'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => trim($d->title.($d->document_no ? " · {$d->document_no}" : '')), 'type' => $d->document_type, 'expiry' => $d->expiry_date])->values()->all(),
            'units' => LabTrialLine::UNITS, 'results' => LabTrialLine::RESULTS, 'trial_statuses' => LabTrial::STATUSES, 'test_statuses' => LabFabricTest::STATUSES,
            'drying' => LabFabricTest::DRYING, 'summaries' => LabFabricTest::SUMMARIES, 'grades' => LabFabricTest::GRADES, 'actions' => LabCertRegister::ACTIONS,
        ]);
    }

    /** Dyes and chemicals for a trial line: a department's items by code or name, with the brand, origin and vendor they carry. */
    public function items(Request $request): JsonResponse
    {
        $this->allow('colourlab.view');
        $f = $request->validate(['department' => ['nullable', 'string', 'max:40'], 'q' => ['nullable', 'string', 'max:80']]);
        $company = ColourLab::company();
        $countries = DB::table('origin_countries')->where('company_id', $company)->get(['id', 'name']);
        $rows = DB::table('items')->where('company_id', $company)->where('active', true)
            ->when($f['department'] ?? null, fn ($q, $d) => $q->where('item_department_id', $d))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'ilike', '%'.addcslashes($t, '%_\\').'%')->orWhere('item_code', 'ilike', '%'.addcslashes($t, '%_\\').'%')))
            ->orderBy('name')->limit(60)->get(['id', 'item_code', 'name', 'brand', 'origin', 'default_vendor_id', 'item_department_id']);

        return ApiResponse::ok($rows->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->name} ({$i->item_code})", 'brand' => $i->brand, 'department_id' => $i->item_department_id,
            'supplier_id' => $i->default_vendor_id, 'origin_country_id' => $i->origin ? $countries->first(fn ($c) => strcasecmp($c->name, $i->origin) === 0)?->id : null])->values()->all());
    }

    // ── Chemical Trial Workbench ──────────────────────────────────────────

    public function trials(Request $request): JsonResponse
    {
        $this->allow('colourlab.view');
        $f = $request->validate(['ref' => ['nullable', 'string', 'max:40'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'status' => ['nullable', Rule::in(LabTrial::STATUSES)]]);
        $rows = LabTrial::query()->when($f['ref'] ?? null, fn ($q, $v) => $q->where('trial_no', 'ilike', '%'.addcslashes($v, '%_\\').'%'))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('trial_date', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('trial_date', '<=', $d))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderByDesc('trial_date')->orderByDesc('trial_no')->get();
        $lines = LabTrialLine::query()->whereIn('trial_id', $rows->pluck('id'))->get()->groupBy('trial_id');
        $floors = ColourLab::floors();
        $users = ColourLab::userNames();

        return ApiResponse::ok($rows->map(fn (LabTrial $t) => ['trial_no' => $t->trial_no, 'lab' => $floors[$t->lab_floor_id] ?? null, 'trial_date' => $t->trial_date->toDateString(),
            'trial_by' => $users[$t->trial_by] ?? null, 'status' => $t->status, 'lines' => ($lines[$t->id] ?? collect())->count(),
            'passed' => ($lines[$t->id] ?? collect())->where('result', 'PASS')->count(), 'failed' => ($lines[$t->id] ?? collect())->where('result', 'FAIL')->count()])->values()->all());
    }

    public function showTrial(string $no): JsonResponse
    {
        $this->allow('colourlab.view');

        return ApiResponse::ok($this->presentTrial($this->trial($no)));
    }

    /** Save a trial: the head and every line. Only an open trial (Pending or In Progress) changes. */
    public function saveTrial(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('colourlab.manage');
        $row = $no ? $this->trial($no) : null;
        if ($row && ! in_array($row->status, LabTrial::OPEN, true)) {
            throw ValidationException::withMessages(['status' => __('colourlab.v.trial_closed', ['no' => $row->trial_no, 'status' => __("colourlab.trial_status.{$row->status}")])]);
        }
        $company = ColourLab::company();
        $data = $request->validate([
            'lab_floor_id' => ['required', Rule::exists('floors', 'id')->where('company_id', $company)],
            'trial_date' => ['required', 'date'],
            'trial_by' => ['required', Rule::in(array_keys(ColourLab::users()))],
            'lines' => ['required', 'array', 'min:1', 'max:60'],
            'lines.*.item_department_id' => ['nullable', Rule::exists('item_departments', 'id')->where('company_id', $company)],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $company)],
            'lines.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'lines.*.brand' => ['nullable', 'string', 'max:64'],
            'lines.*.origin_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'lines.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.claim_dose' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'lines.*.dose_unit' => ['nullable', Rule::in(LabTrialLine::UNITS)],
            'lines.*.claim_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.claim_ratio' => ['nullable', 'regex:/^1:\d+(\.\d+)?$/'],
            'lines.*.cert_type_id' => ['nullable', Rule::exists('lab_cert_types', 'id')->where('company_id', $company)],
            'lines.*.actual_dose' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'lines.*.actual_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.actual_ratio' => ['nullable', 'regex:/^1:\d+(\.\d+)?$/'],
            'lines.*.result' => ['nullable', Rule::in(LabTrialLine::RESULTS)],
            'lines.*.comments' => ['nullable', 'string', 'max:255'],
            'lines.*.sample_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.required_by' => ['nullable', 'date'],
        ], $this->lineMessages('lines', [
            'item_id.required' => 'line_item', 'claim_ratio.regex' => 'line_ratio', 'actual_ratio.regex' => 'line_ratio',
        ]) + [
            'lab_floor_id.required' => __('colourlab.v.lab'), 'trial_date.required' => __('colourlab.v.trial_date'), 'trial_by.required' => __('colourlab.v.trial_by'),
            'lines.required' => __('colourlab.v.trial_lines'), 'lines.min' => __('colourlab.v.trial_lines'),
        ]);
        ksort($data['lines']);
        $row = DB::transaction(function () use ($data, $row): LabTrial {
            $head = ['lab_floor_id' => $data['lab_floor_id'], 'trial_date' => $data['trial_date'], 'trial_by' => $data['trial_by']];
            if ($row) {
                $row->update($head);
                LabTrialLine::query()->where('trial_id', $row->id)->delete();
            } else {
                $row = LabTrial::query()->create($head + ['trial_no' => ColourLab::nextNumber('TRL', 'lab_trials', 'trial_no', $data['trial_date']), 'status' => 'PENDING', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                LabTrialLine::query()->create(['trial_id' => $row->id, 'sort_order' => ($i + 1) * 10, 'dose_unit' => $l['dose_unit'] ?? '%', 'result' => $l['result'] ?? 'PENDING']
                    + array_intersect_key($l, array_flip(['item_department_id', 'item_id', 'supplier_id', 'brand', 'origin_country_id', 'price', 'claim_dose', 'claim_cost', 'claim_ratio',
                        'cert_type_id', 'actual_dose', 'actual_cost', 'actual_ratio', 'comments', 'sample_qty', 'required_by'])));
            }

            return $row;
        });

        return ApiResponse::ok($this->presentTrial($row->refresh()), [], $no ? 200 : 201);
    }

    /** The trial's steps: Start (Pending → In Progress), Complete (every line given its result), Cancel. */
    public function trialTransition(string $no, string $action): JsonResponse
    {
        $this->allow('colourlab.manage');
        $row = $this->trial($no);
        $from = ['start' => ['PENDING'], 'complete' => ['IN_PROGRESS'], 'cancel' => LabTrial::OPEN][$action];
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('colourlab.v.trial_cannot', ['no' => $row->trial_no, 'status' => __("colourlab.trial_status.{$row->status}"),
                'action' => __("colourlab.trial_action.{$action}")])]);
        }
        if ($action === 'complete') {
            $open = LabTrialLine::query()->where('trial_id', $row->id)->orderBy('sort_order')->get()->search(fn ($l) => $l->result === 'PENDING');
            if ($open !== false) {
                throw ValidationException::withMessages(['lines' => __('colourlab.v.line_pending', ['n' => $open + 1])]);
            }
        }
        $row->update(match ($action) {
            'start' => ['status' => 'IN_PROGRESS', 'started_at' => now()],
            'complete' => ['status' => 'COMPLETED', 'completed_at' => now()],
            'cancel' => ['status' => 'CANCELLED'],
        });

        return ApiResponse::ok($this->presentTrial($row->refresh()));
    }

    public function deleteTrial(string $no): JsonResponse
    {
        $this->allow('colourlab.manage');
        $row = $this->trial($no);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => __('colourlab.v.trial_cannot', ['no' => $row->trial_no, 'status' => __("colourlab.trial_status.{$row->status}"),
                'action' => __('colourlab.trial_action.delete')])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    // ── In-House Fabric Testing ───────────────────────────────────────────

    public function fabricTests(Request $request): JsonResponse
    {
        $this->allow('colourlab.view');
        $f = $request->validate(['status' => ['nullable', Rule::in(LabFabricTest::STATUSES)], 'buyer' => ['nullable', 'string', 'max:40']]);
        $rows = LabFabricTest::query()->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($f['buyer'] ?? null, fn ($q, $b) => $q->where('buyer_id', $b))
            ->orderByDesc('test_date')->orderByDesc('test_no')->get();

        return ApiResponse::ok($this->presentTests($rows));
    }

    public function showFabricTest(string $no): JsonResponse
    {
        $this->allow('colourlab.view');

        return ApiResponse::ok($this->presentTests(collect([$this->test($no)]))[0]);
    }

    /** Save a test. A draft or a rejected test changes; a submitted or approved one is decided. */
    public function saveFabricTest(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('colourlab.manage');
        $row = $no ? $this->test($no) : null;
        if ($row && ! in_array($row->status, ['DRAFT', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['status' => __('colourlab.v.test_decided', ['no' => $row->test_no, 'status' => __("colourlab.test_status.{$row->status}")])]);
        }
        $company = ColourLab::company();
        $num = fn (int $max) => ['nullable', 'numeric', 'min:0', "max:{$max}"];
        $grade = ['nullable', Rule::in(LabFabricTest::GRADES)];
        $data = $request->validate([
            'test_date' => ['required', 'date'],
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'style_no' => ['nullable', 'string', 'max:64'],
            'fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'colour' => ['nullable', 'string', 'max:80'], 'lot_no' => ['nullable', 'string', 'max:64'], 'qty' => $num(99999999),
            'drying' => ['nullable', Rule::in(LabFabricTest::DRYING)], 'summary' => ['nullable', Rule::in(LabFabricTest::SUMMARIES)],
            'std_dia' => $num(999), 'act_dia' => $num(999), 'std_gsm' => $num(9999), 'act_gsm' => $num(9999), 'coil_no' => ['nullable', 'string', 'max:32'],
            'shrink_l' => ['nullable', 'numeric', 'min:-99', 'max:99'], 'shrink_w' => ['nullable', 'numeric', 'min:-99', 'max:99'], 'spirality' => $num(99), 'stitch_length' => $num(99),
            'wash' => $grade, 'rub_dry' => $grade, 'rub_wet' => $grade, 'light' => $grade, 'perspiration' => $grade,
            'lab_floor_id' => ['nullable', Rule::exists('floors', 'id')->where('company_id', $company)], 'rh' => $num(100), 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['test_date.required' => __('colourlab.v.test_date'), 'buyer_id.required' => __('colourlab.v.customer')]);
        if (! empty($data['order_id']) && Order::query()->whereKey($data['order_id'])->value('buyer_id') !== $data['buyer_id']) {
            throw ValidationException::withMessages(['order_id' => __('colourlab.v.order_buyer')]);
        }
        $row = DB::transaction(function () use ($data, $row): LabFabricTest {
            if ($row) {
                $row->update($data);

                return $row;
            }

            return LabFabricTest::query()->create($data + ['test_no' => ColourLab::nextNumber('LFT', 'lab_fabric_tests', 'test_no', $data['test_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->presentTests(collect([$row->refresh()]))[0], [], $no ? 200 : 201);
    }

    /** Submit (a draft or a rejected test), then Approve or Reject (who may approve, a submitted one). */
    public function fabricTestTransition(Request $request, string $no, string $action): JsonResponse
    {
        $this->allow($action === 'submit' ? 'colourlab.manage' : 'colourlab.approve');
        $row = $this->test($no);
        $from = $action === 'submit' ? ['DRAFT', 'REJECTED'] : ['SUBMITTED'];
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('colourlab.v.test_cannot', ['no' => $row->test_no, 'status' => __("colourlab.test_status.{$row->status}"),
                'action' => __("colourlab.test_action.{$action}")])]);
        }
        if ($action === 'submit' && ! $row->summary) {
            throw ValidationException::withMessages(['summary' => __('colourlab.v.summary_first')]);
        }
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
        $row->update(match ($action) {
            'submit' => ['status' => 'SUBMITTED', 'decision_note' => null, 'decided_at' => null, 'decided_by' => null],
            'approve' => ['status' => 'APPROVED', 'decision_note' => $note, 'decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()],
            'reject' => ['status' => 'REJECTED', 'decision_note' => $note, 'decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()],
        });

        return ApiResponse::ok($this->presentTests(collect([$row->refresh()]))[0]);
    }

    public function deleteFabricTest(string $no): JsonResponse
    {
        $this->allow('colourlab.manage');
        $row = $this->test($no);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('colourlab.v.test_cannot', ['no' => $row->test_no, 'status' => __("colourlab.test_status.{$row->status}"),
                'action' => __('colourlab.test_action.delete')])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function trial(string $no): LabTrial
    {
        return LabTrial::query()->where('trial_no', $no)->firstOrFail();
    }

    private function test(string $no): LabFabricTest
    {
        return LabFabricTest::query()->where('test_no', $no)->firstOrFail();
    }

    /** "Line 2: Pick the dye or chemical." for each wildcard rule. @param array<string, string> $map */
    private function lineMessages(string $prefix, array $map): array
    {
        $out = [];
        foreach ($map as $rule => $key) {
            $out["{$prefix}.*.{$rule}"] = __("colourlab.v.{$key}");
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function presentTrial(LabTrial $t): array
    {
        $lines = LabTrialLine::query()->where('trial_id', $t->id)->orderBy('sort_order')->get();
        $items = DB::table('items')->whereIn('id', $lines->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');
        $departments = DB::table('item_departments')->whereIn('id', $lines->pluck('item_department_id')->filter())->pluck('name', 'id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $lines->pluck('supplier_id')->filter())->pluck('name', 'id');
        $countries = DB::table('origin_countries')->whereIn('id', $lines->pluck('origin_country_id')->filter())->pluck('name', 'id');
        $types = ColourLab::certTypes();

        return [
            'trial_no' => $t->trial_no, 'lab_floor_id' => $t->lab_floor_id, 'lab' => ColourLab::floors()[$t->lab_floor_id] ?? null, 'trial_date' => $t->trial_date->toDateString(),
            'trial_by' => $t->trial_by, 'trial_by_name' => ColourLab::userNames()[$t->trial_by] ?? null, 'status' => $t->status,
            'started_at' => $t->started_at?->toDateTimeString(), 'completed_at' => $t->completed_at?->toDateTimeString(),
            'lines' => $lines->map(fn (LabTrialLine $l) => [
                'item_department_id' => $l->item_department_id, 'department' => $departments[$l->item_department_id] ?? null, 'item_id' => $l->item_id,
                'item' => isset($items[$l->item_id]) ? "{$items[$l->item_id]->name} ({$items[$l->item_id]->item_code})" : null, 'supplier_id' => $l->supplier_id,
                'supplier' => $suppliers[$l->supplier_id] ?? null, 'brand' => $l->brand, 'origin_country_id' => $l->origin_country_id, 'origin' => $countries[$l->origin_country_id] ?? null,
                'price' => $l->price, 'claim_dose' => $l->claim_dose, 'dose_unit' => $l->dose_unit, 'claim_cost' => $l->claim_cost, 'claim_ratio' => $l->claim_ratio,
                'cert_type_id' => $l->cert_type_id, 'certification' => $types[$l->cert_type_id] ?? null, 'actual_dose' => $l->actual_dose, 'actual_cost' => $l->actual_cost,
                'actual_ratio' => $l->actual_ratio, 'result' => $l->result, 'comments' => $l->comments, 'sample_qty' => $l->sample_qty, 'required_by' => $l->required_by?->toDateString(),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function presentTests(Collection $rows): array
    {
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id'))->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->pluck('order_no', 'id');
        $types = DB::table('fabric_types')->whereIn('id', $rows->pluck('fabric_type_id')->filter())->pluck('name', 'id');
        $comps = DB::table('fiber_compositions')->whereIn('id', $rows->pluck('composition_id')->filter())->pluck('description', 'id');
        $users = ColourLab::userNames();

        return $rows->map(fn (LabFabricTest $t) => [
            'test_no' => $t->test_no, 'test_date' => $t->test_date->toDateString(), 'buyer_id' => $t->buyer_id, 'customer' => $buyers[$t->buyer_id] ?? null, 'order_id' => $t->order_id,
            'order_no' => $orders[$t->order_id] ?? null, 'style_no' => $t->style_no, 'fabric_type_id' => $t->fabric_type_id, 'fabric_type' => $types[$t->fabric_type_id] ?? null,
            'composition_id' => $t->composition_id, 'composition' => $comps[$t->composition_id] ?? null, 'colour' => $t->colour, 'lot_no' => $t->lot_no, 'qty' => $t->qty,
            'drying' => $t->drying, 'summary' => $t->summary, 'std_dia' => $t->std_dia, 'act_dia' => $t->act_dia, 'std_gsm' => $t->std_gsm, 'act_gsm' => $t->act_gsm,
            'coil_no' => $t->coil_no, 'shrink_l' => $t->shrink_l, 'shrink_w' => $t->shrink_w, 'spirality' => $t->spirality, 'stitch_length' => $t->stitch_length, 'wash' => $t->wash,
            'rub_dry' => $t->rub_dry, 'rub_wet' => $t->rub_wet, 'light' => $t->light, 'perspiration' => $t->perspiration, 'rh' => $t->rh,
            'lab_floor_id' => $t->lab_floor_id, 'lab' => ColourLab::floors()[$t->lab_floor_id] ?? null, 'remarks' => $t->remarks, 'status' => $t->status, 'decision_note' => $t->decision_note,
            'decided_at' => $t->decided_at?->toDateTimeString(), 'decided_by' => $users[$t->decided_by] ?? null,
        ])->values()->all();
    }
}
