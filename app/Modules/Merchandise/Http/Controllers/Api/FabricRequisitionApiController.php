<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\DyeingLossBand;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * Fabric Booking → Additional Fabric Booking Entry (AnnotechERP 06/11), as
 * Brickland works it: a requisition keyed by its header — booking date,
 * style, job / order, buyer account, work style, reference, SP#, ship date,
 * status, send-mail and remarks — numbered BK-ADD-00001 and revised on each
 * later save; and the Booking Items panel, where the fabric lines of any
 * booking are added (one at a time, at once) and adjusted in place. A line
 * there is a plain fabric, colour, GSM, dia, quantity, unit, rate and
 * process loss; the loss defaults from the dyeing process loss norms when
 * the colour's group and the GSM fall in a band. A bulk booking's lines are
 * derived on Bulk Fabric Booking Entry, so the panel only shows them.
 *
 * Purchase Buffer Booking is the same requisition, numbered BK-BUF-00001,
 * listed by buyer, style and reference, and removable from its Records.
 */
class FabricRequisitionApiController extends ApiController
{
    /** The booking types keyed as a requisition, header first and lines on the panel. */
    private const TYPES = ['ADDITIONAL', 'BUFFER'];

    public function options(): JsonResponse
    {
        $this->allow('merchandise.booking.view');

        return ApiResponse::ok([
            'company' => Company::query()->whereKey($this->caller()->companyId)->value('name'),
            'styles' => Style::query()->orderBy('style_ref')->get()->map(fn (Style $s): array => ['value' => $s->id, 'label' => "{$s->styleNo()} — {$s->description}", 'buyer_office_id' => $s->buyer_office_id])->values()->all(),
            'orders' => Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('order_no')->get(['id', 'order_no', 'style_id', 'style_no', 'work_style_no', 'buyer_office_id', 'ship_date'])
                ->map(fn (Order $o): array => [
                    'value' => $o->id, 'label' => "{$o->order_no} · {$o->style_no} (".($o->work_style_no ?: $o->style_no).')', 'style_id' => $o->style_id,
                    'buyer_office_id' => $o->buyer_office_id, 'work_style' => $o->work_style_no ?: $o->style_no, 'ship_date' => $o->ship_date?->toDateString(),
                ])->values()->all(),
            'offices' => BuyerOffice::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name])->values()->all(),
            'statuses' => FabricBooking::STATUSES,
        ]);
    }

    /** Records: the requisitions of the type. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $rows = FabricBooking::query()->where('booking_type', $this->typeOf($request))->orderByDesc('booking_date')->orderByDesc('booking_no')->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');
        $styles = Style::query()->whereIn('id', $rows->pluck('style_id')->filter())->get()->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $rows->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (FabricBooking $b) use ($orders, $styles, $offices, $buyers): array {
            $o = $b->order_id ? ($orders[$b->order_id] ?? null) : null;
            $s = $b->style_id ? ($styles[$b->style_id] ?? null) : null;

            return [
                'id' => $b->id, 'booking_no' => $b->booking_no, 'booking_date' => $b->booking_date->toDateString(), 'revision' => $b->revision, 'ref_no' => $b->ref_no,
                'buyer_ac' => $b->buyer_office_id ? ($offices[$b->buyer_office_id] ?? null) : null,
                'work_style' => $b->work_style_no ?: ($o ? ($o->work_style_no ?: $o->style_no) : $s?->styleNo()),
                'order_no' => $o?->order_no, 'active' => $b->active, 'status' => $b->status,
                // Purchase Buffer Booking lists the buyer and the style itself.
                'buyer' => $s?->buyer_id ? ($buyers[$s->buyer_id] ?? null) : null, 'style' => $s?->styleNo(),
            ];
        })->values()->all());
    }

    public function show(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.view');

        return ApiResponse::ok($this->present($this->requisition($booking)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $type = $this->typeOf($request);
        $data = $this->validated($request);
        $row = DB::transaction(fn (): FabricBooking => FabricBooking::query()->create($this->header($data) + [
            'booking_type' => $type, 'booking_no' => $this->nextNumber($type), 'revision' => 0, 'program_by' => $this->caller()->auditUserId(),
            'created_by' => $this->caller()->auditUserId(), 'currency' => 'USD', 'conv_rate' => 1,
        ]));

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = $this->requisition($booking);
        $data = $this->validated($request);
        // Each save after the first is a new revision.
        $row->update($this->header($data, $row) + ['revision' => $row->revision + 1]);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Remove a requisition and its fabric lines (Purchase Buffer Booking's Records). */
    public function destroy(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $this->requisition($booking)->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Booking Items: every booking, of every type, with how many fabric lines it has. */
    public function bookings(): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $bookings = FabricBooking::query()->orderByDesc('id')->get(['id', 'booking_no', 'booking_type', 'status']);
        $counts = FabricBookingLine::query()->whereIn('booking_id', $bookings->pluck('id'))->select('booking_id', DB::raw('count(*) as n'))->groupBy('booking_id')->pluck('n', 'booking_id');

        return ApiResponse::ok($bookings->map(fn (FabricBooking $b): array => [
            'booking_no' => $b->booking_no, 'booking_type' => $b->booking_type, 'lines' => (int) ($counts[$b->id] ?? 0), 'editable' => $this->editable($b),
        ])->values()->all());
    }

    /** One booking's fabric lines, with what a new line is picked from. */
    public function items(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $row = FabricBooking::query()->where('booking_no', $booking)->firstOrFail();

        return ApiResponse::ok([
            'booking_no' => $row->booking_no, 'booking_type' => $row->booking_type, 'editable' => $this->editable($row), 'lines' => $this->lines($row),
            'fabrics' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($f): array => ['value' => $f->id, 'label' => $f->code ? "{$f->name} ({$f->code})" : $f->name])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name, 'name' => $c->name])->values()->all(),
        ]);
    }

    /** Add fabric line: kept at once, its process loss defaulting from the dyeing norms. */
    public function addItem(Request $request, string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = $this->editableBooking($booking);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'fabric_type_id' => ['required', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'colour_name' => ['nullable', 'string', 'max:120'],
            'gsm' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'process_loss' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'fabric_type_id.required' => __('merchandise.requisition.fabric_type_required'),
            'qty.required' => __('merchandise.requisition.qty_required'),
            'qty.gt' => __('merchandise.requisition.qty_required'),
        ]);
        $colour = isset($data['colour_id']) ? Colour::query()->whereKey($data['colour_id'])->first(['id', 'name', 'colour_group_id']) : null;
        $name = trim((string) ($data['colour_name'] ?? '')) ?: $colour?->name;
        $loss = $data['process_loss'] ?? $this->defaultLoss($data['fabric_type_id'], $colour?->colour_group_id, $data['gsm'] ?? null);
        $next = 1 + (int) FabricBookingLine::query()->where('booking_id', $row->id)->max('disp_order');
        FabricBookingLine::query()->create([
            'booking_id' => $row->id, 'fabric_kind' => 'KNIT', 'fabric_type_id' => $data['fabric_type_id'], 'fab_colour_id' => $colour?->id, 'fab_same_as_gmt' => false,
            'colour_spec' => $name, 'gsm' => $data['gsm'] ?? null, 'finish_dia' => $data['dia'] ?? null, 'finish_qty' => $data['qty'], 'uom' => UomCodes::normalize($data['uom'] ?? null) ?? 'KG',
            'rate' => $data['rate'] ?? null, 'process_loss' => $loss ?? 0, 'all_orders' => false, 'all_colours' => false, 'all_sizes' => false, 'disp_order' => $next,
        ]);

        return ApiResponse::created($this->lines($row));
    }

    /** Save: the lines as adjusted in place; a line left out is removed. */
    public function saveItems(Request $request, string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = $this->editableBooking($booking);
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:300'],
            'rows.*.id' => ['required', 'string'],
            'rows.*.colour_name' => ['nullable', 'string', 'max:120'],
            'rows.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'rows.*.dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'rows.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'rows.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
            'rows.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'rows.*.process_loss' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['rows.*.qty.required' => __('merchandise.requisition.qty_required'), 'rows.*.qty.gt' => __('merchandise.requisition.qty_required')]);
        $existing = FabricBookingLine::query()->where('booking_id', $row->id)->get()->keyBy('id');
        foreach ($data['rows'] as $r) {
            if (! isset($existing[$r['id']])) {
                throw ValidationException::withMessages(['rows' => __('merchandise.requisition.line_gone')]);
            }
        }
        DB::transaction(function () use ($data, $existing): void {
            $kept = collect($data['rows'])->keyBy('id');
            foreach ($existing as $id => $line) {
                if (! $kept->has($id)) {
                    $line->delete();

                    continue;
                }
                $r = $kept[$id];
                $line->update([
                    'colour_spec' => trim((string) ($r['colour_name'] ?? '')) ?: null, 'gsm' => $r['gsm'] ?? null, 'finish_dia' => $r['dia'] ?? null, 'finish_qty' => $r['qty'],
                    'uom' => UomCodes::normalize($r['uom'] ?? null) ?? 'KG', 'rate' => $r['rate'] ?? null, 'process_loss' => $r['process_loss'] ?? 0,
                ]);
            }
        });

        return ApiResponse::ok($this->lines($row));
    }

    private function typeOf(Request $request): string
    {
        $type = strtoupper((string) $request->input('type', 'ADDITIONAL'));

        return in_array($type, self::TYPES, true) ? $type : 'ADDITIONAL';
    }

    private function requisition(string $booking): FabricBooking
    {
        return FabricBooking::query()->where('booking_no', $booking)->whereIn('booking_type', self::TYPES)->firstOrFail();
    }

    /** A bulk booking's lines are derived on its own screen, and an approved bulk booking is closed. */
    private function editable(FabricBooking $booking): bool
    {
        return $booking->booking_type !== 'BULK' && ! $booking->isLocked();
    }

    private function editableBooking(string $booking): FabricBooking
    {
        $row = FabricBooking::query()->where('booking_no', $booking)->firstOrFail();
        if (! $this->editable($row)) {
            throw ValidationException::withMessages(['booking' => __($row->booking_type === 'BULK' ? 'merchandise.requisition.bulk_read_only' : 'merchandise.requisition.locked')]);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table): Exists => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'booking_date' => ['required', 'date'],
            'style_id' => ['nullable', $in('merch_styles')],
            'order_id' => ['nullable', $in('merch_orders')],
            'buyer_office_id' => ['nullable', $in('buyer_offices')],
            'work_style_no' => ['nullable', 'string', 'max:64'],
            'ref_no' => ['nullable', 'string', 'max:64'],
            'sp_no' => ['nullable', 'string', 'max:32'],
            'ship_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(FabricBooking::STATUSES)],
            'send_mail' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['booking_date.required' => __('merchandise.requisition.date_required')]);
        if (! empty($data['order_id']) && ! empty($data['style_id']) && Order::query()->whereKey($data['order_id'])->value('style_id') !== $data['style_id']) {
            throw ValidationException::withMessages(['order_id' => __('merchandise.requisition.order_of_style')]);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function header(array $data, ?FabricBooking $row = null): array
    {
        $order = ! empty($data['order_id']) ? Order::query()->whereKey($data['order_id'])->first(['style_id', 'buyer_office_id']) : null;
        $styleId = ($data['style_id'] ?? null) ?: $order?->style_id;
        $style = $styleId ? Style::query()->whereKey($styleId)->first(['buyer_office_id']) : null;
        $status = $data['status'] ?? $row?->status ?? 'DRAFT';
        $approving = $status === 'APPROVED' && $row?->status !== 'APPROVED';
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;

        return [
            'booking_date' => $data['booking_date'], 'style_id' => $styleId, 'order_id' => $data['order_id'] ?? null,
            // The buyer account follows the style (or the order) when none is picked.
            'buyer_office_id' => ($data['buyer_office_id'] ?? null) ?: ($style?->buyer_office_id ?? $order?->buyer_office_id),
            'work_style_no' => $blank('work_style_no'), 'ref_no' => $blank('ref_no'), 'sp_no' => $blank('sp_no'), 'ship_date' => $data['ship_date'] ?? null,
            'status' => $status, 'send_mail' => (bool) ($data['send_mail'] ?? false), 'active' => (bool) ($data['active'] ?? $row?->active ?? true), 'remarks' => $blank('remarks'),
        ] + ($approving ? ['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()] : []) + ($status !== 'APPROVED' ? ['approved_at' => null, 'approved_by' => null] : []);
    }

    /**
     * The dyeing process loss for the fabric, the colour's group and the GSM: a band for the fabric itself
     * before one for every fabric. Null when no band matches.
     */
    private function defaultLoss(string $fabricTypeId, ?string $colourGroupId, mixed $gsm): ?float
    {
        if ($colourGroupId === null || $gsm === null || $gsm === '') {
            return null;
        }
        $group = strtoupper(str_replace([' ', '-'], '_', trim((string) DyeColourGroup::query()->whereKey($colourGroupId)->value('name'))));
        $band = DyeingLossBand::query()->where('active', true)->where('color_group', $group)
            ->where('weight_low', '<=', $gsm)->where('weight_high', '>=', $gsm)
            ->where(fn ($q) => $q->where('fabric_type_id', $fabricTypeId)->orWhereNull('fabric_type_id'))
            ->orderByRaw('fabric_type_id is null')->first();

        return $band ? (float) $band->loss_percent : null;
    }

    /** @return list<array<string, mixed>> */
    private function lines(FabricBooking $row): array
    {
        $lines = FabricBookingLine::query()->where('booking_id', $row->id)->orderBy('disp_order')->get();
        $fabrics = FabricType::query()->whereIn('id', $lines->pluck('fabric_type_id'))->pluck('name', 'id');

        return $lines->map(fn (FabricBookingLine $l): array => [
            'id' => $l->id, 'fabric' => $fabrics[$l->fabric_type_id] ?? null, 'colour_name' => $l->colour_spec, 'gsm' => $l->gsm, 'dia' => $l->finish_dia,
            'qty' => $l->finish_qty, 'uom' => UomCodes::normalize($l->uom ?: ($row->booking_type === 'BULK' ? $l->cons_unit : 'KG')), 'rate' => $l->rate, 'process_loss' => $l->process_loss,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(FabricBooking $row): array
    {
        return [
            'id' => $row->id, 'booking_type' => $row->booking_type, 'booking_no' => $row->booking_no, 'revision' => $row->revision, 'booking_date' => $row->booking_date->toDateString(),
            'style_id' => $row->style_id, 'order_id' => $row->order_id, 'buyer_office_id' => $row->buyer_office_id, 'work_style_no' => $row->work_style_no, 'ref_no' => $row->ref_no,
            'sp_no' => $row->sp_no, 'ship_date' => $row->ship_date?->toDateString(), 'status' => $row->status, 'send_mail' => $row->send_mail, 'active' => $row->active,
            'remarks' => $row->remarks, 'approved_at' => $row->approved_at?->toIso8601String(),
        ];
    }

    /** BK-ADD-00001 …: the company's next booking of the type; held to the end of the save. */
    private function nextNumber(string $type): string
    {
        $head = FabricBooking::PREFIXES[$type];
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_fabric_bookings:'.$type.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) FabricBooking::query()->where('booking_type', $type)->pluck('booking_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
