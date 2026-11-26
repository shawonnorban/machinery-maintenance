<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\PurchasedFabricIndent;
use App\Modules\Merchandise\Models\PurchasedFabricIndentLine;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleFabric;
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
 * Trims & Accessories → Purchased Fabric Indent (AnnotechERP 06/06), as
 * Brickland works it: an order picked off the order list fills the buyer,
 * the order, its ship date and the style's purchased fabrics — type,
 * description, composition, GSM, DIA and supplier — and the header and its
 * fabric lines are saved together, numbered PRQ-FAB-202610 0001. Draft →
 * Submitted → Approved or Rejected; only a draft or a rejected indent is
 * changed, a rejected one reopened before it goes again. A fabric already
 * requisitioned for the order is refused.
 */
class PurchasedFabricIndentApiController extends ApiController
{
    /** The orders an indent is raised against. */
    private const OPEN = ['CONFIRMED', 'IN_PRODUCTION'];

    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $orders = Order::query()->whereIn('status', self::OPEN)->orderBy('ship_date')->orderBy('order_no')->get();
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->merge($styles->pluck('buyer_id'))->filter())->get(['id', 'name', 'short_name'])->keyBy('id');

        return ApiResponse::ok([
            'orders' => $orders->map(function (Order $o) use ($styles, $buyers): array {
                $s = $o->style_id ? ($styles[$o->style_id] ?? null) : null;
                $b = $buyers[$o->buyer_id ?? $s?->buyer_id] ?? null;

                return [
                    'value' => $o->id, 'order_no' => $o->order_no, 'label' => "{$o->order_no} · ".($s?->styleNo() ?? $o->style_no).' ('.($o->work_style_no ?: $o->style_no).')',
                    'job' => $o->job_no ?: ($o->work_style_no ?: $o->style_no), 'style' => $s?->styleNo() ?? $o->style_no, 'buyer' => $b ? ($b->short_name ?: $b->name) : null,
                    'buyer_office_id' => $o->buyer_office_id ?? $s?->buyer_office_id, 'ship_date' => $o->ship_date?->toDateString(),
                ];
            })->values()->all(),
            'offices' => BuyerOffice::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name])->values()->all(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'fabric_types' => FabricType::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
            'compositions' => FiberComposition::query()->where('active', true)->orderBy('description')->get(['id', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->name])->values()->all(),
            'indents' => PurchasedFabricIndent::query()->orderByDesc('req_no')->get(['req_no'])->map(fn ($r): array => ['value' => $r->req_no, 'label' => $r->req_no])->values()->all(),
            'uoms' => PurchasedFabricIndent::UOMS, 'statuses' => PurchasedFabricIndent::STATUSES,
        ]);
    }

    /** Records: every indent with its ship date, GSM, DIA, order, supplier and fabrics. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = PurchasedFabricIndent::query()->orderByDesc('req_date')->orderByDesc('req_no')->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id'))->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $rows->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $lines = PurchasedFabricIndentLine::query()->whereIn('indent_id', $rows->pluck('id'))->orderBy('sort_order')->get(['indent_id', 'description', 'fabric_type_id', 'qty'])->groupBy('indent_id');
        $types = FabricType::query()->whereIn('id', $lines->flatten()->pluck('fabric_type_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (PurchasedFabricIndent $r) use ($orders, $offices, $suppliers, $lines, $types): array {
            $o = $orders[$r->order_id] ?? null;
            $mine = $lines[$r->id] ?? collect();

            return [
                'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'ship_date' => $r->ship_date?->toDateString(), 'gsm' => $r->gsm, 'dia' => $r->dia,
                'buyer_ac' => $r->buyer_office_id ? ($offices[$r->buyer_office_id] ?? null) : null, 'job_no' => $o ? ($o->job_no ?: ($o->work_style_no ?: $o->style_no)) : null,
                'order_no' => $o?->order_no, 'supplier' => $r->supplier_id ? ($suppliers[$r->supplier_id] ?? null) : null,
                'items' => $mine->map(fn ($l) => $l->description ?: ($types[$l->fabric_type_id] ?? null))->filter()->implode(', '), 'qty' => round((float) $mine->sum('qty'), 4),
                'status' => $r->status, 'editable' => $r->isEditable(), 'req_month' => $r->req_date->format('Y-m'),
            ];
        })->values()->all());
    }

    /** What an order fills: its buyer account and ship date, and the style's purchased fabrics as lines. */
    public function fill(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $data = $request->validate(['order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $this->caller()->companyId)]]);
        $order = Order::query()->whereKey($data['order_id'])->firstOrFail();
        $style = $order->style_id ? Style::query()->whereKey($order->style_id)->first() : null;
        $fabrics = $style ? StyleFabric::query()->where('style_id', $style->id)->where('source', 'PURCHASED')->orderBy('sort_order')->get() : collect();
        $suppliers = MaterialSupplier::query()->where('active', true)->get(['id', 'name', 'short_name', 'code']);

        return ApiResponse::ok([
            'buyer_office_id' => $order->buyer_office_id ?? $style?->buyer_office_id, 'ship_date' => $order->ship_date?->toDateString(),
            'lines' => $fabrics->map(function (StyleFabric $f) use ($suppliers): array {
                $supplier = $f->supplier ? $suppliers->first(fn ($s) => in_array(mb_strtolower(trim($f->supplier)), array_filter([mb_strtolower((string) $s->name), mb_strtolower((string) $s->short_name), mb_strtolower((string) $s->code)]), true)) : null;
                $gsm = $f->weight_from ? rtrim(rtrim(number_format((float) $f->weight_from, 2, '.', ''), '0'), '.') : null;

                return [
                    'style_fabric_id' => $f->id, 'fabric_type_id' => $f->fabric_type_id, 'description' => $f->description ?: $f->fabric_code, 'construction' => $f->fabric_code,
                    'composition_id' => $f->fiber_composition_id, 'colour_id' => null, 'qty' => null, 'uom' => 'KG', 'gsm' => $gsm, 'dia' => $f->width ? (string) $f->width : null,
                    'supplier_id' => $supplier?->id, 'required_date' => null, 'remarks' => $f->remarks,
                ];
            })->values()->all(),
        ]);
    }

    public function show(string $indent): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($indent)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): PurchasedFabricIndent {
            $row = PurchasedFabricIndent::query()->create($this->header($data) + ['req_no' => $this->nextNumber($data['req_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($row, $data['lines'], $data['supplier_id'] ?? null);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $indent): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($indent);
        $this->assertEditable($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->writeLines($row, $data['lines'], $data['supplier_id'] ?? null);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $indent): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($indent);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Submit, approve, reject or reopen: each only from the state it follows. */
    public function transition(string $indent, string $action): JsonResponse
    {
        $row = $this->find($indent);
        [$permission, $from, $to] = match ($action) {
            'submit' => ['merchandise.accessories.manage', ['DRAFT'], 'SUBMITTED'],
            'approve' => ['merchandise.accessories.approve', ['SUBMITTED'], 'APPROVED'],
            'reject' => ['merchandise.accessories.approve', ['SUBMITTED'], 'REJECTED'],
            'reopen' => ['merchandise.accessories.manage', ['REJECTED'], 'DRAFT'],
        };
        $this->allow($permission);
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_requisition.cannot', ['action' => __("merchandise.acc_requisition.action.{$action}"), 'status' => __('merchandise.master.apr.status.'.$row->status)])]);
        }
        $row->update(['status' => $to] + match ($action) {
            'submit' => ['submitted_at' => now()],
            'approve', 'reject' => ['decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()],
            default => ['decided_at' => null, 'decided_by' => null],
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function find(string $indent): PurchasedFabricIndent
    {
        return PurchasedFabricIndent::query()->where('req_no', $indent)->firstOrFail();
    }

    private function assertEditable(PurchasedFabricIndent $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_requisition.locked', ['status' => __('merchandise.master.apr.status.'.$row->status)])]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PurchasedFabricIndent $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'req_date' => ['required', 'date'],
            'order_id' => ['required', $in('merch_orders')],
            'buyer_office_id' => ['nullable', $in('buyer_offices')],
            'supplier_id' => ['nullable', $in('mp_suppliers')],
            'ship_date' => ['nullable', 'date'],
            'gsm' => ['nullable', 'string', 'max:40'],
            'dia' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.style_fabric_id' => ['nullable', 'string'],
            'lines.*.fabric_type_id' => ['nullable', $in('fabric_types')],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.construction' => ['nullable', 'string', 'max:120'],
            'lines.*.composition_id' => ['nullable', $in('fiber_compositions')],
            'lines.*.colour_id' => ['nullable', $in('colours')],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.uom' => ['nullable', Rule::in(PurchasedFabricIndent::UOMS)],
            'lines.*.gsm' => ['nullable', 'string', 'max:40'],
            'lines.*.dia' => ['nullable', 'string', 'max:40'],
            'lines.*.supplier_id' => ['nullable', $in('mp_suppliers')],
            'lines.*.required_date' => ['nullable', 'date'],
            'lines.*.remarks' => ['nullable', 'string', 'max:200'],
        ], ['req_date.required' => __('merchandise.acc_requisition.date_required'), 'order_id.required' => __('merchandise.acc_requisition.order_required')]);

        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.pfi.lines_required')]);
        }
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (empty($l['fabric_type_id']) && trim((string) ($l['description'] ?? '')) === '') {
                $errors["lines.{$i}.description"] = __('merchandise.pfi.row_fabric', ['n' => $n]);
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.pfi.row_qty', ['n' => $n]);
            }
            if (empty($l['uom'])) {
                $errors["lines.{$i}.uom"] = __('merchandise.pfi.row_uom', ['n' => $n]);
            }
        }
        // A fabric already on another indent of the order (not rejected) is refused.
        if ($errors === []) {
            $others = PurchasedFabricIndent::query()->where('order_id', $data['order_id'])->where('status', '!=', 'REJECTED')->when($row, fn ($q) => $q->whereKeyNot($row->id))->pluck('req_no', 'id');
            $taken = PurchasedFabricIndentLine::query()->whereIn('indent_id', $others->keys())->get(['indent_id', 'fabric_type_id', 'composition_id', 'colour_id', 'description']);
            $seen = [];
            foreach (array_values($data['lines']) as $i => $l) {
                $key = $this->fabricKey($l['fabric_type_id'] ?? null, $l['composition_id'] ?? null, $l['colour_id'] ?? null, $l['description'] ?? null);
                if (isset($seen[$key])) {
                    $errors["lines.{$i}.description"] = __('merchandise.pfi.row_twice', ['n' => $i + 1]);

                    continue;
                }
                $seen[$key] = true;
                $hit = $taken->first(fn ($t) => $this->fabricKey($t->fabric_type_id, $t->composition_id, $t->colour_id, $t->description) === $key);
                if ($hit) {
                    $errors["lines.{$i}.description"] = __('merchandise.pfi.row_duplicate', ['n' => $i + 1, 'no' => $others[$hit->indent_id]]);
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** A fabric is its type, composition, colour and description together. */
    private function fabricKey(?string $type, ?string $composition, ?string $colour, ?string $description): string
    {
        return implode('|', [$type ?? '', $composition ?? '', $colour ?? '', mb_strtolower(trim((string) $description))]);
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        $order = Order::query()->whereKey($data['order_id'])->first(['style_id', 'buyer_office_id', 'ship_date']);
        $style = $order?->style_id ? Style::query()->whereKey($order->style_id)->first(['buyer_office_id']) : null;
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;

        return [
            'req_date' => $data['req_date'], 'order_id' => $data['order_id'],
            // The buyer account and ship date follow the order when none is given.
            'buyer_office_id' => ($data['buyer_office_id'] ?? null) ?: ($order?->buyer_office_id ?? $style?->buyer_office_id),
            'ship_date' => ($data['ship_date'] ?? null) ?: $order?->ship_date?->toDateString(),
            'supplier_id' => $data['supplier_id'] ?? null, 'gsm' => $blank('gsm'), 'dia' => $blank('dia'), 'remarks' => $blank('remarks'),
        ];
    }

    /** The lines as sent — the header's supplier on those naming none. */
    private function writeLines(PurchasedFabricIndent $row, array $lines, ?string $supplierId): void
    {
        PurchasedFabricIndentLine::query()->where('indent_id', $row->id)->delete();
        foreach (array_values($lines) as $i => $l) {
            $text = fn (string $k, int $max): ?string => isset($l[$k]) && trim((string) $l[$k]) !== '' ? mb_substr(trim((string) $l[$k]), 0, $max) : null;
            PurchasedFabricIndentLine::query()->create([
                'indent_id' => $row->id, 'style_fabric_id' => $l['style_fabric_id'] ?? null, 'fabric_type_id' => $l['fabric_type_id'] ?? null, 'description' => $text('description', 200),
                'construction' => $text('construction', 120), 'composition_id' => $l['composition_id'] ?? null, 'colour_id' => $l['colour_id'] ?? null,
                'qty' => round((float) $l['qty'], 4), 'uom' => $l['uom'], 'gsm' => $text('gsm', 40), 'dia' => $text('dia', 40),
                'supplier_id' => ($l['supplier_id'] ?? null) ?: $supplierId, 'required_date' => $l['required_date'] ?? null, 'remarks' => $text('remarks', 200), 'sort_order' => $i + 1,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function present(PurchasedFabricIndent $row): array
    {
        $order = Order::query()->whereKey($row->order_id)->first(['order_no']);
        $lines = PurchasedFabricIndentLine::query()->where('indent_id', $row->id)->orderBy('sort_order')->get();

        return [
            'req_no' => $row->req_no, 'req_date' => $row->req_date->toDateString(), 'order_id' => $row->order_id, 'order_no' => $order?->order_no,
            'buyer_office_id' => $row->buyer_office_id, 'supplier_id' => $row->supplier_id, 'ship_date' => $row->ship_date?->toDateString(), 'gsm' => $row->gsm, 'dia' => $row->dia,
            'remarks' => $row->remarks, 'status' => $row->status, 'editable' => $row->isEditable(),
            'lines' => $lines->map(fn (PurchasedFabricIndentLine $l): array => [
                'style_fabric_id' => $l->style_fabric_id, 'fabric_type_id' => $l->fabric_type_id, 'description' => $l->description, 'construction' => $l->construction,
                'composition_id' => $l->composition_id, 'colour_id' => $l->colour_id, 'qty' => $l->qty, 'uom' => $l->uom, 'gsm' => $l->gsm, 'dia' => $l->dia,
                'supplier_id' => $l->supplier_id, 'required_date' => $l->required_date?->toDateString(), 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }

    /** PRQ-FAB-2026100001: the year and month, then the company's next fabric indent that month; held to the end of the save. */
    private function nextNumber(string $date): string
    {
        $head = 'PRQ-FAB-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_pfab_indents:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) PurchasedFabricIndent::query()->where('req_no', 'like', $head.'%')->pluck('req_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
