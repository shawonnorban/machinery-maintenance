<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccReturn;
use App\Modules\Merchandise\Models\FabricReturn;
use App\Modules\Merchandise\Models\FabricReturnLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\PurchasedFabricLedger;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Merchandise\Models\PurchasedFabricMrrLine;
use App\Modules\Merchandise\Support\StockPeriods;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trims & Accessories → Accessories Supplier Return Challan (AnnotechERP
 * 06/06), as Brickland works it: the MRR List of finalised receipts (by
 * supplier) on the left; picking one fills the challan with what it brought
 * in, as what is going back, and the quantities are corrected to the return.
 * Numbered RTN-FAB-202610 0001; nothing goes back beyond what came in.
 * Finalised, the challan is locked and its goods leave stock.
 */
class FabricReturnApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $company = $this->caller()->companyId;
        $name = Company::query()->whereKey($company)->value('name');
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id');
        $receipts = PurchasedFabricMrr::query()->where('status', 'FINALIZED')->orderByDesc('mrr_date')->orderByDesc('mrr_no')->get(['id', 'mrr_no', 'mrr_date', 'challan_no', 'supplier_id']);
        $left = $this->leftOf($receipts->pluck('id'));

        return ApiResponse::ok([
            'companies' => $name ? [['value' => $company, 'label' => $name]] : [],
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'users' => User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name', 'email'])->map(fn ($u): array => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])->values()->all(),
            // The MRR List: finalised fabric MRRs with something still to send back.
            'receipts' => $receipts->filter(fn (PurchasedFabricMrr $r) => ($left[$r->id] ?? collect())->sum('left') > 0)->map(fn (PurchasedFabricMrr $r): array => [
                'mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'challan_no' => $r->challan_no, 'supplier_id' => $r->supplier_id,
            ])->values()->all(),
            'returns' => FabricReturn::query()->orderByDesc('challan_no')->get(['challan_no'])->map(fn ($r): array => ['value' => $r->challan_no, 'label' => $r->challan_no])->values()->all(),
            'types' => AccReturn::TYPES,
        ]);
    }

    /** Records: every fabric return challan with its MRR, type, company, supplier and store. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = FabricReturn::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->get();
        $mrrs = PurchasedFabricMrr::query()->whereIn('id', $rows->pluck('mrr_id')->filter())->pluck('mrr_no', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $companies = Company::query()->whereIn('id', $rows->pluck('unit_company_id'))->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (FabricReturn $r): array => [
            'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'return_type' => $r->return_type, 'finalized' => ! $r->isEditable(),
            'mrr_no' => $r->mrr_id ? ($mrrs[$r->mrr_id] ?? null) : null, 'company' => $companies[$r->unit_company_id] ?? null, 'supplier' => $suppliers[$r->supplier_id] ?? null,
            'store' => $stores[$r->store_id] ?? null, 'status' => $r->status,
        ])->values()->all());
    }

    /** What a finalised fabric MRR accepted and has not gone back yet, as the challan's master data and lines (each batch named by its fabric and batch no). */
    public function fromReceipt(string $receipt): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $mrr = PurchasedFabricMrr::query()->where('mrr_no', $receipt)->where('status', 'FINALIZED')->firstOrFail();
        $left = ($this->leftOf(collect([$mrr->id]))[$mrr->id] ?? collect())->where('left', '>', 0);
        $types = FabricType::query()->whereIn('id', $left->map(fn (array $x) => $x['line']->fabric_type_id)->filter())->pluck('name', 'id');

        return ApiResponse::ok([
            'mrr_no' => $mrr->mrr_no, 'unit_company_id' => $mrr->unit_company_id, 'supplier_id' => $mrr->supplier_id, 'store_id' => $mrr->store_id,
            'lines' => $left->map(fn (array $x): array => [
                'mrr_line_id' => $x['line']->id, 'description' => trim(($x['line']->description ?: ($types[$x['line']->fabric_type_id] ?? '')).($x['line']->batch_no ? " · {$x['line']->batch_no}" : '')),
                'uom' => $x['line']->uom, 'qty' => $x['left'], 'reason' => null,
            ])->values()->all(),
        ]);
    }

    public function show(string $challan): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($challan)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): FabricReturn {
            $row = FabricReturn::query()->create($this->header($data) + ['challan_no' => $this->nextNumber($data['challan_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $challan): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($challan);
        $this->assertEditable($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->writeLines($row, $data['lines']);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $challan): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($challan);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Finalised, the challan is locked and each MRR batch on it leaves the purchased-fabric stock ledger at its landed cost per unit (a free line has no batch to take off). */
    public function finalize(string $challan): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($challan);
        $this->assertEditable($row);
        StockPeriods::assertOpen('purchased-fabric', $row->challan_date);
        DB::transaction(function () use ($row): void {
            $row->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $this->caller()->auditUserId()]);
            $mrr = PurchasedFabricMrr::query()->whereKey($row->mrr_id)->first();
            $lines = FabricReturnLine::query()->where('return_id', $row->id)->whereNotNull('mrr_line_id')->orderBy('sort_order')->get();
            $batches = PurchasedFabricMrrLine::query()->whereIn('id', $lines->pluck('mrr_line_id'))->get()->keyBy('id');
            foreach ($lines as $l) {
                $b = $batches[$l->mrr_line_id];
                $unit = $b->accept_qty > 0 ? $b->landed_amount / $b->accept_qty : 0;
                PurchasedFabricLedger::query()->create([
                    'store_id' => $row->store_id, 'source' => 'RTN', 'source_id' => $row->id, 'source_line_id' => $l->id, 'txn_date' => $row->challan_date->toDateString(),
                    'batch_no' => $b->batch_no, 'fabric_item' => $b->description ?: (FabricType::query()->whereKey($b->fabric_type_id)->value('name') ?? '—'), 'description' => $b->description,
                    'colour_id' => $b->colour_id, 'fabric_type_id' => $b->fabric_type_id, 'composition_id' => $b->composition_id, 'uom' => $b->uom, 'qty' => -$l->qty,
                    'cost_bdt' => -round($unit * $l->qty * ($mrr === null || $mrr->currency === 'BDT' ? 1 : $mrr->conv_rate), 2),
                ]);
            }
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function find(string $challan): FabricReturn
    {
        return FabricReturn::query()->where('challan_no', $challan)->firstOrFail();
    }

    private function assertEditable(FabricReturn $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_rtn.locked')]);
        }
    }

    /**
     * Each MRR's batches with what is left to send back: accepted, less what every challan but `$except` (draft or final) returns.
     *
     * @return Collection<string, Collection<int, array{line: PurchasedFabricMrrLine, left: float}>>
     */
    private function leftOf(Collection $mrrIds, ?string $except = null): Collection
    {
        $lines = PurchasedFabricMrrLine::query()->whereIn('mrr_id', $mrrIds)->orderBy('sort_order')->get();
        $back = FabricReturnLine::query()->whereIn('mrr_line_id', $lines->pluck('id'))->when($except, fn ($q) => $q->where('return_id', '!=', $except))
            ->selectRaw('mrr_line_id, sum(qty) as qty')->groupBy('mrr_line_id')->pluck('qty', 'mrr_line_id');

        return $lines->map(fn (PurchasedFabricMrrLine $l): array => ['line' => $l, 'left' => round(max(0, $l->accept_qty - (float) ($back[$l->id] ?? 0)), 4)])->groupBy(fn (array $x) => $x['line']->mrr_id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FabricReturn $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id')->all();
        $required = ['return_type', 'unit_company_id', 'supplier_id', 'mrr_no', 'challan_date', 'store_id', 'return_by'];
        $data = $request->validate([
            'return_type' => ['required', Rule::in(AccReturn::TYPES)],
            'unit_company_id' => ['required', Rule::in([$company])],
            'supplier_id' => ['required', $in('mp_suppliers')],
            'mrr_no' => ['required', Rule::exists('merch_pfab_mrrs', 'mrr_no')->where('company_id', $company)->where('status', 'FINALIZED')],
            'challan_date' => ['required', 'date'],
            'store_id' => ['required', $in('stores')],
            'return_by' => ['required', Rule::in($members)],
            'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'vehicle_no' => ['nullable', 'string', 'max:40'],
            'driver_name' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.mrr_line_id' => ['nullable', $in('merch_pfab_mrr_lines')],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.uom' => ['nullable', 'string', 'max:24', UomCodes::rule($this->caller()->companyId)],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.reason' => ['nullable', 'string', 'max:200'],
        ], collect($required)->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.rtn.f.{$f}")])])->all() + [
            'mrr_no.exists' => __('merchandise.acc_rtn.not_finalised'),
        ]);

        $mrr = PurchasedFabricMrr::query()->where('mrr_no', $data['mrr_no'])->firstOrFail();
        if ($mrr->supplier_id !== $data['supplier_id']) {
            throw ValidationException::withMessages(['supplier_id' => __('merchandise.acc_rtn.other_supplier', ['no' => $mrr->mrr_no])]);
        }
        if ($data['challan_date'] < $mrr->mrr_date->toDateString()) {
            throw ValidationException::withMessages(['challan_date' => __('merchandise.acc_rtn.before_receipt', ['date' => $mrr->mrr_date->format('d-M-Y')])]);
        }
        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);
        // A closed month's documents are no longer saved (Month Close, T14–T19).
        StockPeriods::assertOpen('purchased-fabric', $data['challan_date'], 'challan_date');

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_rtn.lines_required')]);
        }
        $left = ($this->leftOf(collect([$mrr->id]), $row?->id)[$mrr->id] ?? collect())->keyBy(fn (array $x) => $x['line']->id);
        $asked = [];
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (trim((string) ($l['description'] ?? '')) === '') {
                $errors["lines.{$i}.description"] = __('merchandise.acc_rtn.row_description', ['n' => $n]);
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.acc_rtn.row_qty', ['n' => $n]);

                continue;
            }
            $lineId = $l['mrr_line_id'] ?? null;
            if ($lineId) {
                if (! $left->has($lineId)) {
                    $errors["lines.{$i}.mrr_line_id"] = __('merchandise.acc_rtn.row_other_receipt', ['n' => $n, 'no' => $mrr->mrr_no]);

                    continue;
                }
                $asked[$lineId] = ($asked[$lineId] ?? 0) + (float) $l['qty'];
                $available = (float) $left[$lineId]['left'];
                if ($asked[$lineId] > $available + 0.00005) {
                    $errors["lines.{$i}.qty"] = __('merchandise.acc_rtn.row_over', ['n' => $n, 'left' => rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'), 'no' => $mrr->mrr_no]);
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data + ['mrr_id' => $mrr->id];
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;

        return [
            'return_type' => $data['return_type'], 'unit_company_id' => $data['unit_company_id'], 'supplier_id' => $data['supplier_id'], 'mrr_id' => $data['mrr_id'],
            'challan_date' => $data['challan_date'], 'store_id' => $data['store_id'], 'return_by' => $data['return_by'], 'gate_pass_no' => $blank('gate_pass_no'),
            'vehicle_no' => $blank('vehicle_no'), 'driver_name' => $blank('driver_name'), 'remarks' => $blank('remarks'),
        ];
    }

    private function writeLines(FabricReturn $row, array $lines): void
    {
        FabricReturnLine::query()->where('return_id', $row->id)->delete();
        foreach (array_values($lines) as $i => $l) {
            $lineId = $l['mrr_line_id'] ?? null;
            FabricReturnLine::query()->create([
                'return_id' => $row->id, 'mrr_line_id' => $lineId, 'description' => trim((string) $l['description']),
                'uom' => UomCodes::normalize($l['uom'] ?? null), 'qty' => round((float) $l['qty'], 4), 'reason' => trim((string) ($l['reason'] ?? '')) ?: null, 'sort_order' => $i + 1,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function present(FabricReturn $row): array
    {
        $lines = FabricReturnLine::query()->where('return_id', $row->id)->orderBy('sort_order')->get();

        return [
            'challan_no' => $row->challan_no, 'return_type' => $row->return_type, 'unit_company_id' => $row->unit_company_id, 'supplier_id' => $row->supplier_id,
            'mrr_no' => $row->mrr_id ? PurchasedFabricMrr::query()->whereKey($row->mrr_id)->value('mrr_no') : null, 'challan_date' => $row->challan_date->toDateString(),
            'store_id' => $row->store_id, 'return_by' => $row->return_by, 'gate_pass_no' => $row->gate_pass_no, 'vehicle_no' => $row->vehicle_no, 'driver_name' => $row->driver_name,
            'remarks' => $row->remarks, 'status' => $row->status, 'editable' => $row->isEditable(),
            'lines' => $lines->map(fn (FabricReturnLine $l): array => ['mrr_line_id' => $l->mrr_line_id, 'description' => $l->description, 'uom' => $l->uom, 'qty' => $l->qty, 'reason' => $l->reason])->values()->all(),
        ];
    }

    /** RTN-FAB-2026100001: the year and month, then the company's next return challan that month; held to the end of the save. */
    private function nextNumber(string $date): string
    {
        $head = 'RTN-FAB-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_pfab_returns:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) FabricReturn::query()->where('challan_no', 'like', $head.'%')->pluck('challan_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
