<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeChemRequisition;
use App\Modules\DyeHouse\Models\DyeChemRequisitionLine;
use App\Modules\DyeHouse\Models\DyeFabricBooking;
use App\Modules\DyeHouse\Models\DyeFabricBookingLine;
use App\Modules\DyeHouse\Models\DyeOrderClosing;
use App\Modules\DyeHouse\Models\DyeTrimProgramme;
use App\Modules\DyeHouse\Models\DyeTrimProgrammeLine;
use App\Modules\DyeHouse\Models\DyeWashProduction;
use App\Modules\DyeHouse\Models\DyeWashProductionLine;
use App\Modules\DyeHouse\Support\ChemStore;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Production Board (AnnotechERP 17/07): Machine Wash Output (what
 * was dosed on a machine wash requisition, one production per requisition,
 * addressed by the requisition's number), Direct and General Fabric Booking
 * (DFB- / GFB-), the Trims Dyeing Programme (TDP-) and Dyeing & Finishing
 * Closing (an order closed out of dyeing and finishing for a scope; its cards
 * take no more production). A booking or programme is addressed by its
 * number and reads only once closed or cancelled.
 */
class DyeProductionBoardApiController extends ApiController
{
    private const PREFIX = ['DIRECT' => 'DFB-', 'GENERAL' => 'GFB-'];

    public function options(): JsonResponse
    {
        $this->allow('dyehouse.production.view');
        $c = DyeBatches::company();
        $produced = DyeWashProduction::query()->pluck('requisition_id');

        return ApiResponse::ok([
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'short_name', 'code'])
                ->map(fn ($b) => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')])->values()->all(),
            // "876MB · LPP": an order by its style and buyer, as Brickland's Style/Ord# picker shows it.
            'orders' => DB::table('merch_orders')->leftJoin('buyers', 'buyers.id', '=', 'merch_orders.buyer_id')->where('merch_orders.company_id', $c)->where('merch_orders.status', '!=', 'CANCELLED')
                ->orderByDesc('merch_orders.created_at')->limit(1000)->get(['merch_orders.id', 'merch_orders.order_no', 'merch_orders.style_no', 'merch_orders.work_style_no', 'merch_orders.buyer_id', 'buyers.short_name', 'buyers.name'])
                ->map(fn ($o) => ['value' => $o->id, 'label' => trim(($o->work_style_no ?: $o->style_no).' · '.($o->short_name ?: $o->name), ' ·'), 'order_no' => $o->order_no, 'buyer_id' => $o->buyer_id])->values()->all(),
            'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->code ? "{$x->name} ({$x->code})" : $x->name])->values()->all(),
            'machines' => DB::table('dye_machines')->where('company_id', $c)->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'is_sample'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no, 'is_sample' => (bool) $m->is_sample])->values()->all(),
            'departments' => DB::table('departments')->where('company_id', $c)->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'users' => DB::table('company_users')->join('users', 'users.id', '=', 'company_users.user_id')->where('company_users.company_id', $c)->where('company_users.status', 'ACTIVE')
                ->orderBy('users.name')->get(['users.id', 'users.name', 'users.email'])->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])->values()->all(),
            'companies' => DB::table('companies')->where('id', $c)->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values()->all(),
            'chemicals' => DyeBatches::chemicals(),
            'fabric_types' => DB::table('fabric_types')->where('company_id', $c)->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'short_name'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => $f->short_name ? "{$f->name} ({$f->short_name})" : $f->name])->values()->all(),
            // Machine wash requisitions not produced against yet, nor cancelled.
            'wash_requisitions' => DyeChemRequisition::query()->where('req_for', 'MC_WASH')->where('status', '!=', 'CANCELLED')->whereNotIn('id', $produced)
                ->orderByDesc('req_date')->orderByDesc('req_no')->get(['req_no', 'req_date'])->map(fn ($r) => ['value' => $r->req_no, 'label' => "{$r->req_no} · {$r->req_date->toDateString()}"])->values()->all(),
        ]);
    }

    /* ── Machine Wash Output ─────────────────────────────────────────── */

    public function washes(): JsonResponse
    {
        $this->allow('dyehouse.production.view');
        $rows = DyeWashProduction::query()->orderByDesc('production_date')->get();

        return ApiResponse::ok($this->washRows($rows));
    }

    /** A wash requisition's sheet: its details and lines with what the stores hold now, and the production written against it, if any. */
    public function wash(string $reqNo): JsonResponse
    {
        $this->allow('dyehouse.production.view');

        return ApiResponse::ok($this->washSheet($this->washRequisition($reqNo)));
    }

    /** Save the production of a wash requisition: new, or the one already written rewritten. */
    public function saveWash(Request $request, string $reqNo): JsonResponse
    {
        $this->allow('dyehouse.production.manage');
        $req = $this->washRequisition($reqNo);
        $c = DyeBatches::company();
        $data = $request->validate([
            'production_date' => ['required', 'date'],
            'party_id' => ['required', Rule::in([$c])],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('company_id', $c)],
            'machine_id' => ['required', Rule::exists('dye_machines', 'id')->where('company_id', $c)],
            'requisition_to' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $c)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $c)],
            'lines.*.kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.gm' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.mg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'production_date.required' => __('dyehouse.prod.v.production_date'), 'party_id.required' => __('dyehouse.prod.v.company'), 'department_id.required' => __('dyehouse.prod.v.department'),
            'machine_id.required' => __('dyehouse.prod.v.wash_machine'), 'lines.required' => __('dyehouse.prod.v.wash_lines'), 'lines.min' => __('dyehouse.prod.v.wash_lines'),
            'lines.*.item_id.required' => __('dyehouse.prod.v.line_item'),
        ]);
        ksort($data['lines']);
        $lines = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $kg = (float) ($l['kg'] ?? 0);
            $gm = (float) ($l['gm'] ?? 0);
            $mg = (float) ($l['mg'] ?? 0);
            // Kept as written on three scales, totalled onto one.
            $total = round($kg + $gm / 1000 + $mg / 1000000, 6);
            if ($total <= 0) {
                throw ValidationException::withMessages(["lines.{$i}.kg" => __('dyehouse.prod.v.wash_dose', ['n' => $i + 1])]);
            }
            $lines[] = ['item_id' => $l['item_id'], 'kg' => $kg, 'gm' => $gm, 'mg' => $mg, 'total_kg' => $total, 'sort_order' => ($i + 1) * 10];
        }
        $row = DyeWashProduction::query()->where('requisition_id', $req->id)->first();
        DB::transaction(function () use (&$row, $req, $data, $lines): void {
            $head = ['production_date' => $data['production_date'], 'party_id' => $data['party_id'], 'department_id' => $data['department_id'], 'dye_machine_id' => $data['machine_id'],
                'requisition_to' => $data['requisition_to'] ?? null, 'remarks' => ($data['remarks'] ?? '') ?: null];
            if ($row) {
                $row->update($head);
                DyeWashProductionLine::query()->where('production_id', $row->id)->delete();
            } else {
                $row = DyeWashProduction::query()->create($head + ['requisition_id' => $req->id, 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $l) {
                DyeWashProductionLine::query()->create($l + ['production_id' => $row->id]);
            }
        });

        return ApiResponse::ok($this->washSheet($req), [], $row->wasRecentlyCreated ? 201 : 200);
    }

    private function washRequisition(string $reqNo): DyeChemRequisition
    {
        $req = DyeChemRequisition::query()->where('req_no', $reqNo)->firstOrFail();
        if ($req->req_for !== 'MC_WASH') {
            throw ValidationException::withMessages(['requisition_id' => __('dyehouse.prod.v.not_wash', ['no' => $req->req_no])]);
        }
        if ($req->status === 'CANCELLED') {
            throw ValidationException::withMessages(['requisition_id' => __('dyehouse.prod.v.req_cancelled', ['no' => $req->req_no])]);
        }

        return $req;
    }

    /** @return array<string, mixed> */
    private function washSheet(DyeChemRequisition $req): array
    {
        $n = DyeBatches::names();
        $row = DyeWashProduction::query()->where('requisition_id', $req->id)->first();
        $reqLines = DyeChemRequisitionLine::query()->where('requisition_id', $req->id)->orderBy('sort_order')->get();
        $done = $row ? DyeWashProductionLine::query()->where('production_id', $row->id)->orderBy('sort_order')->get() : collect();
        $items = ChemStore::itemNames($reqLines->pluck('item_id')->merge($done->pluck('item_id')));
        // What the requisition's sub-store holds, and every other store together.
        $stock = function (string $item) use ($req): array {
            $sub = ChemStore::itemBalance($req->store_id, $item);
            $all = round((float) DB::table('dye_chem_moves')->join('dye_chem_lots', 'dye_chem_lots.id', '=', 'dye_chem_moves.lot_id')->where('dye_chem_lots.item_id', $item)->sum('dye_chem_moves.qty'), 4);

            return ['sub_stock' => $sub, 'central_stock' => round($all - $sub, 4)];
        };

        return [
            'requisition' => ['req_no' => $req->req_no, 'req_date' => $req->req_date->toDateString(), 'req_type' => $req->req_type, 'store' => ChemStore::store($req->store_id),
                'machine_id' => $req->dye_machine_id, 'machine' => $n['machines'][$req->dye_machine_id] ?? null, 'status' => $req->status, 'remarks' => $req->remarks,
                'lines' => $reqLines->map(fn ($l) => ['item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null, 'uom' => $l->uom, 'required_qty' => (float) $l->required_qty,
                    'issued_qty' => (float) $l->issued_qty] + $stock($l->item_id))->values()->all()],
            'production' => $row ? [
                'production_date' => $row->production_date->toDateString(), 'party_id' => $row->party_id, 'department_id' => $row->department_id, 'machine_id' => $row->dye_machine_id,
                'requisition_to' => $row->requisition_to, 'remarks' => $row->remarks,
                'lines' => $done->map(fn (DyeWashProductionLine $l) => ['item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null, 'kg' => (float) $l->kg, 'gm' => (float) $l->gm,
                    'mg' => (float) $l->mg, 'total_kg' => (float) $l->total_kg] + $stock($l->item_id))->values()->all(),
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, DyeWashProduction>  $rows
     * @return list<array<string, mixed>>
     */
    private function washRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $reqs = DyeChemRequisition::query()->whereIn('id', $rows->pluck('requisition_id'))->get(['id', 'req_no', 'req_date'])->keyBy('id');
        $sums = DyeWashProductionLine::query()->whereIn('production_id', $rows->pluck('id'))->groupBy('production_id')->selectRaw('production_id, count(*) as items, sum(total_kg) as kg')->get()->keyBy('production_id');
        $depts = DB::table('departments')->whereIn('id', $rows->pluck('department_id'))->pluck('name', 'id');
        $users = DB::table('users')->whereIn('id', $rows->pluck('requisition_to')->filter())->pluck('name', 'id');

        return $rows->map(fn (DyeWashProduction $r) => [
            'req_no' => $reqs[$r->requisition_id]->req_no ?? null, 'req_date' => $reqs[$r->requisition_id]?->req_date?->toDateString(), 'production_date' => $r->production_date->toDateString(),
            'company' => $n['companies'][$r->party_id ?? DyeBatches::company()] ?? null, 'department' => $depts[$r->department_id] ?? null, 'machine' => $n['machines'][$r->dye_machine_id] ?? null,
            'requisition_to' => $users[$r->requisition_to] ?? null, 'items' => (int) ($sums[$r->id]->items ?? 0), 'total_kg' => round((float) ($sums[$r->id]->kg ?? 0), 4), 'remarks' => $r->remarks,
        ])->values()->all();
    }

    /* ── Fabric bookings and trim programmes ─────────────────────────── */

    public function bookings(Request $request): JsonResponse
    {
        $this->allow('dyehouse.production.view');
        $kind = $request->validate(['kind' => ['required', Rule::in(DyeFabricBooking::KINDS)]])['kind'];

        return ApiResponse::ok($this->bookingRows(DyeFabricBooking::query()->where('kind', $kind)->orderByDesc('prog_date')->orderByDesc('booking_no')->get()));
    }

    public function booking(string $no): JsonResponse
    {
        $this->allow('dyehouse.production.view');

        return ApiResponse::ok($this->presentBooking(DyeFabricBooking::query()->where('booking_no', $no)->firstOrFail()));
    }

    /** A booking and its fabrics together: new, or rewritten while not closed or cancelled. A direct booking names its buyer and style. */
    public function saveBooking(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.production.manage');
        $row = $no ? DyeFabricBooking::query()->where('booking_no', $no)->firstOrFail() : null;
        $this->assertOpen($row?->status, $row?->booking_no);
        $kind = $row?->kind ?? $request->input('kind');
        $c = DyeBatches::company();
        $direct = $kind === 'DIRECT';
        $data = $request->validate([
            'kind' => [$row ? 'prohibited' : 'required', Rule::in(DyeFabricBooking::KINDS)],
            'prod_type' => ['required', Rule::in(DyeFabricBooking::PROD_TYPES)],
            'prog_date' => ['required', 'date'],
            'buyer_id' => [$direct ? 'required' : 'nullable', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'order_id' => [$direct ? 'required' : 'nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'req_type' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::in(DyeFabricBooking::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.fabric' => ['required', 'string', 'max:160'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $c)],
            'lines.*.qty_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], $this->programmeMessages('booking'));
        ksort($data['lines']);
        $this->assertOrderOfBuyer($data['order_id'] ?? null, $data['buyer_id'] ?? null);
        $row = DB::transaction(function () use ($row, $kind, $data): DyeFabricBooking {
            $head = ['prod_type' => $data['prod_type'], 'prog_date' => $data['prog_date'], 'buyer_id' => $data['buyer_id'] ?? null, 'order_id' => $data['order_id'] ?? null,
                'req_type' => trim((string) ($data['req_type'] ?? '')) ?: 'Dyeing', 'status' => $data['status'], 'remarks' => ($data['remarks'] ?? '') ?: null];
            if ($row) {
                $row->update($head);
                DyeFabricBookingLine::query()->where('booking_id', $row->id)->delete();
            } else {
                $row = DyeFabricBooking::query()->create($head + ['kind' => $kind, 'created_by' => $this->caller()->auditUserId(),
                    'booking_no' => DyeBatches::nextNumber(self::PREFIX[$kind], 'dye_fabric_bookings', 'booking_no', $data['prog_date'], 'Ym')]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                DyeFabricBookingLine::query()->create(['booking_id' => $row->id, 'fabric' => trim($l['fabric']), 'colour_id' => $l['colour_id'] ?? null, 'qty_kg' => $l['qty_kg'],
                    'remarks' => ($l['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentBooking($row->refresh()), [], $no ? 200 : 201);
    }

    public function trims(): JsonResponse
    {
        $this->allow('dyehouse.production.view');

        return ApiResponse::ok($this->trimRows(DyeTrimProgramme::query()->orderByDesc('prog_date')->orderByDesc('programme_no')->get()));
    }

    public function trim(string $no): JsonResponse
    {
        $this->allow('dyehouse.production.view');

        return ApiResponse::ok($this->presentTrim(DyeTrimProgramme::query()->where('programme_no', $no)->firstOrFail()));
    }

    /** A trim programme and its trims together: new, or rewritten while not closed or cancelled. */
    public function saveTrim(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.production.manage');
        $row = $no ? DyeTrimProgramme::query()->where('programme_no', $no)->firstOrFail() : null;
        $this->assertOpen($row?->status, $row?->programme_no);
        $c = DyeBatches::company();
        $data = $request->validate([
            'prod_type' => ['required', Rule::in(DyeFabricBooking::PROD_TYPES)],
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'prog_date' => ['required', 'date'],
            'status' => ['required', Rule::in(DyeFabricBooking::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item' => ['required', 'string', 'max:160'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $c)],
            'lines.*.yds' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.qty_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], $this->programmeMessages('trim'));
        ksort($data['lines']);
        foreach (array_values($data['lines']) as $i => $l) {
            if ((float) ($l['yds'] ?? 0) <= 0 && (float) ($l['qty_kg'] ?? 0) <= 0) {
                throw ValidationException::withMessages(["lines.{$i}.yds" => __('dyehouse.prod.v.trim_qty', ['n' => $i + 1])]);
            }
        }
        $this->assertOrderOfBuyer($data['order_id'], $data['buyer_id']);
        $row = DB::transaction(function () use ($row, $data): DyeTrimProgramme {
            $head = ['prod_type' => $data['prod_type'], 'buyer_id' => $data['buyer_id'], 'order_id' => $data['order_id'], 'prog_date' => $data['prog_date'], 'status' => $data['status'],
                'remarks' => ($data['remarks'] ?? '') ?: null];
            if ($row) {
                $row->update($head);
                DyeTrimProgrammeLine::query()->where('programme_id', $row->id)->delete();
            } else {
                $row = DyeTrimProgramme::query()->create($head + ['created_by' => $this->caller()->auditUserId(),
                    'programme_no' => DyeBatches::nextNumber('TDP-', 'dye_trim_programmes', 'programme_no', $data['prog_date'], 'Ym')]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                DyeTrimProgrammeLine::query()->create(['programme_id' => $row->id, 'item' => trim($l['item']), 'colour_id' => $l['colour_id'] ?? null, 'yds' => $l['yds'] ?? null,
                    'qty_kg' => $l['qty_kg'] ?? null, 'remarks' => ($l['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentTrim($row->refresh()), [], $no ? 200 : 201);
    }

    private function assertOpen(?string $status, ?string $no): void
    {
        if (in_array($status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('dyehouse.prod.v.paper_closed', ['no' => $no])]);
        }
    }

    /** The order picked is the buyer's own. */
    private function assertOrderOfBuyer(?string $orderId, ?string $buyerId): void
    {
        if ($orderId && $buyerId && DB::table('merch_orders')->where('id', $orderId)->value('buyer_id') !== $buyerId) {
            throw ValidationException::withMessages(['order_id' => __('dyehouse.prod.v.order_of_buyer')]);
        }
    }

    /** @return array<string, string> */
    private function programmeMessages(string $what): array
    {
        return [
            'kind.required' => __('dyehouse.prod.v.kind'), 'prod_type.required' => __('dyehouse.prod.v.prod_type'), 'prog_date.required' => __('dyehouse.prod.v.prog_date'),
            'buyer_id.required' => __('dyehouse.prod.v.buyer'), 'order_id.required' => __('dyehouse.prod.v.style'), 'status.required' => __('dyehouse.prod.v.status'),
            'lines.required' => __("dyehouse.prod.v.{$what}_lines"), 'lines.min' => __("dyehouse.prod.v.{$what}_lines"),
            'lines.*.fabric.required' => __('dyehouse.prod.v.fabric'), 'lines.*.item.required' => __('dyehouse.prod.v.trim_item'),
            'lines.*.qty_kg.required' => __('dyehouse.prod.v.line_kg'), 'lines.*.qty_kg.gt' => __('dyehouse.prod.v.line_kg'),
        ];
    }

    /**
     * @param  Collection<int, DyeFabricBooking>  $rows
     * @return list<array<string, mixed>>
     */
    private function bookingRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $sums = DyeFabricBookingLine::query()->whereIn('booking_id', $rows->pluck('id'))->groupBy('booking_id')->selectRaw('booking_id, count(*) as items, sum(qty_kg) as kg')->get()->keyBy('booking_id');

        return $rows->map(fn (DyeFabricBooking $r) => [
            'kind' => $r->kind, 'booking_no' => $r->booking_no, 'prod_type' => $r->prod_type, 'prog_date' => $r->prog_date->toDateString(), 'buyer_id' => $r->buyer_id,
            'buyer' => $n['buyers'][$r->buyer_id] ?? null, 'order_id' => $r->order_id, 'style_order' => $this->styleOrder($r->order_id, $n), 'req_type' => $r->req_type,
            'status' => $r->status, 'remarks' => $r->remarks, 'items' => (int) ($sums[$r->id]->items ?? 0), 'qty_kg' => round((float) ($sums[$r->id]->kg ?? 0), 3),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentBooking(DyeFabricBooking $r): array
    {
        $n = DyeBatches::names();

        return $this->bookingRows(collect([$r]))[0] + ['lines' => DyeFabricBookingLine::query()->where('booking_id', $r->id)->orderBy('sort_order')->get()
            ->map(fn (DyeFabricBookingLine $l) => ['fabric' => $l->fabric, 'colour_id' => $l->colour_id, 'colour' => $n['colours'][$l->colour_id] ?? null, 'qty_kg' => (float) $l->qty_kg, 'remarks' => $l->remarks])->values()->all()];
    }

    /**
     * @param  Collection<int, DyeTrimProgramme>  $rows
     * @return list<array<string, mixed>>
     */
    private function trimRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $sums = DyeTrimProgrammeLine::query()->whereIn('programme_id', $rows->pluck('id'))->groupBy('programme_id')
            ->selectRaw('programme_id, count(*) as items, sum(coalesce(yds, 0)) as yds, sum(coalesce(qty_kg, 0)) as kg')->get()->keyBy('programme_id');

        return $rows->map(fn (DyeTrimProgramme $r) => [
            'programme_no' => $r->programme_no, 'prod_type' => $r->prod_type, 'prog_date' => $r->prog_date->toDateString(), 'buyer_id' => $r->buyer_id, 'buyer' => $n['buyers'][$r->buyer_id] ?? null,
            'order_id' => $r->order_id, 'style_order' => $this->styleOrder($r->order_id, $n), 'status' => $r->status, 'remarks' => $r->remarks,
            'items' => (int) ($sums[$r->id]->items ?? 0), 'yds' => round((float) ($sums[$r->id]->yds ?? 0), 2), 'qty_kg' => round((float) ($sums[$r->id]->kg ?? 0), 3),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentTrim(DyeTrimProgramme $r): array
    {
        $n = DyeBatches::names();

        return $this->trimRows(collect([$r]))[0] + ['lines' => DyeTrimProgrammeLine::query()->where('programme_id', $r->id)->orderBy('sort_order')->get()
            ->map(fn (DyeTrimProgrammeLine $l) => ['item' => $l->item, 'colour_id' => $l->colour_id, 'colour' => $n['colours'][$l->colour_id] ?? null,
                'yds' => $l->yds === null ? null : (float) $l->yds, 'qty_kg' => $l->qty_kg === null ? null : (float) $l->qty_kg, 'remarks' => $l->remarks])->values()->all()];
    }

    /** "876MB / LPP-2026-000007": an order's style and number. */
    private function styleOrder(?string $orderId, array $n): ?string
    {
        $o = $orderId ? ($n['orders'][$orderId] ?? null) : null;

        return $o ? trim(($o->work_style_no ?: $o->style_no).' / '.$o->order_no, ' /') : null;
    }

    /* ── Dyeing & Finishing Closing ──────────────────────────────────── */

    /**
     * Every order with dye cards of a scope: what it is, what was booked and
     * batched for it, and whether it is still running or closed.
     */
    public function closings(): JsonResponse
    {
        $this->allow('dyehouse.production.view');
        $n = DyeBatches::names();
        $groups = DyeBatch::query()->whereNotNull('order_id')->get(['id', 'order_id', 'scope', 'status', 'card_qty'])->groupBy(fn ($b) => $b->order_id.'|'.$b->scope);
        $orders = DB::table('merch_orders')->whereIn('id', $groups->keys()->map(fn ($k) => explode('|', $k)[0])->unique())->get()->keyBy('id');
        $fabrics = DB::table('dye_batch_items')->whereIn('batch_id', DyeBatch::query()->whereNotNull('order_id')->select('id'))->join('dye_batches', 'dye_batches.id', '=', 'dye_batch_items.batch_id')
            ->groupBy('dye_batches.order_id', 'dye_batches.scope')->selectRaw('dye_batches.order_id, dye_batches.scope, count(distinct dye_batch_items.description) as n')->get()
            ->mapWithKeys(fn ($f) => [$f->order_id.'|'.$f->scope => (int) $f->n]);
        $booked = DB::table('dye_fabric_booking_lines')->join('dye_fabric_bookings', 'dye_fabric_bookings.id', '=', 'dye_fabric_booking_lines.booking_id')
            ->where('dye_fabric_bookings.status', '!=', 'CANCELLED')->whereNotNull('dye_fabric_bookings.order_id')
            ->groupBy('dye_fabric_bookings.order_id', 'dye_fabric_bookings.prod_type')->selectRaw('dye_fabric_bookings.order_id, dye_fabric_bookings.prod_type, sum(qty_kg) as kg')->get()
            ->mapWithKeys(fn ($b) => [$b->order_id.'|'.$b->prod_type => (float) $b->kg]);
        $closed = DyeOrderClosing::query()->get()->keyBy(fn ($c) => $c->order_id.'|'.$c->scope);
        $rows = [];
        foreach ($groups as $key => $cards) {
            [$orderId, $scope] = explode('|', $key);
            $o = $orders[$orderId] ?? null;
            if (! $o) {
                continue;
            }
            $rows[] = [
                'key' => "{$o->order_no}|{$scope}", 'scope' => $scope, 'order_no' => $o->order_no, 'buyer_id' => $o->buyer_id, 'buyer' => $n['buyers'][$o->buyer_id] ?? null,
                'job_no' => $o->job_no, 'style' => $o->work_style_no ?: $o->style_no, 'order_type' => $o->order_type, 'ship_date' => $o->ship_date,
                'ship_month' => $o->ship_date ? substr((string) $o->ship_date, 0, 7) : null, 'fabrics' => $fabrics[$key] ?? 0, 'booked_kg' => round($booked[$key] ?? 0, 3),
                'req_qty' => round((float) $cards->sum('card_qty'), 3), 'batches' => $cards->count(), 'open_batches' => $cards->where('status', 'OPEN')->count(),
                'status' => isset($closed[$key]) ? 'CLOSED' : 'RUNNING', 'closed_at' => ($closed[$key] ?? null)?->created_at?->toIso8601String(),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['status'], $b['ship_date'] ?? ''] <=> [$b['status'], $a['ship_date'] ?? '']);

        return ApiResponse::ok($rows);
    }

    /**
     * Close an order out of dyeing and finishing for a scope: its open cards
     * are closed and take no more production. Refused while a load of its
     * cards is still on a machine.
     */
    public function close(Request $request): JsonResponse
    {
        $this->allow('dyehouse.production.close');
        $data = $request->validate(['order_no' => ['required', 'string'], 'scope' => ['required', Rule::in(['BULK', 'SAMPLE'])], 'remarks' => ['nullable', 'string', 'max:2000']]);
        $order = DB::table('merch_orders')->where('company_id', DyeBatches::company())->where('order_no', $data['order_no'])->first();
        abort_unless($order, 404);
        $cards = DyeBatch::query()->where('order_id', $order->id)->where('scope', $data['scope']);
        if (! (clone $cards)->exists()) {
            throw ValidationException::withMessages(['order_no' => __('dyehouse.prod.v.no_cards', ['no' => $order->order_no])]);
        }
        if (DyeOrderClosing::query()->where('order_id', $order->id)->where('scope', $data['scope'])->exists()) {
            throw ValidationException::withMessages(['order_no' => __('dyehouse.prod.v.closed_already', ['no' => $order->order_no])]);
        }
        $running = DB::table('dye_production_loads')->join('dye_productions', 'dye_productions.id', '=', 'dye_production_loads.production_id')
            ->whereIn('dye_productions.batch_id', (clone $cards)->select('id'))->whereIn('dye_production_loads.status', ['LOADED', 'RUNNING'])
            ->join('dye_batches', 'dye_batches.id', '=', 'dye_productions.batch_id')->distinct()->pluck('dye_batches.batch_no');
        if ($running->isNotEmpty()) {
            throw ValidationException::withMessages(['order_no' => __('dyehouse.prod.v.still_running', ['no' => $order->order_no, 'batches' => $running->implode(', ')])]);
        }
        DB::transaction(function () use ($cards, $order, $data): void {
            (clone $cards)->where('status', 'OPEN')->update(['status' => 'CLOSED']);
            DyeOrderClosing::query()->create(['order_id' => $order->id, 'scope' => $data['scope'], 'remarks' => ($data['remarks'] ?? '') ?: null, 'closed_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok(['order_no' => $order->order_no, 'scope' => $data['scope'], 'status' => 'CLOSED'], [], 201);
    }
}
