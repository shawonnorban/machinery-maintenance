<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Prototype\Models\Bundle;
use App\Modules\Prototype\Models\BundleCard;
use App\Modules\Prototype\Models\BundleMovement;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleLedger;
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
 * Prototype Desk → Sample Bundle Tracking (AnnotechERP 07/03): bundles of a
 * finalized card scanned through the sample room's processes — each scan
 * recorded on its own, nothing to save. A bundle goes into each process
 * once; one sent for embroidery is not also sent for print (and print and
 * embroidery together excludes both); only a bundle sent out is received
 * back, and one still out is not issued to sewing; sewing done needs it
 * issued to sewing, distribution needs it sewn. The first scan puts the card
 * in production, the last bundle distributed completes it. Undo withdraws a
 * movement until the bundle has moved on. Tracking Update records each
 * movement's operator, times, expected hours, status and delay reason; the
 * Sample Card follows an order's cards to delivered and rejected.
 */
class BundleTrackingApiController extends ApiController
{
    /** What a process needs of the bundle first: [one of these processes done, none of these]. */
    private const NEEDS = [
        'DELV_PRINT' => [[], ['DELV_EMBR', 'DELV_PRINT_EMBR']],
        'DELV_EMBR' => [[], ['DELV_PRINT', 'DELV_PRINT_EMBR']],
        'DELV_PRINT_EMBR' => [[], ['DELV_PRINT', 'DELV_EMBR']],
        'RECV_EMBELLISHMENT' => [BundleMovement::SENT, []],
        'DELV_SEWING' => [[], []],
        'SEWING_DONE' => [['DELV_SEWING'], []],
        'DISTRIBUTION' => [['SEWING_DONE'], []],
    ];

    public function options(): JsonResponse
    {
        $this->allow('prototype.tracking.view');

        return ApiResponse::ok([
            'processes' => BundleMovement::PROCESSES, 'statuses' => BundleMovement::STATUSES, 'bundle_statuses' => BundleCard::BUNDLE_STATUSES, 'users' => Prototype::users(),
            'months' => BundleCard::query()->get(['cutting_date'])->map(fn ($c) => $c->cutting_date->format('Y-m'))->unique()->sortDesc()->values()->all(),
        ]);
    }

    /** Records: every movement. */
    public function index(): JsonResponse
    {
        $this->allow('prototype.tracking.view');

        return ApiResponse::ok($this->present(BundleMovement::query()->orderByDesc('scanned_at')->get()));
    }

    /** Scan Desk: today's movements at a process — the same rows at every terminal — and each process's count today. */
    public function desk(string $process): JsonResponse
    {
        $this->allow('prototype.tracking.view');
        abort_unless(in_array($process, BundleMovement::PROCESSES, true), 404);
        $today = CarbonImmutable::today()->toDateString();
        $rows = BundleMovement::query()->whereDate('movement_date', $today)->where('process', $process)->orderByDesc('scanned_at')->get();

        return ApiResponse::ok([
            'movements' => $this->present($rows),
            'counts' => BundleMovement::query()->whereDate('movement_date', $today)->selectRaw('process, count(*) as n')->groupBy('process')->pluck('n', 'process'),
        ]);
    }

    /** One scan of a bundle card's code into a process. */
    public function scan(Request $request): JsonResponse
    {
        $this->allow('prototype.tracking.manage');
        $data = $request->validate([
            'process' => ['required', Rule::in(BundleMovement::PROCESSES)],
            'code' => ['required', 'string', 'max:40'],
        ], ['code.required' => __('prototype.tracking.code_required')]);
        $code = strtoupper(trim($data['code']));
        $process = $data['process'];
        $label = fn (string $p): string => __('prototype.process.'.$p);

        $row = DB::transaction(function () use ($code, $process, $label): BundleMovement {
            $bundle = Bundle::query()->where('bundle_no', $code)->lockForUpdate()->first();
            if (! $bundle) {
                throw ValidationException::withMessages(['code' => __('prototype.tracking.unknown', ['code' => $code])]);
            }
            $card = BundleCard::query()->whereKey($bundle->card_id)->lockForUpdate()->firstOrFail();
            if (! $card->is_finalized || ! in_array($card->bundle_status, BundleCard::ON_FLOOR, true)) {
                throw ValidationException::withMessages(['code' => __('prototype.tracking.card_not_open', ['card' => $card->card_no, 'status' => __('prototype.bundle_status.'.$card->bundle_status)])]);
            }
            $done = BundleMovement::query()->where('bundle_id', $bundle->id)->pluck('process')->all();
            if (in_array($process, $done, true)) {
                throw ValidationException::withMessages(['code' => __('prototype.tracking.already', ['code' => $code, 'process' => $label($process)])]);
            }
            [$needs, $excludes] = self::NEEDS[$process];
            if ($clash = array_values(array_intersect($excludes, $done))) {
                throw ValidationException::withMessages(['code' => __('prototype.tracking.excluded', ['code' => $code, 'process' => $label($clash[0])])]);
            }
            if ($needs !== [] && array_intersect($needs, $done) === []) {
                throw ValidationException::withMessages(['code' => __('prototype.tracking.needs_'.strtolower($process), ['code' => $code])]);
            }
            // A bundle still out at the embellishment house is not issued to sewing.
            if ($process === 'DELV_SEWING' && array_intersect(BundleMovement::SENT, $done) !== [] && ! in_array('RECV_EMBELLISHMENT', $done, true)) {
                throw ValidationException::withMessages(['code' => __('prototype.tracking.still_out', ['code' => $code])]);
            }
            $row = BundleMovement::query()->create([
                'bundle_id' => $bundle->id, 'card_id' => $card->id, 'process' => $process, 'movement_date' => CarbonImmutable::today(), 'scanned_at' => now(),
                'scanned_by' => $this->caller()->auditUserId(), 'qty' => $bundle->cutting_qty, 'operator_id' => $card->operator_id, 'status' => 'RECEIVED', 'received_at' => now(),
            ]);
            $bundle->update(['now_at' => $process]);
            $this->settle($card);
            SampleLedger::log($card->sample_id, 'bundle_scanned', ['bundle' => $bundle->bundle_no, 'process' => $process], $bundle->bundle_no);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]))[0]);
    }

    /** Undo withdraws a movement — not once the bundle has moved on. */
    public function undo(string $movement): JsonResponse
    {
        $this->allow('prototype.tracking.manage');
        DB::transaction(function () use ($movement): void {
            $row = BundleMovement::query()->whereKey($movement)->lockForUpdate()->firstOrFail();
            // Moved on: the bundle now stands at a later process than this one.
            if (Bundle::query()->whereKey($row->bundle_id)->value('now_at') !== $row->process) {
                throw ValidationException::withMessages(['movement' => __('prototype.tracking.moved_on')]);
            }
            $row->delete();
            $done = BundleMovement::query()->where('bundle_id', $row->bundle_id)->pluck('process')->all();
            $last = collect(BundleMovement::PROCESSES)->filter(fn (string $p) => in_array($p, $done, true))->last();
            Bundle::query()->whereKey($row->bundle_id)->update(['now_at' => $last]);
            $this->settle(BundleCard::query()->findOrFail($row->card_id));
        });

        return ApiResponse::ok(['withdrawn' => $movement]);
    }

    /** Tracking Update: a movement's operator, times, expected hours, status and delay reason. */
    public function update(Request $request, string $movement): JsonResponse
    {
        $this->allow('prototype.tracking.manage');
        $row = BundleMovement::query()->findOrFail($movement);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'operator_id' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'received_at' => ['nullable', 'date'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'expected_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(BundleMovement::STATUSES)],
            'delay_reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        if (! empty($data['start_at']) && ! empty($data['end_at']) && CarbonImmutable::parse($data['end_at'])->lt(CarbonImmutable::parse($data['start_at']))) {
            throw ValidationException::withMessages(['end_at' => __('prototype.tracking.end_before_start')]);
        }
        $row->fill($data);
        // A delayed movement says why.
        if (($data['status'] === 'DELAYED' || ($row->delayHours() ?? 0) > 0) && blank($data['delay_reason'] ?? null)) {
            throw ValidationException::withMessages(['delay_reason' => __('prototype.tracking.reason_required')]);
        }
        $row->save();

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    /** Tracking Update's left side: the bundles that have moved, each with its movements. */
    public function bundles(): JsonResponse
    {
        $this->allow('prototype.tracking.view');
        $moved = BundleMovement::query()->orderBy('scanned_at')->get()->groupBy('bundle_id');
        $bundles = Bundle::query()->whereIn('id', $moved->keys())->orderBy('bundle_no')->get();

        return ApiResponse::ok($bundles->map(fn (Bundle $b): array => [
            'bundle_no' => $b->bundle_no, 'now_at' => $b->now_at, 'qty' => $b->cutting_qty, 'movements' => $this->present($moved[$b->id]),
        ])->values()->all());
    }

    /** Bundle Lookup: every bundle with its card, sample, style, where it is and whether it is delayed. */
    public function lookup(): JsonResponse
    {
        $this->allow('prototype.tracking.view');
        $bundles = Bundle::query()->orderBy('bundle_no')->get();
        $cards = BundleCard::query()->whereIn('id', $bundles->pluck('card_id'))->get()->keyBy('id');
        $samples = Sample::query()->whereIn('id', $cards->pluck('sample_id')->filter())->pluck('sample_no', 'id');
        $styles = Style::query()->whereIn('id', $cards->pluck('style_id')->filter())->get(['id', 'style_ref', 'style_ext'])->keyBy('id');
        $movements = BundleMovement::query()->whereIn('bundle_id', $bundles->pluck('id'))->get()->groupBy('bundle_id');

        return ApiResponse::ok($bundles->map(function (Bundle $b) use ($cards, $samples, $styles, $movements): array {
            $card = $cards[$b->card_id];
            $style = $card->style_id ? ($styles[$card->style_id] ?? null) : null;
            $moves = $movements[$b->id] ?? collect();

            return [
                'bundle_no' => $b->bundle_no, 'card_no' => $card->card_no, 'sample_no' => $card->sample_id ? ($samples[$card->sample_id] ?? null) : null,
                'style' => $style ? trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')) : null, 'color' => $b->color, 'size' => $b->size, 'qty' => $b->cutting_qty,
                'now_at' => $b->now_at, 'bundle_status' => $card->bundle_status, 'cut_month' => $card->cutting_date->format('Y-m'), 'movements' => $moves->count(),
                'delayed' => $moves->contains(fn (BundleMovement $m) => $m->status === 'DELAYED' || ($m->delayHours() ?? 0) > 0),
            ];
        })->values()->all());
    }

    /** Sample Card: an order's cards — asked for, sewn, gone out — and what is delivered or rejected. */
    public function sampleCards(): JsonResponse
    {
        $this->allow('prototype.tracking.view');
        $cards = BundleCard::query()->whereNotNull('order_id')->where('bundle_status', '!=', 'CANCELLED')->orderBy('card_no')->get();
        $orders = Order::query()->whereIn('id', $cards->pluck('order_id'))->get(['id', 'order_no', 'job_no'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $cards->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $bundles = Bundle::query()->whereIn('card_id', $cards->pluck('id'))->get()->groupBy('card_id');
        $moves = BundleMovement::query()->whereIn('card_id', $cards->pluck('id'))->whereIn('process', ['SEWING_DONE', 'DISTRIBUTION'])->get()->groupBy('card_id');
        $samples = Sample::query()->whereIn('id', $cards->pluck('sample_id')->filter())->pluck('sample_no', 'id');
        $types = SampleType::query()->whereIn('id', $bundles->flatten()->pluck('sample_type_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($cards->groupBy('order_id')->map(function (Collection $list, string $orderId) use ($orders, $offices, $bundles, $moves, $samples, $types): array {
            $order = $orders[$orderId] ?? null;
            $office = $list->first()->buyer_office_id;

            return [
                'order_id' => $orderId, 'order_no' => $order?->order_no, 'job_no' => $order?->job_no,
                'label' => trim(($office ? ($offices[$office] ?? '').' — ' : '').($order?->job_no ? "{$order->job_no} · " : '').($order?->order_no ?? ''), ' —·'),
                'cards' => $list->map(fn (BundleCard $c): array => [
                    'card_no' => $c->card_no, 'sample_no' => $c->sample_id ? ($samples[$c->sample_id] ?? null) : null, 'bundle_status' => $c->bundle_status,
                    'sample_types' => ($bundles[$c->id] ?? collect())->pluck('sample_type_id')->filter()->unique()->map(fn ($t) => $types[$t] ?? null)->filter()->values()->all(),
                    'req_qty' => (int) ($bundles[$c->id] ?? collect())->sum('req_qty'), 'cutting_qty' => (int) ($bundles[$c->id] ?? collect())->sum('cutting_qty'),
                    'sewn_qty' => (int) ($moves[$c->id] ?? collect())->where('process', 'SEWING_DONE')->sum('qty'),
                    'out_qty' => (int) ($moves[$c->id] ?? collect())->where('process', 'DISTRIBUTION')->sum('qty'),
                    'delivered_qty' => $c->delivered_qty, 'rejected_qty' => $c->rejected_qty,
                ])->values()->all(),
            ];
        })->values()->all());
    }

    /** Sample Card in place: the delivered and rejected figures — never more, together, than was sewn; or hand the whole card over. */
    public function deliver(Request $request, string $card): JsonResponse
    {
        $this->allow('prototype.tracking.manage');
        $row = BundleCard::query()->where('card_no', $card)->firstOrFail();
        $data = $request->validate([
            'delivered_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rejected_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'hand_over' => ['sometimes', 'boolean'],
        ]);
        $sewn = (int) BundleMovement::query()->where('card_id', $row->id)->where('process', 'SEWING_DONE')->sum('qty');
        $rejected = (int) ($data['rejected_qty'] ?? $row->rejected_qty);
        $delivered = ! empty($data['hand_over']) ? max(0, $sewn - $rejected) : (int) ($data['delivered_qty'] ?? $row->delivered_qty);
        if ($delivered + $rejected > $sewn) {
            throw ValidationException::withMessages(['delivered_qty' => __('prototype.tracking.over_sewn', ['sewn' => $sewn])]);
        }
        $row->update(['delivered_qty' => $delivered, 'rejected_qty' => $rejected]);

        return ApiResponse::ok(['card_no' => $row->card_no, 'delivered_qty' => $delivered, 'rejected_qty' => $rejected, 'sewn_qty' => $sewn]);
    }

    /** The card follows its bundles: in production once any has been scanned, completed once all are distributed. */
    private function settle(BundleCard $card): void
    {
        $bundles = Bundle::query()->where('card_id', $card->id)->pluck('id');
        $out = BundleMovement::query()->whereIn('bundle_id', $bundles)->where('process', 'DISTRIBUTION')->count();
        $any = BundleMovement::query()->whereIn('bundle_id', $bundles)->exists();
        if ($bundles->isNotEmpty() && $out === $bundles->count()) {
            $card->update(['bundle_status' => 'COMPLETED']);
            SampleLedger::resolve('PRODUCTION_READY', $card->card_no);
        } elseif ($any && in_array($card->bundle_status, ['ISSUED', 'RECEIVED', 'COMPLETED'], true)) {
            $card->update(['bundle_status' => 'IN_PRODUCTION']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows): array
    {
        $bundles = Bundle::query()->whereIn('id', $rows->pluck('bundle_id'))->get()->keyBy('id');
        $cards = BundleCard::query()->whereIn('id', $rows->pluck('card_id'))->get()->keyBy('id');
        $samples = Sample::query()->whereIn('id', $cards->pluck('sample_id')->filter())->pluck('sample_no', 'id');
        $orders = Order::query()->whereIn('id', [...$bundles->pluck('order_id')->filter(), ...$cards->pluck('order_id')->filter()])->pluck('order_no', 'id');
        $types = SampleType::query()->whereIn('id', $bundles->pluck('sample_type_id')->filter())->pluck('name', 'id');
        $names = Prototype::names([...$rows->pluck('operator_id'), ...$rows->pluck('scanned_by')]);

        return $rows->map(function (BundleMovement $m) use ($bundles, $cards, $samples, $orders, $types, $names): array {
            $b = $bundles[$m->bundle_id] ?? null;
            $c = $cards[$m->card_id] ?? null;
            $order = $b?->order_id ?? $c?->order_id;
            $delay = $m->delayHours();

            return [
                'id' => $m->id, 'movement_date' => $m->movement_date->toDateString(), 'process' => $m->process, 'bundle_no' => $b?->bundle_no, 'card_no' => $c?->card_no,
                'sample_no' => $c?->sample_id ? ($samples[$c->sample_id] ?? null) : null, 'order_no' => $order ? ($orders[$order] ?? null) : null,
                'sample_type' => $b?->sample_type_id ? ($types[$b->sample_type_id] ?? null) : null, 'color' => $b?->color, 'size' => $b?->size, 'qty' => $m->qty,
                'operator_id' => $m->operator_id, 'operator_name' => $names[$m->operator_id] ?? null, 'scanned_by_name' => $names[$m->scanned_by] ?? null,
                'status' => $m->status, 'expected_hours' => $m->expected_hours === null ? null : (float) $m->expected_hours, 'delay_hours' => $delay,
                'delayed' => $m->status === 'DELAYED' || ($delay ?? 0) > 0, 'delay_reason' => $m->delay_reason, 'remarks' => $m->remarks,
                'scanned_at' => $m->scanned_at->toIso8601String(), 'received_at' => $m->received_at?->toIso8601String(), 'start_at' => $m->start_at?->toIso8601String(), 'end_at' => $m->end_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
