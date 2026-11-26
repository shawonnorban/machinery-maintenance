<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Merchandise\Models\FabricOpeningBalance;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\FinishedFabricOpeningBalance;
use App\Modules\Merchandise\Models\FinishedFabricOpeningBalanceLine;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Support\StockPeriods;
use App\Modules\Tenancy\Models\Company;
use App\Modules\TradeExport\Models\TxExchangeRate;
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
 * Trims & Accessories → Finished Fabric Opening Balance (AnnotechERP 06/06),
 * as Brickland works it: no workflow — a receiving store, the OB date, the
 * order it is for (if any), currency, conversion and the USD rate kept with
 * the document, and one roll batch per line (description, item, type and
 * group named). Saved, it stands; it can be corrected or removed. Each line's
 * cost is carried into BDT. A batch appears once per opening balance.
 */
class FinishedFabricObApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $stores = Store::query()->pluck('name', 'id');

        return ApiResponse::ok([
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'orders' => Order::query()->orderByDesc('order_no')->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])
                ->map(fn ($o): array => ['value' => $o->id, 'label' => "{$o->order_no} · ".($o->job_no ?: $o->style_no).' ('.($o->work_style_no ?: $o->style_no).')'])->values()->all(),
            'fabric_types' => FabricType::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
            'compositions' => FiberComposition::query()->where('active', true)->orderBy('description')->get(['id', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            // Existing opening balance: "01 Oct 2026 · Finished Fabric Store · FFOB-2026100001", as Brickland names them.
            'balances' => FinishedFabricOpeningBalance::query()->orderByDesc('ob_date')->orderByDesc('ob_no')->get(['ob_no', 'ob_date', 'store_id'])
                ->map(fn ($b): array => ['value' => $b->ob_no, 'label' => $b->ob_date->format('d M Y').' · '.($stores[$b->store_id] ?? '—').' · '.$b->ob_no])->values()->all(),
            'usd_bdt' => $this->usdRate(now()->toDateString()),
            'groups' => FabricOpeningBalance::GROUPS, 'dia_uoms' => FabricOpeningBalance::DIA_UOMS, 'dia_types' => FabricOpeningBalance::DIA_TYPES, 'uoms' => FabricOpeningBalance::UOMS,
        ]);
    }

    /** Records: every opening balance with its company, store, order, rates, item count and quantity. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = FinishedFabricOpeningBalance::query()->orderByDesc('ob_date')->orderByDesc('ob_no')->get();
        $lines = FinishedFabricOpeningBalanceLine::query()->whereIn('ob_id', $rows->pluck('id'))->get(['ob_id', 'qty', 'cost_bdt'])->groupBy('ob_id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get(['id', 'order_no', 'job_no', 'style_no'])->keyBy('id');
        $company = Company::query()->whereKey($this->caller()->companyId)->value('name');

        return ApiResponse::ok($rows->map(function (FinishedFabricOpeningBalance $b) use ($lines, $stores, $orders, $company): array {
            $mine = $lines[$b->id] ?? collect();
            $o = $b->order_id ? ($orders[$b->order_id] ?? null) : null;

            return [
                'ob_no' => $b->ob_no, 'ob_date' => $b->ob_date->toDateString(), 'company' => $company, 'store' => $stores[$b->store_id] ?? null,
                'order' => $o ? "{$o->order_no} · ".($o->job_no ?: $o->style_no) : null, 'currency' => $b->currency, 'conv_rate' => $b->conv_rate, 'usd_bdt' => $b->usd_bdt,
                'items' => $mine->count(), 'total_qty' => round((float) $mine->sum('qty'), 4), 'total_cost' => round((float) $mine->sum('cost_bdt'), 2),
            ];
        })->values()->all());
    }

    public function show(string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($balance)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request);
        $row = DB::transaction(function () use ($data): FinishedFabricOpeningBalance {
            $row = FinishedFabricOpeningBalance::query()->create($this->header($data) + ['ob_no' => $this->nextNumber($data['ob_date']), 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $balance): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($balance);
        $data = $this->validated($request);
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
        StockPeriods::assertOpen('finished-fabric', $row->ob_date);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    private function find(string $balance): FinishedFabricOpeningBalance
    {
        return FinishedFabricOpeningBalance::query()->where('ob_no', $balance)->firstOrFail();
    }

    private function usdRate(string $date): ?float
    {
        $rate = TxExchangeRate::query()->where('currency', 'USD')->where('effective_date', '<=', $date)->orderByDesc('effective_date')->value('rate');

        return $rate === null ? null : (float) $rate;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'store_id' => ['required', $in('stores')],
            'ob_date' => ['required', 'date'],
            'order_id' => ['nullable', $in('merch_orders')],
            'currency' => ['nullable', 'string', 'max:8'],
            'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'usd_bdt' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'lines' => ['present', 'array', 'max:500'],
            'lines.*.category' => ['nullable', 'string', 'max:60'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.fabric_item' => ['nullable', 'string', 'max:120'],
            'lines.*.colour' => ['nullable', 'string', 'max:80'],
            'lines.*.fabric_type_id' => ['nullable', $in('fabric_types')],
            'lines.*.fabric_group' => ['nullable', Rule::in(FabricOpeningBalance::GROUPS)],
            'lines.*.composition_id' => ['nullable', $in('fiber_compositions')],
            'lines.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.dia_uom' => ['nullable', Rule::in(FabricOpeningBalance::DIA_UOMS)],
            'lines.*.dia_type' => ['nullable', Rule::in(FabricOpeningBalance::DIA_TYPES)],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'lines.*.uom' => ['nullable', Rule::in(FabricOpeningBalance::UOMS)],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], collect(['store_id', 'ob_date'])->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.ffob.f.{$f}")])])->all());

        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);
        // A closed month's documents are no longer saved (Month Close, T14–T19).
        StockPeriods::assertOpen('finished-fabric', $data['ob_date'], 'ob_date');
        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.ffob.lines_required')]);
        }
        $errors = [];
        $batches = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            // Brickland's order: the first of these still empty is named.
            $need = collect(['description', 'fabric_item', 'fabric_type_id', 'fabric_group', 'uom'])->first(fn (string $k) => trim((string) ($l[$k] ?? '')) === '');
            if ($need !== null) {
                $errors["lines.{$i}.{$need}"] = __('merchandise.ffob.row_needed', ['n' => $n, 'field' => __("merchandise.master.ffob.f.{$need}")]);

                continue;
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.ffob.row_qty', ['n' => $n]);

                continue;
            }
            $batch = mb_strtoupper(trim((string) ($l['batch_no'] ?? '')));
            if ($batch !== '') {
                if (isset($batches[$batch])) {
                    $errors["lines.{$i}.batch_no"] = __('merchandise.ffob.row_batch_twice', ['n' => $n, 'batch' => trim((string) $l['batch_no']), 'first' => $batches[$batch]]);
                }
                $batches[$batch] ??= $n;
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
        $currency = mb_strtoupper(trim((string) ($data['currency'] ?? ''))) ?: 'USD';

        return [
            'store_id' => $data['store_id'], 'ob_date' => $data['ob_date'], 'order_id' => $data['order_id'] ?? null, 'currency' => $currency,
            'conv_rate' => $currency === 'USD' || $currency === 'BDT' ? 1 : (float) ($data['conv_rate'] ?? 1), 'usd_bdt' => $currency === 'BDT' ? null : ($data['usd_bdt'] ?? $this->usdRate($data['ob_date'])),
        ];
    }

    /** Each batch at its cost in BDT: quantity × rate, in the document's currency, through its conversion and the USD rate. */
    private function writeLines(FinishedFabricOpeningBalance $row, array $lines): void
    {
        FinishedFabricOpeningBalanceLine::query()->where('ob_id', $row->id)->delete();
        $toBdt = $row->currency === 'BDT' ? 1 : $row->conv_rate * (float) ($row->usd_bdt ?? 0);
        $text = fn (array $l, string $k): ?string => trim((string) ($l[$k] ?? '')) ?: null;
        foreach (array_values($lines) as $i => $l) {
            $qty = round((float) $l['qty'], 4);
            $rate = round((float) ($l['rate'] ?? 0), 4);
            FinishedFabricOpeningBalanceLine::query()->create([
                'ob_id' => $row->id, 'category' => $text($l, 'category'), 'description' => trim((string) $l['description']), 'fabric_item' => trim((string) $l['fabric_item']),
                'colour' => $text($l, 'colour'), 'fabric_type_id' => $l['fabric_type_id'], 'fabric_group' => $l['fabric_group'], 'composition_id' => $l['composition_id'] ?? null,
                'gsm' => $l['gsm'] ?? null, 'dia' => $l['dia'] ?? null, 'dia_uom' => $l['dia_uom'] ?? null, 'dia_type' => $l['dia_type'] ?? null, 'batch_no' => $text($l, 'batch_no'),
                'rolls' => $l['rolls'] ?? null, 'uom' => $l['uom'], 'qty' => $qty, 'rate' => $rate, 'cost_bdt' => round($qty * $rate * $toBdt, 2), 'sort_order' => $i + 1,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function present(FinishedFabricOpeningBalance $row): array
    {
        $lines = FinishedFabricOpeningBalanceLine::query()->where('ob_id', $row->id)->orderBy('sort_order')->get();

        return [
            'ob_no' => $row->ob_no, 'store_id' => $row->store_id, 'ob_date' => $row->ob_date->toDateString(), 'order_id' => $row->order_id, 'currency' => $row->currency,
            'conv_rate' => $row->conv_rate, 'usd_bdt' => $row->usd_bdt, 'editable' => true,
            'lines' => $lines->map(fn (FinishedFabricOpeningBalanceLine $l): array => $l->only([
                'category', 'description', 'fabric_item', 'colour', 'fabric_type_id', 'fabric_group', 'composition_id', 'gsm', 'dia', 'dia_uom', 'dia_type', 'batch_no', 'rolls', 'uom', 'qty', 'rate', 'cost_bdt',
            ]))->values()->all(),
        ];
    }

    /** FFOB-2026100001: the year and month, then the company's next finished-fabric opening balance that month; held to the end of the save. */
    private function nextNumber(string $date): string
    {
        $head = 'FFOB-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_ffab_obs:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) FinishedFabricOpeningBalance::query()->where('ob_no', 'like', $head.'%')->pluck('ob_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
