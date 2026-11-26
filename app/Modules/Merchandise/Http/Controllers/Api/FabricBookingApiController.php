<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\CadRecord;
use App\Modules\Merchandise\Models\CadSize;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleItem;
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
 * Fabric Booking → Bulk Fabric Booking Entry (AnnotechERP 06/11), as Brickland
 * works it: the order keyed once (buyer account, job / working style, order,
 * currency, item / model and the wastages), then each dia-wise consumption
 * paired with the garment item, colour and size range it applies to. A
 * booking is saved whole — at least one line — numbered BFB-00001 on its
 * first save; each later save is a new revision. A line's pieces are the
 * order's quantity in the colour and size range it applies to (every order
 * of the style when "All?" is ticked), and its finished fabric quantity is
 * the consumption over those pieces plus the line's cutting allowance and
 * any additional quantity. Suggest reads the style's CAD consumption.
 *
 * A bulk booking is approved through the Approval Center (Submit): while
 * it waits, and once approved, it is not changed or removed. The Approved
 * Fabric Booking List reads the approved bookings of every type.
 */
class FabricBookingApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $styles = Style::query()->orderBy('style_ref')->get();
        $items = StyleItem::query()->whereIn('style_id', $styles->pluck('id'))->orderBy('sort_order')->get(['style_id', 'model_no', 'full_name']);

        return ApiResponse::ok([
            'me' => ['id' => $this->caller()->auditUserId(), 'name' => $this->caller()->label()],
            'offices' => BuyerOffice::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name])->values()->all(),
            // Brickland's "CA-6627 — LADIES S/S T-SHIRT".
            'styles' => $styles->map(fn (Style $s): array => [
                'value' => $s->id, 'label' => "{$s->styleNo()} — {$s->description}", 'description' => $s->description, 'buyer_office_id' => $s->buyer_office_id,
                'items' => $items->where('style_id', $s->id)->map(fn ($i): string => trim(($i->model_no ? "{$i->model_no} · " : '').($i->full_name ?? '')))->filter()->values()->all(),
            ])->values()->all(),
            'orders' => Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('order_no')->get(['id', 'order_no', 'style_id', 'style_no', 'work_style_no', 'currency', 'exchange_rate', 'buyer_office_id'])
                ->map(fn (Order $o): array => [
                    'value' => $o->id, 'label' => "{$o->order_no} · {$o->style_no} (".($o->work_style_no ?: $o->style_no).')', 'style_id' => $o->style_id,
                    'currency' => $o->currency, 'rate' => $o->exchange_rate, 'buyer_office_id' => $o->buyer_office_id,
                ])->values()->all(),
            'fabrics' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($f): array => ['value' => $f->id, 'label' => "{$f->name} ({$f->code})"])->values()->all(),
            'parts' => GarmentPart::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name])->values()->all(),
            'size_groups' => Size::GROUPS,
            'currencies' => FabricBooking::CURRENCIES, 'kinds' => FabricBookingLine::KINDS, 'dia_units' => FabricBookingLine::DIA_UNITS, 'dia_types' => FabricBookingLine::DIA_TYPES,
            'cons_units' => FabricBookingLine::CONS_UNITS, 'cons_per' => FabricBookingLine::CONS_PER,
        ]);
    }

    /** Records: every booking of the type, with its order, revision and finished fabric total. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $type = $this->typeOf($request);
        $bookings = FabricBooking::query()->where('booking_type', $type)->orderByDesc('booking_date')->orderByDesc('booking_no')->get();
        $orders = Order::query()->whereIn('id', $bookings->pluck('order_id')->filter())->get(['id', 'order_no', 'style_no', 'work_style_no', 'ship_date'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $bookings->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $lines = FabricBookingLine::query()->whereIn('booking_id', $bookings->pluck('id'))->get(['booking_id', 'finish_qty', 'cons_unit', 'uom'])->groupBy('booking_id');

        return ApiResponse::ok($bookings->map(function (FabricBooking $b) use ($orders, $offices, $lines): array {
            $o = $b->order_id ? ($orders[$b->order_id] ?? null) : null;

            return [
                'id' => $b->id, 'booking_no' => $b->booking_no, 'booking_date' => $b->booking_date->toDateString(), 'revision' => $b->revision, 'status' => $b->status,
                'work_style' => $o ? ($o->work_style_no ?: $o->style_no) : null, 'order_no' => $o?->order_no, 'ship_date' => $o?->ship_date?->toDateString(),
                'buyer_ac' => $b->buyer_office_id ? ($offices[$b->buyer_office_id] ?? null) : null,
                'items' => ($lines[$b->id] ?? collect())->count(), ...FabricBookingLine::unitTotals($lines[$b->id] ?? collect()),
            ];
        })->values()->all());
    }

    public function show(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.view');

        return ApiResponse::ok($this->present(FabricBooking::query()->where('booking_no', $booking)->firstOrFail()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $type = $this->typeOf($request);
        $data = $this->validated($request);
        $row = DB::transaction(function () use ($data, $type): FabricBooking {
            $row = FabricBooking::query()->create($this->header($data) + [
                'booking_type' => $type, 'booking_no' => $this->nextNumber($type), 'revision' => 0, 'program_by' => $this->caller()->auditUserId(),
                'created_by' => $this->caller()->auditUserId(),
            ] + ($this->statusOf($type, $data) ?: ['status' => 'DRAFT']));
            $this->writeLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = FabricBooking::query()->where('booking_no', $booking)->firstOrFail();
        $this->assertUnlocked($row);
        $data = $this->validated($request);
        DB::transaction(function () use ($row, $data): void {
            // Each save after the first is a new revision of the booking.
            $row->update($this->header($data) + ['revision' => $row->revision + 1] + $this->statusOf($row->booking_type, $data));
            $this->writeLines($row, $data['lines']);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = FabricBooking::query()->where('booking_no', $booking)->firstOrFail();
        $this->assertUnlocked($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /**
     * Submit: the booking goes to the Approval Center. A draft, or a rejected booking keyed again,
     * may be sent; with no fabric booking chain configured it is approved at once.
     */
    public function submit(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = FabricBooking::query()->where('booking_no', $booking)->firstOrFail();
        if ($row->booking_type !== 'BULK' || ! in_array($row->status, ['DRAFT', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['booking' => __('merchandise.booking.not_submittable')]);
        }
        DB::transaction(function () use ($row): void {
            $row->forceFill(['status' => 'SUBMITTED', 'approval_status' => null, 'approval_request_id' => null])->save();
            app(RequestApproval::class)->forEntity($row, $this->caller()->auditUserId());
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Approved Fabric Booking List: the approved bookings of every type, newest approval first. */
    public function approved(): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $bookings = FabricBooking::query()->where('status', 'APPROVED')->orderByDesc('approved_at')->orderByDesc('booking_no')->get();
        $orders = Order::query()->whereIn('id', $bookings->pluck('order_id')->filter())->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no', 'ship_date'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $bookings->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $styles = Style::query()->whereIn('id', $bookings->pluck('style_id')->filter())->get()->keyBy('id');

        return ApiResponse::ok($bookings->map(function (FabricBooking $b) use ($orders, $offices, $styles): array {
            $o = $b->order_id ? ($orders[$b->order_id] ?? null) : null;
            $s = $b->style_id ? ($styles[$b->style_id] ?? null) : null;

            return [
                'id' => $b->id, 'booking_type' => $b->booking_type, 'booking_no' => $b->booking_no, 'sp_no' => $b->sp_no, 'revision' => $b->revision,
                'approved_at' => $b->approved_at?->toDateString(),
                // Brickland's "Prg/Rev Date": the booking's date, or the day of its last revision.
                'rev_date' => $b->revision > 0 ? $b->updated_at?->toDateString() : $b->booking_date->toDateString(),
                'ship_date' => $o?->ship_date?->toDateString(),
                'buyer_ac' => $b->buyer_office_id ? ($offices[$b->buyer_office_id] ?? null) : null,
                'style' => $s?->styleNo() ?? ($o ? ($o->work_style_no ?: $o->style_no) : null),
                'job_no' => $o?->job_no, 'order_no' => $o?->order_no,
                'send_mail' => $b->send_mail, 'mail_sent_at' => $b->mail_sent_at?->toDateString(),
            ];
        })->values()->all());
    }

    /** Suggest: the style's CAD consumption, part by part — the average over its sizes. */
    public function suggest(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $data = $request->validate(['style_id' => ['required', Rule::exists('merch_styles', 'id')->where('company_id', $this->caller()->companyId)]]);
        $record = CadRecord::query()->where('style_id', $data['style_id'])->latest('record_date')->latest()->first();
        $sizes = $record ? CadSize::query()->where('cad_id', $record->id)->get() : collect();
        if ($sizes->isEmpty()) {
            return ApiResponse::ok(['record' => null, 'parts' => []]);
        }
        $parts = GarmentPart::query()->whereIn('id', $sizes->pluck('garment_part_id')->filter())->get(['id', 'name', 'is_body'])->keyBy('id');

        return ApiResponse::ok([
            'record' => $record->job_no,
            'parts' => $sizes->groupBy(fn (CadSize $s): string => (string) $s->garment_part_id)->map(fn ($rows, string $part): array => [
                'garment_part_id' => $part !== '' ? $part : null, 'part' => $part !== '' ? ($parts[$part]->name ?? null) : null, 'is_body' => $part !== '' && (bool) ($parts[$part]->is_body ?? false),
                'consumption' => round((float) $rows->avg('consumption'), 4), 'uom' => $rows->first()->uom,
            ])->values()->all(),
        ]);
    }

    private function assertUnlocked(FabricBooking $row): void
    {
        if ($row->isLocked()) {
            throw ValidationException::withMessages(['booking' => __('merchandise.booking.locked', ['status' => __('merchandise.master.bfb.status.'.$row->status)])]);
        }
    }

    /**
     * A bulk booking's status is the approval's to move; the other types carry a Status field on their form,
     * and one saved as Approved is stamped with who and when.
     *
     * @return array<string, mixed>
     */
    private function statusOf(string $type, array $data): array
    {
        if ($type === 'BULK' || ! isset($data['status'])) {
            return [];
        }

        return ['status' => $data['status']] + ($data['status'] === 'APPROVED' ? ['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()] : []);
    }

    private function typeOf(Request $request): string
    {
        $type = strtoupper((string) $request->input('type', 'BULK'));

        return in_array($type, FabricBooking::TYPES, true) ? $type : 'BULK';
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table): Exists => Rule::exists($table, 'id')->where('company_id', $company);

        return $request->validate([
            'booking_date' => ['required', 'date'],
            'buyer_office_id' => ['nullable', $in('buyer_offices')],
            'style_id' => ['required', $in('merch_styles')],
            'order_id' => ['required', $in('merch_orders')],
            'currency' => ['required', Rule::in(FabricBooking::CURRENCIES)],
            'conv_rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'item_model' => ['required', 'string', 'max:96'],
            'cutting_wastage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'yd_wastage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aop_wastage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(FabricBooking::STATUSES)],
            'lines' => ['required', 'array', 'min:1', 'max:300'],
            'lines.*.fabric_kind' => ['required', Rule::in(FabricBookingLine::KINDS)],
            'lines.*.fabric_type_id' => ['required', $in('fabric_types')],
            'lines.*.garment_part_id' => ['nullable', $in('garment_parts')],
            'lines.*.finish_dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.dia_unit' => ['required', Rule::in(FabricBookingLine::DIA_UNITS)],
            'lines.*.dia_type' => ['required', Rule::in(FabricBookingLine::DIA_TYPES)],
            'lines.*.consumption' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'lines.*.cons_unit' => ['required', Rule::in(FabricBookingLine::CONS_UNITS)],
            'lines.*.cons_per' => ['required', Rule::in(FabricBookingLine::CONS_PER)],
            'lines.*.all_orders' => ['boolean'],
            'lines.*.gmt_style_id' => ['required', $in('merch_styles')],
            'lines.*.all_colours' => ['boolean'],
            'lines.*.gmt_colour_id' => ['nullable', 'required_unless:lines.*.all_colours,true', $in('colours')],
            'lines.*.all_sizes' => ['boolean'],
            'lines.*.size_group' => ['nullable', 'required_unless:lines.*.all_sizes,true', Rule::in(Size::GROUPS)],
            'lines.*.fab_same_as_gmt' => ['boolean'],
            'lines.*.fab_colour_id' => ['nullable', 'required_unless:lines.*.fab_same_as_gmt,true', $in('colours')],
            'lines.*.colour_spec' => ['nullable', 'string', 'max:160'],
            'lines.*.contrast' => ['boolean'],
            'lines.*.cutting_wastage' => ['required', 'numeric', 'min:0', 'max:100'],
            'lines.*.add_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.disp_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'lines.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.process_loss' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'booking_date.required' => __('merchandise.booking.date_required'),
            'style_id.required' => __('merchandise.booking.style_required'),
            'order_id.required' => __('merchandise.booking.order_required'),
            'item_model.required' => __('merchandise.booking.item_required'),
            'lines.required' => __('merchandise.booking.lines_required'),
            'lines.min' => __('merchandise.booking.lines_required'),
            'lines.*.fabric_type_id.required' => __('merchandise.booking.fabric_required'),
            'lines.*.consumption.required' => __('merchandise.booking.cons_required'),
            'lines.*.gmt_style_id.required' => __('merchandise.booking.gmt_item_required'),
            'lines.*.gmt_colour_id.required_unless' => __('merchandise.booking.gmt_colour_required'),
            'lines.*.size_group.required_unless' => __('merchandise.booking.size_required'),
            'lines.*.fab_colour_id.required_unless' => __('merchandise.booking.fab_colour_required'),
        ]);
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        $style = Style::query()->whereKey($data['style_id'])->first();

        return [
            'booking_date' => $data['booking_date'], 'style_id' => $data['style_id'], 'order_id' => $data['order_id'],
            // The buyer account follows the style when none is picked.
            'buyer_office_id' => $data['buyer_office_id'] ?? $style?->buyer_office_id,
            'currency' => $data['currency'], 'conv_rate' => $data['conv_rate'], 'item_model' => trim($data['item_model']),
            'cutting_wastage' => $data['cutting_wastage'] ?? 0, 'yd_wastage' => $data['yd_wastage'] ?? 0, 'aop_wastage' => $data['aop_wastage'] ?? 0,
        ];
    }

    private function writeLines(FabricBooking $row, array $lines): void
    {
        FabricBookingLine::query()->where('booking_id', $row->id)->delete();
        foreach (array_values($lines) as $i => $l) {
            $values = [
                'booking_id' => $row->id, 'fabric_kind' => $l['fabric_kind'], 'fabric_type_id' => $l['fabric_type_id'], 'garment_part_id' => $l['garment_part_id'] ?? null,
                'finish_dia' => $l['finish_dia'] ?? null, 'dia_unit' => $l['dia_unit'], 'dia_type' => $l['dia_type'], 'consumption' => $l['consumption'],
                'cons_unit' => $l['cons_unit'], 'cons_per' => $l['cons_per'], 'all_orders' => (bool) ($l['all_orders'] ?? false), 'gmt_style_id' => $l['gmt_style_id'],
                'all_colours' => (bool) ($l['all_colours'] ?? false), 'gmt_colour_id' => ($l['all_colours'] ?? false) ? null : ($l['gmt_colour_id'] ?? null),
                'all_sizes' => (bool) ($l['all_sizes'] ?? false), 'size_group' => ($l['all_sizes'] ?? false) ? null : ($l['size_group'] ?? null),
                'fab_same_as_gmt' => (bool) ($l['fab_same_as_gmt'] ?? false), 'fab_colour_id' => ($l['fab_same_as_gmt'] ?? false) ? null : ($l['fab_colour_id'] ?? null),
                'colour_spec' => isset($l['colour_spec']) && trim($l['colour_spec']) !== '' ? trim($l['colour_spec']) : null, 'contrast' => (bool) ($l['contrast'] ?? false),
                'cutting_wastage' => $l['cutting_wastage'], 'add_qty' => $l['add_qty'] ?? 0, 'disp_order' => $l['disp_order'] ?? ($i + 1),
                'gsm' => $l['gsm'] ?? null, 'rate' => $l['rate'] ?? null, 'process_loss' => $l['process_loss'] ?? null,
            ];
            $pieces = $this->piecesFor($row, $values);
            $perPiece = (float) $values['consumption'] / ($values['cons_per'] === 'DZ' ? 12 : 1);
            $values['pieces'] = $pieces;
            $values['finish_qty'] = round($perPiece * $pieces * (1 + (float) $values['cutting_wastage'] / 100) + (float) $values['add_qty'], 3);
            FabricBookingLine::query()->create($values);
        }
    }

    /**
     * The pieces a line applies to: the booking's order (or every order of the line's style when "All?" is
     * ticked), in the line's colour and size range. An order without a colour / size breakdown counts whole.
     */
    public static function piecesFor(FabricBooking $booking, array $line, ?string $onlyOrder = null): float
    {
        $orders = $line['all_orders']
            ? Order::query()->where('style_id', $line['gmt_style_id'])->where('status', '!=', 'CANCELLED')->get()
            : Order::query()->whereKey($booking->order_id)->get();
        // Only one order's share — what a budget sheet of that order takes of the line.
        if ($onlyOrder !== null) {
            $orders = $orders->where('id', $onlyOrder);
        }
        $colour = $line['gmt_colour_id'] ? Colour::query()->whereKey($line['gmt_colour_id'])->first(['name', 'code']) : null;
        $sizes = $line['size_group'] ? Size::query()->where('size_group', $line['size_group'])->pluck('code')->map(fn ($c): string => mb_strtoupper($c))->all() : null;
        $total = 0.0;
        foreach ($orders as $o) {
            $perUnit = $o->unit === 'PCS' ? 1 : max(1, (int) $o->pcs_per_unit);
            $breakdown = OrderSize::query()->where('order_id', $o->id)->get(['colour', 'size', 'qty']);
            if ($breakdown->isEmpty()) {
                $total += ($colour === null && $sizes === null) ? (float) $o->total_qty * $perUnit : 0;

                continue;
            }
            foreach ($breakdown as $b) {
                $colourOk = $colour === null || in_array(mb_strtoupper((string) $b->colour), [mb_strtoupper($colour->name), mb_strtoupper((string) $colour->code)], true);
                $sizeOk = $sizes === null || in_array(mb_strtoupper((string) $b->size), $sizes, true);
                if ($colourOk && $sizeOk) {
                    $total += (float) $b->qty * $perUnit;
                }
            }
        }

        return $total;
    }

    /** @return array<string, mixed> */
    private function present(FabricBooking $row): array
    {
        $lines = FabricBookingLine::query()->where('booking_id', $row->id)->orderBy('disp_order')->get();

        return [
            'id' => $row->id, 'booking_type' => $row->booking_type, 'booking_no' => $row->booking_no, 'revision' => $row->revision, 'status' => $row->status,
            'approval_status' => $row->approval_status, 'approved_at' => $row->approved_at?->toIso8601String(),
            'approved_by' => $row->approved_by ? User::query()->whereKey($row->approved_by)->value('name') : null, 'locked' => $row->isLocked(),
            'booking_date' => $row->booking_date->toDateString(), 'program_by' => $row->program_by ? User::query()->whereKey($row->program_by)->value('name') : null,
            'buyer_office_id' => $row->buyer_office_id, 'style_id' => $row->style_id, 'order_id' => $row->order_id, 'currency' => $row->currency, 'conv_rate' => $row->conv_rate,
            'item_model' => $row->item_model, 'cutting_wastage' => $row->cutting_wastage, 'yd_wastage' => $row->yd_wastage, 'aop_wastage' => $row->aop_wastage,
            'lines' => $lines->map(fn (FabricBookingLine $l): array => collect($l->only($l->getFillable()))->except(['company_id', 'booking_id'])->all() + ['id' => $l->id])->values()->all(),
        ];
    }

    /** BFB-00001 (bulk), BK-BUF-00001 (buffer) …: the company's next booking of the type; held to the end of the save. */
    private function nextNumber(string $type): string
    {
        $head = FabricBooking::PREFIXES[$type];
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_fabric_bookings:'.$type.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) FabricBooking::query()->where('booking_type', $type)->pluck('booking_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
