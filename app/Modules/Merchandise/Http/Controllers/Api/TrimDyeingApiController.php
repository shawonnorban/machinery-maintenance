<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\TrimDyeingItem;
use App\Modules\Merchandise\Models\TrimDyeingProgram;
use App\Modules\Merchandise\Support\TrimOrders;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Accessories & Trims → Trims Dyeing Programme (AnnotechERP 06/08), as
 * Brickland works it: a programme (bulk, sample or development; buyer,
 * style/order, date, status, remarks), numbered TDP-00001, and on its Program
 * Items panel the trims being dyed — each its description, the colour (from
 * the colour directory or not) and the shade name, quantity and UOM.
 */
class TrimDyeingApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok([
            'buyers' => Buyer::query()->orderBy('name')->get(['id', 'name', 'short_name'])->map(fn ($b): array => ['value' => $b->id, 'label' => $b->short_name ? "{$b->name} ({$b->short_name})" : $b->name])->values()->all(),
            'orders' => collect(TrimOrders::rows(Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('order_no')->get()))
                ->map(fn (array $o): array => ['value' => $o['order_id'], 'label' => $o['label'], 'buyer_id' => $o['buyer_id']])->values()->all(),
            'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'name', 'item_code', 'uom'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'name' => $i->name, 'uom' => $i->uom])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name, 'name' => $c->name])->values()->all(),
            'programs' => TrimDyeingProgram::query()->orderByDesc('program_no')->get(['program_no'])->map(fn ($p): array => ['value' => $p->program_no, 'label' => $p->program_no])->values()->all(),
            'prod_types' => TrimDyeingProgram::PROD_TYPES, 'statuses' => TrimDyeingProgram::STATUSES,
        ]);
    }

    /** Records and the Programs list: every programme with its buyer, style/order, date, item count, remarks and status. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = TrimDyeingProgram::query()->orderByDesc('program_no')->get();
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter())->get(['id', 'name', 'short_name'])->keyBy('id');
        $orders = TrimOrders::rows(Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get());
        $items = TrimDyeingItem::query()->whereIn('program_id', $rows->pluck('id'))->selectRaw('program_id, count(*) as n')->groupBy('program_id')->pluck('n', 'program_id');

        return ApiResponse::ok($rows->map(function (TrimDyeingProgram $p) use ($buyers, $orders, $items): array {
            $b = $p->buyer_id ? ($buyers[$p->buyer_id] ?? null) : null;

            return [
                'program_no' => $p->program_no, 'prod_type' => $p->prod_type, 'buyer' => $b ? ($b->short_name ?: $b->name) : null,
                'order' => $p->order_id ? ($orders[$p->order_id]['title'] ?? null) : null, 'program_date' => $p->program_date->toDateString(),
                'items' => (int) ($items[$p->id] ?? 0), 'remarks' => $p->remarks, 'status' => $p->status,
            ];
        })->values()->all());
    }

    public function show(string $program): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($program)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request);
        $row = DB::transaction(fn (): TrimDyeingProgram => TrimDyeingProgram::query()->create($data + ['program_no' => $this->nextNumber(), 'created_by' => $this->caller()->auditUserId()]));

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $program): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($program);
        $row->update($this->validated($request));

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Add item: kept at once. */
    public function addItem(Request $request, string $program): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($program);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'item_id' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $company)],
            'description' => ['required', 'string', 'max:200'],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'colour_name' => ['required', 'string', 'max:80'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
        ], collect(['description', 'colour_name', 'qty'])->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.tdp.f.{$f}")])])->all() + [
            'qty.gt' => __('merchandise.acb.qty_positive'),
        ]);
        $last = (int) TrimDyeingItem::query()->where('program_id', $row->id)->max('sort_order');
        TrimDyeingItem::query()->create([
            'program_id' => $row->id, 'item_id' => $data['item_id'] ?? null, 'description' => trim($data['description']), 'colour_id' => $data['colour_id'] ?? null,
            'colour_name' => trim($data['colour_name']), 'qty' => round((float) $data['qty'], 4), 'uom' => UomCodes::normalize($data['uom'] ?? null), 'sort_order' => $last + 1,
        ]);

        return ApiResponse::created($this->present($row));
    }

    public function removeItem(string $program, TrimDyeingItem $item): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($program);
        abort_unless($item->program_id === $row->id, 404);
        $item->delete();

        return ApiResponse::ok($this->present($row));
    }

    private function find(string $program): TrimDyeingProgram
    {
        return TrimDyeingProgram::query()->where('program_no', $program)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'prod_type' => ['nullable', Rule::in(TrimDyeingProgram::PROD_TYPES)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'program_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(TrimDyeingProgram::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['program_date.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.tdp.f.program_date')])]);
        // The order names its buyer when none is picked.
        $buyer = $data['buyer_id'] ?? null;
        if (! $buyer && ! empty($data['order_id'])) {
            $buyer = TrimOrders::rows(Order::query()->whereKey($data['order_id'])->get())[$data['order_id']]['buyer_id'] ?? null;
        }

        return [
            'prod_type' => $data['prod_type'] ?? 'BULK', 'buyer_id' => $buyer, 'order_id' => $data['order_id'] ?? null, 'program_date' => $data['program_date'],
            'status' => $data['status'] ?? 'DRAFT', 'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function present(TrimDyeingProgram $p): array
    {
        $items = TrimDyeingItem::query()->where('program_id', $p->id)->orderBy('sort_order')->get();
        $trims = MaterialItem::query()->whereIn('id', $items->pluck('item_id')->filter())->pluck('name', 'id');
        $colours = Colour::query()->whereIn('id', $items->pluck('colour_id')->filter())->pluck('name', 'id');

        return [
            'program_no' => $p->program_no, 'prod_type' => $p->prod_type, 'buyer_id' => $p->buyer_id, 'order_id' => $p->order_id, 'program_date' => $p->program_date->toDateString(),
            'status' => $p->status, 'remarks' => $p->remarks,
            'items' => $items->map(fn (TrimDyeingItem $i): array => [
                'id' => $i->id, 'trim' => $i->item_id ? ($trims[$i->item_id] ?? null) : null, 'description' => $i->description, 'colour' => $i->colour_id ? ($colours[$i->colour_id] ?? null) : null,
                'colour_name' => $i->colour_name, 'qty' => $i->qty, 'uom' => $i->uom,
            ])->values()->all(),
        ];
    }

    /** TDP-00001: the company's next dyeing programme; held to the end of the save. */
    private function nextNumber(): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_trim_dyeing_programs:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) TrimDyeingProgram::query()->pluck('program_no')->map(fn (string $v): int => (int) substr($v, 4))->max();

        return 'TDP-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
