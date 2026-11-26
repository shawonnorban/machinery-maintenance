<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitDirectBooking;
use App\Modules\Knitting\Models\KnitDirectBookingLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\KnitSamples;
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
 * Knit Production → Production (AnnotechERP 15/04) → Direct Knit Fabric
 * Booking, as Brickland works it: fabric booked straight against an order
 * with no knitting programme behind it (DFB-…) — the production type, the
 * buyer and order, and one line per fabric and shade, described in the
 * floor's own words as well as picked from the type list. Saving writes a
 * draft; Submit and Approve are on the register — approving releases it to
 * the fabric store and its lines stop being editable.
 */
class KnitDirectBookingApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok(KnitSamples::options() + [
            'bookings' => KnitDirectBooking::query()->orderByDesc('prog_date')->orderByDesc('booking_no')->limit(500)->get(['booking_no', 'prog_date'])
                ->map(fn (KnitDirectBooking $b) => ['value' => $b->booking_no, 'label' => $b->booking_no.' — '.$b->prog_date->format('d M Y')])->values()->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'category' => ['nullable', 'string', 'max:16'], 'buyer' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(KnitDirectBooking::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rows = KnitDirectBooking::query()
            ->when(! empty($f['category']), fn ($q) => $q->where('category_code', $f['category']))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('prog_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('prog_date', '<=', $f['to']))
            ->orderByDesc('prog_date')->orderByDesc('booking_no')->limit(300)->get();

        return ApiResponse::ok($this->register($rows));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.db.v.fixed', ['no' => $row->booking_no, 'status' => __("knitting.db.status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'category_code' => ['required', Rule::in(array_column(KnitProduction::categories(), 'value'))],
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'prog_date' => ['required', 'date'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'lines.*.fabric' => ['nullable', 'string', 'max:160'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.colour_name' => ['nullable', 'string', 'max:80'], 'lines.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.finish_dia' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'lines.*.dia_unit' => ['nullable', Rule::in(['INCH', 'CM'])],
            'lines.*.width_type' => ['nullable', Rule::in(['OPEN', 'TUBE'])], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.required_on' => ['nullable', 'date'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'category_code.required' => __('knitting.db.v.category'), 'buyer_id.required' => __('knitting.db.v.buyer'), 'order_id.required' => __('knitting.db.v.order'),
            'prog_date.required' => __('knitting.db.v.date'), 'lines.*.qty.required' => __('knitting.db.v.qty'), 'lines.*.qty.gt' => __('knitting.db.v.qty'),
            'lines.*.uom.required' => __('knitting.db.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.db.v.no_lines')]);
        }
        foreach ($lines as $i => $l) {
            if (empty($l['fabric_type_id']) && empty(trim((string) ($l['fabric'] ?? '')))) {
                throw ValidationException::withMessages(["lines.{$i}.fabric" => __('knitting.db.v.fabric', ['n' => $i + 1])]);
            }
        }
        $order = Order::query()->findOrFail($data['order_id']);
        if ($order->buyer_id !== $data['buyer_id']) {
            throw ValidationException::withMessages(['order_id' => __('knitting.db.v.order_buyer')]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $order): KnitDirectBooking {
            $head = ['category_code' => $data['category_code'], 'buyer_id' => $data['buyer_id'], 'style_id' => ($data['style_id'] ?? null) ?: $order->style_id,
                'order_id' => $data['order_id'], 'prog_date' => $data['prog_date'], 'remarks' => $data['remarks'] ?? null];
            $row ??= KnitDirectBooking::query()->create($head + [
                'booking_no' => KnitProduction::nextNumber('DFB', 'knit_direct_bookings', 'booking_no', $data['prog_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitDirectBookingLine::query()->where('booking_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitDirectBookingLine::query()->create(['company_id' => $row->company_id, 'booking_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom'])]
                    + collect($l)->only(['fabric_type_id', 'fabric', 'colour_id', 'colour_name', 'gsm', 'finish_dia', 'dia_unit', 'width_type', 'required_on', 'remarks'])
                        ->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** Submit (Draft → Submitted), Approve (Submitted → Approved: released to the fabric store), Cancel. */
    public function transition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'approve' => [['SUBMITTED'], 'APPROVED'],
            'cancel' => [['DRAFT', 'SUBMITTED'], 'CANCELLED'],
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.db.v.cannot', ['no' => $row->booking_no, 'status' => __("knitting.db.status.{$row->status}"), 'action' => __("knitting.db.action.{$action}")])]);
        }
        $row->update(['status' => $to] + ($action === 'approve' ? ['decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->register(collect([$row->refresh()]))[0]);
    }

    private function find(string $no): KnitDirectBooking
    {
        return KnitDirectBooking::query()->where('booking_no', $no)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function register(Collection $rows): array
    {
        $lines = KnitDirectBookingLine::query()->whereIn('booking_id', $rows->pluck('id'))->get()->groupBy('booking_id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id'))->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $styles = DB::table('merch_styles')->whereIn('id', $rows->pluck('style_id')->filter())->pluck('style_ref', 'id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id'))->get()->keyBy('id');
        $categories = collect(KnitProduction::categories())->mapWithKeys(fn ($c) => [$c['value'] => preg_replace('/\s*\([^)]*\)$/', '', $c['label'])]);

        return $rows->map(fn (KnitDirectBooking $b) => [
            'booking_no' => $b->booking_no, 'prog_date' => $b->prog_date->toDateString(), 'category_code' => $b->category_code, 'category' => $categories[$b->category_code] ?? $b->category_code,
            'buyer_id' => $b->buyer_id, 'buyer' => $buyers[$b->buyer_id] ?? null, 'style' => $styles[$b->style_id] ?? null,
            'order' => isset($orders[$b->order_id]) ? trim(($orders[$b->order_id]->work_style_no ?: $orders[$b->order_id]->style_no ?: $orders[$b->order_id]->job_no).' / '.$orders[$b->order_id]->order_no, ' /') : null,
            'lines' => ($lines[$b->id] ?? collect())->count(), 'qty' => round((float) ($lines[$b->id] ?? collect())->sum('qty'), 3), 'remarks' => $b->remarks, 'status' => $b->status,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitDirectBooking $b): array
    {
        $lines = KnitDirectBookingLine::query()->where('booking_id', $b->id)->orderBy('sort_order')->get();
        $types = DB::table('fabric_types')->whereIn('id', $lines->pluck('fabric_type_id')->filter())->pluck('code', 'id');
        $colours = DB::table('colours')->whereIn('id', $lines->pluck('colour_id')->filter())->pluck('name', 'id');
        $order = Order::query()->find($b->order_id);

        return array_merge($this->register(collect([$b]))[0], [
            'id' => $b->id, 'style_id' => $b->style_id, 'order_id' => $b->order_id, 'order_no' => $order?->order_no, 'job' => $order ? ($order->work_style_no ?: $order->style_no ?: $order->job_no) : null,
            'company' => DB::table('companies')->where('id', $b->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitDirectBookingLine $l) => collect($l->only(['fabric_type_id', 'fabric', 'colour_id', 'colour_name', 'gsm', 'finish_dia', 'dia_unit', 'width_type',
                'qty', 'uom', 'remarks']))->all() + [
                    'required_on' => $l->required_on?->toDateString(), 'fabric_type' => $types[$l->fabric_type_id] ?? null, 'colour' => $colours[$l->colour_id] ?? null,
                ])->values()->all(),
        ]);
    }
}
