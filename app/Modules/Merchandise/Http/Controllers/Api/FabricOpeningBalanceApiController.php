<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricOpeningBalance;
use App\Modules\Merchandise\Models\FabricOpeningBalanceEvent;
use App\Modules\Merchandise\Models\FabricOpeningBalanceLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\PurchasedFabricLedger;
use App\Modules\Merchandise\Support\StockPeriods;
use App\Modules\Tenancy\Models\Company;
use App\Modules\TradeExport\Models\TxExchangeRate;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trims & Accessories → Purchased Fabric Opening Balance (AnnotechERP 06/06),
 * as Brickland works it: opening stock, not a purchase — a header (company,
 * receiving store, date, the order it is for, currency and rates, source and
 * reference, an attachment) and one fabric batch per row, its cost carried
 * into BDT. Draft → Submitted → Approved → Posted → Locked, every step kept
 * for the Approval History; only a draft is edited or deleted. Posting puts
 * every batch on the purchased-fabric stock ledger at its OB cost in BDT;
 * a locked balance can no longer be unposted. A batch appears once per
 * opening balance, and once per store across all of them.
 */
class FabricOpeningBalanceApiController extends ApiController
{
    /** action => [permission, from, to] */
    private const FLOW = [
        'submit' => ['merchandise.accessories.manage', 'DRAFT', 'SUBMITTED'],
        'approve' => ['merchandise.accessories.approve', 'SUBMITTED', 'APPROVED'],
        'reject' => ['merchandise.accessories.approve', 'SUBMITTED', 'DRAFT'],
        'post' => ['merchandise.accessories.manage', 'APPROVED', 'POSTED'],
        'unpost' => ['merchandise.accessories.manage', 'POSTED', 'APPROVED'],
        'lock' => ['merchandise.accessories.approve', 'POSTED', 'LOCKED'],
    ];

    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $company = $this->caller()->companyId;
        $name = Company::query()->whereKey($company)->value('name');

        return ApiResponse::ok([
            'companies' => $name ? [['value' => $company, 'label' => $name]] : [],
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'orders' => Order::query()->orderByDesc('order_no')->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])
                ->map(fn ($o): array => ['value' => $o->id, 'label' => "{$o->order_no} · ".($o->job_no ?: $o->style_no).' ('.($o->work_style_no ?: $o->style_no).')'])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name])->values()->all(),
            'fabric_types' => FabricType::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
            'compositions' => FiberComposition::query()->where('active', true)->orderBy('description')->get(['id', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            'balances' => FabricOpeningBalance::query()->orderByDesc('ob_no')->get(['ob_no'])->map(fn ($b): array => ['value' => $b->ob_no, 'label' => $b->ob_no])->values()->all(),
            'usd_bdt' => $this->usdRate(now()->toDateString()),
            'currencies' => ['BDT', 'USD', 'EUR', 'CNY', 'INR'], 'sources' => FabricOpeningBalance::SOURCES, 'categories' => FabricOpeningBalance::CATEGORIES,
            'groups' => FabricOpeningBalance::GROUPS, 'dia_uoms' => FabricOpeningBalance::DIA_UOMS, 'dia_types' => FabricOpeningBalance::DIA_TYPES, 'uoms' => FabricOpeningBalance::UOMS,
            'statuses' => FabricOpeningBalance::STATUSES,
        ]);
    }

    /** Records: every opening balance with its store, order, source, rates and totals. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = FabricOpeningBalance::query()->orderByDesc('ob_date')->orderByDesc('ob_no')->get();
        $lines = FabricOpeningBalanceLine::query()->whereIn('ob_id', $rows->pluck('id'))->get(['ob_id', 'rolls', 'qty', 'cost_bdt'])->groupBy('ob_id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $companies = Company::query()->whereIn('id', $rows->pluck('unit_company_id'))->pluck('name', 'id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get(['id', 'order_no', 'job_no', 'style_no'])->keyBy('id');

        return ApiResponse::ok($rows->map(function (FabricOpeningBalance $b) use ($lines, $stores, $companies, $orders): array {
            $mine = $lines[$b->id] ?? collect();
            $o = $b->order_id ? ($orders[$b->order_id] ?? null) : null;

            return [
                'ob_no' => $b->ob_no, 'ob_date' => $b->ob_date->toDateString(), 'company' => $companies[$b->unit_company_id] ?? null, 'store' => $stores[$b->store_id] ?? null,
                'order' => $o ? "{$o->order_no} · ".($o->job_no ?: $o->style_no) : null, 'source' => $b->source, 'reference_no' => $b->reference_no, 'currency' => $b->currency,
                'conv_rate' => $b->conv_rate, 'usd_bdt' => $b->usd_bdt, 'items' => $mine->count(), 'rolls' => (int) $mine->sum('rolls'), 'total_qty' => round((float) $mine->sum('qty'), 4),
                'total_cost' => round((float) $mine->sum('cost_bdt'), 2), 'status' => $b->status, 'editable' => $b->isEditable(),
            ];
        })->values()->all());
    }

    public function show(string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($balance)));
    }

    /** Approval History: every workflow step the opening balance has taken. */
    public function history(string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $row = $this->find($balance);
        $events = FabricOpeningBalanceEvent::query()->where('ob_id', $row->id)->orderBy('acted_at')->orderBy('created_at')->get();
        $users = User::query()->whereIn('id', $events->pluck('user_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok([
            'ob_no' => $row->ob_no, 'status' => $row->status,
            'steps' => $events->map(fn ($e): array => [
                'action' => $e->action, 'from' => $e->from_status, 'to' => $e->to_status, 'by' => $e->user_id ? ($users[$e->user_id] ?? null) : null,
                'at' => $e->acted_at->toIso8601String(), 'remarks' => $e->remarks,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): FabricOpeningBalance {
            $row = FabricOpeningBalance::query()->create($this->header($data) + ['ob_no' => $this->nextNumber($data['ob_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($row, $data['lines']);
            $this->log($row, 'CREATE', null, 'DRAFT');

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($balance);
        $this->assertEditable($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->writeLines($row, $data['lines']);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($balance);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Submit, approve, reject, post, unpost or lock: each only from the step it follows. */
    public function transition(Request $request, string $balance, string $action): JsonResponse
    {
        [$permission, $from, $to] = self::FLOW[$action];
        $this->allow($permission);
        $row = $this->find($balance);
        StockPeriods::assertOpen('purchased-fabric', $row->ob_date);
        if ($row->status !== $from) {
            throw ValidationException::withMessages(['status' => __('merchandise.fob.cannot', ['action' => __("merchandise.master.fob.do.{$action}"), 'status' => __("merchandise.master.fob.status.{$row->status}")])]);
        }
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:500']])['remarks'] ?? null;
        DB::transaction(function () use ($row, $action, $from, $to, $remarks): void {
            if ($action === 'post') {
                $this->post($row);
            }
            if ($action === 'unpost') {
                PurchasedFabricLedger::query()->where('source', 'OB')->where('source_id', $row->id)->delete();
            }
            $row->update(['status' => $to]);
            $this->log($row, strtoupper($action), $from, $to, $remarks);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** The attachment: the count sheet or legacy stock report the figures were keyed from. */
    public function attach(Request $request, StoreFileAttachment $files, string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($balance);
        $this->assertEditable($row);
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $file = $files->handle($request->file('file'), 'merch_pfab_ob', $row->id, $this->caller()->auditUserId());
        $row->update(['attachment_file_id' => $file->id, 'attachment' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255)]);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function find(string $balance): FabricOpeningBalance
    {
        return FabricOpeningBalance::query()->where('ob_no', $balance)->firstOrFail();
    }

    private function assertEditable(FabricOpeningBalance $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.fob.locked', ['status' => __("merchandise.master.fob.status.{$row->status}")])]);
        }
    }

    /** The USD rate in taka on a day: the latest exchange rate set on or before it. */
    private function usdRate(string $date): ?float
    {
        $rate = TxExchangeRate::query()->where('currency', 'USD')->where('effective_date', '<=', $date)->orderByDesc('effective_date')->value('rate');

        return $rate === null ? null : (float) $rate;
    }

    /** Every batch on the purchased-fabric stock ledger, into the receiving store at its cost in BDT. */
    private function post(FabricOpeningBalance $row): void
    {
        foreach (FabricOpeningBalanceLine::query()->where('ob_id', $row->id)->orderBy('sort_order')->get() as $l) {
            PurchasedFabricLedger::query()->create([
                'store_id' => $row->store_id, 'source' => 'OB', 'source_id' => $row->id, 'source_line_id' => $l->id, 'txn_date' => $row->ob_date->toDateString(),
                'batch_no' => $l->batch_no, 'fabric_item' => $l->fabric_item, 'description' => $l->description, 'colour_id' => $l->colour_id, 'fabric_type_id' => $l->fabric_type_id,
                'composition_id' => $l->composition_id, 'uom' => $l->uom, 'qty' => $l->qty, 'cost_bdt' => $l->cost_bdt,
            ]);
        }
    }

    private function log(FabricOpeningBalance $row, string $action, ?string $from, string $to, ?string $remarks = null): void
    {
        FabricOpeningBalanceEvent::query()->create(['ob_id' => $row->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'user_id' => $this->caller()->auditUserId(), 'remarks' => $remarks, 'acted_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FabricOpeningBalance $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $required = ['unit_company_id', 'store_id', 'ob_date', 'currency', 'source'];
        $data = $request->validate([
            'unit_company_id' => ['required', Rule::in([$company])],
            'store_id' => ['required', $in('stores')],
            'ob_date' => ['required', 'date'],
            'order_id' => ['nullable', $in('merch_orders')],
            'currency' => ['required', Rule::in(['BDT', 'USD', 'EUR', 'CNY', 'INR'])],
            'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'usd_bdt' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'source' => ['required', Rule::in(FabricOpeningBalance::SOURCES)],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:500'],
            'lines.*.category' => ['nullable', Rule::in(FabricOpeningBalance::CATEGORIES)],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.fabric_item' => ['nullable', 'string', 'max:120'],
            'lines.*.colour_id' => ['nullable', $in('colours')],
            'lines.*.fabric_type_id' => ['nullable', $in('fabric_types')],
            'lines.*.fabric_group' => ['nullable', Rule::in(FabricOpeningBalance::GROUPS)],
            'lines.*.composition_id' => ['nullable', $in('fiber_compositions')],
            'lines.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.dia_uom' => ['nullable', Rule::in(FabricOpeningBalance::DIA_UOMS)],
            'lines.*.dia_type' => ['nullable', Rule::in(FabricOpeningBalance::DIA_TYPES)],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'lines.*.roll_nos' => ['nullable', 'string', 'max:500'],
            'lines.*.uom' => ['nullable', Rule::in(FabricOpeningBalance::UOMS)],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], collect($required)->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.fob.f.{$f}")])])->all());

        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);
        // A closed month's documents are no longer saved (Month Close, T14–T19).
        StockPeriods::assertOpen('purchased-fabric', $data['ob_date'], 'ob_date');

        $errors = [];
        // Rates: 1 for USD; none for BDT; otherwise both the conversion and the USD rate.
        if ($data['currency'] !== 'BDT' && empty($data['usd_bdt'])) {
            $errors['usd_bdt'] = __('merchandise.fob.usd_required');
        }
        if (! in_array($data['currency'], ['BDT', 'USD'], true) && empty($data['conv_rate'])) {
            $errors['conv_rate'] = __('merchandise.fob.conv_required');
        }
        if ($data['lines'] === []) {
            $errors['lines'] = __('merchandise.fob.lines_required');
        }
        $batches = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            foreach (['category' => 'category', 'fabric_item' => 'fabric_item', 'fabric_group' => 'fabric_group', 'uom' => 'uom'] as $key => $label) {
                if (empty($l[$key]) || trim((string) $l[$key]) === '') {
                    $errors["lines.{$i}.{$key}"] = __('merchandise.fob.row_needed', ['n' => $n, 'field' => __("merchandise.master.fob.f.{$label}")]);
                }
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.fob.row_qty', ['n' => $n]);
            }
            $batch = mb_strtoupper(trim((string) ($l['batch_no'] ?? '')));
            if ($batch !== '') {
                if (isset($batches[$batch])) {
                    $errors["lines.{$i}.batch_no"] = __('merchandise.fob.batch_twice', ['n' => $n, 'batch' => $batch]);
                }
                $batches[$batch] = true;
            }
        }
        // Once per store across every opening balance.
        if ($batches !== [] && empty($errors)) {
            $others = FabricOpeningBalance::query()->where('store_id', $data['store_id'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->pluck('ob_no', 'id');
            $taken = FabricOpeningBalanceLine::query()->whereIn('ob_id', $others->keys())->whereIn(DB::raw('upper(batch_no)'), array_keys($batches))->get(['ob_id', 'batch_no']);
            foreach (array_values($data['lines']) as $i => $l) {
                $hit = $taken->first(fn ($t) => mb_strtoupper($t->batch_no) === mb_strtoupper(trim((string) ($l['batch_no'] ?? ''))));
                if ($hit) {
                    $errors["lines.{$i}.batch_no"] = __('merchandise.fob.batch_in_store', ['n' => $i + 1, 'batch' => $hit->batch_no, 'no' => $others[$hit->ob_id]]);
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;

        return [
            'unit_company_id' => $data['unit_company_id'], 'store_id' => $data['store_id'], 'ob_date' => $data['ob_date'], 'order_id' => $data['order_id'] ?? null,
            'currency' => $data['currency'], 'conv_rate' => match ($data['currency']) {
                'USD', 'BDT' => 1,
                default => (float) $data['conv_rate'],
            }, 'usd_bdt' => $data['currency'] === 'BDT' ? null : (float) $data['usd_bdt'],
            'source' => $data['source'], 'reference_no' => $blank('reference_no'), 'attachment' => $blank('attachment'), 'remarks' => $blank('remarks'),
        ];
    }

    /** Each batch's amount in the OB currency and its cost in BDT: × conversion to USD × the USD rate, or as is in BDT. */
    private function writeLines(FabricOpeningBalance $row, array $lines): void
    {
        FabricOpeningBalanceLine::query()->where('ob_id', $row->id)->delete();
        $toBdt = $row->currency === 'BDT' ? 1.0 : $row->conv_rate * (float) $row->usd_bdt;
        foreach (array_values($lines) as $i => $l) {
            $amount = round((float) $l['qty'] * (float) ($l['rate'] ?? 0), 2);
            $text = fn (string $k, int $max): ?string => isset($l[$k]) && trim((string) $l[$k]) !== '' ? mb_substr(trim((string) $l[$k]), 0, $max) : null;
            FabricOpeningBalanceLine::query()->create([
                'ob_id' => $row->id, 'category' => $l['category'], 'description' => $text('description', 200), 'fabric_item' => trim($l['fabric_item']),
                'colour_id' => $l['colour_id'] ?? null, 'fabric_type_id' => $l['fabric_type_id'] ?? null, 'fabric_group' => $l['fabric_group'], 'composition_id' => $l['composition_id'] ?? null,
                'gsm' => $l['gsm'] ?? null, 'dia' => $l['dia'] ?? null, 'dia_uom' => $l['dia_uom'] ?? null, 'dia_type' => $l['dia_type'] ?? null, 'batch_no' => $text('batch_no', 60),
                'rolls' => $l['rolls'] ?? null, 'roll_nos' => $text('roll_nos', 500), 'uom' => $l['uom'], 'qty' => round((float) $l['qty'], 4), 'rate' => round((float) ($l['rate'] ?? 0), 4),
                'amount' => $amount, 'cost_bdt' => round($amount * $toBdt, 2), 'sort_order' => $i + 1,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function present(FabricOpeningBalance $row): array
    {
        $lines = FabricOpeningBalanceLine::query()->where('ob_id', $row->id)->orderBy('sort_order')->get();

        return [
            'ob_no' => $row->ob_no, 'status' => $row->status, 'editable' => $row->isEditable(), 'unit_company_id' => $row->unit_company_id, 'store_id' => $row->store_id,
            'ob_date' => $row->ob_date->toDateString(), 'order_id' => $row->order_id, 'currency' => $row->currency, 'conv_rate' => $row->conv_rate, 'usd_bdt' => $row->usd_bdt,
            'source' => $row->source, 'reference_no' => $row->reference_no, 'attachment' => $row->attachment, 'attachment_file_id' => $row->attachment_file_id, 'remarks' => $row->remarks,
            'total_qty' => round((float) $lines->sum('qty'), 4), 'total_cost' => round((float) $lines->sum('cost_bdt'), 2),
            'posted' => PurchasedFabricLedger::query()->where('source', 'OB')->where('source_id', $row->id)->count(),
            'lines' => $lines->map(fn (FabricOpeningBalanceLine $l): array => $l->only([
                'category', 'description', 'fabric_item', 'colour_id', 'fabric_type_id', 'fabric_group', 'composition_id', 'gsm', 'dia', 'dia_uom', 'dia_type', 'batch_no',
                'rolls', 'roll_nos', 'uom', 'qty', 'rate', 'amount', 'cost_bdt',
            ]))->values()->all(),
        ];
    }

    /** PFOB-2026100001: the year and month, then the company's next opening balance that month; held to the end of the save. */
    private function nextNumber(string $date): string
    {
        $head = 'PFOB-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_pfab_obs:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) FabricOpeningBalance::query()->where('ob_no', 'like', $head.'%')->pluck('ob_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
