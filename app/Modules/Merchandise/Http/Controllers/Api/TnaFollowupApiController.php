<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\LabdipShade;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OrderTna;
use App\Modules\Merchandise\Models\OrderTnaEvent;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Models\TnaTemplate;
use App\Modules\Merchandise\Models\TnaTemplateTask;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merchandise Desk → T&A Followup (AnnotechERP 06/12), as Brickland works it:
 * pick a confirmed order and its time and action calendar loads with the
 * order's facts. Recalculate Plan Date raises the milestones the order is
 * missing from the TNA Master — the order's template, else the template
 * whose order type and lead time fit, else each task's own offset — and
 * re-dates every open one to its day from the current shipment date;
 * completed milestones keep theirs. A milestone whose task names a source
 * module is read from that module (an order's confirmation, its approved
 * bulk fabric booking, its lab dips all approved); one with no module takes
 * its actual date here. Every change is written to the order's history.
 */
class TnaFollowupApiController extends ApiController
{
    /** The orders a calendar is followed for. */
    private const OPEN = ['CONFIRMED', 'IN_PRODUCTION'];

    /** The modules that record a task, by the key its source module normalises to. */
    private const MODULES = ['ORDER' => 'Order Module', 'FABRIC_BOOKING' => 'Fabric Booking', 'LAB_DIP' => 'Lab Dip'];

    public function orders(): JsonResponse
    {
        $this->allow('merchandise.tna.view');
        $orders = Order::query()->whereIn('status', self::OPEN)->orderByDesc('order_no')->get(['id', 'order_no', 'style_id', 'style_no', 'buyer_id', 'po_no']);
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->merge($styles->pluck('buyer_id'))->filter())->get(['id', 'name', 'short_name'])->keyBy('id');

        return ApiResponse::ok($orders->map(function (Order $o) use ($styles, $buyers): array {
            $s = $o->style_id ? ($styles[$o->style_id] ?? null) : null;
            $b = $buyers[$o->buyer_id ?? $s?->buyer_id] ?? null;
            // Brickland's "LPP-2026-000008 · LPP · 529 · PO 1531".
            $label = implode(' · ', array_filter([$o->order_no, $b ? ($b->short_name ?: $b->name) : null, $s?->styleNo() ?? $o->style_no, $o->po_no ? "PO {$o->po_no}" : null]));

            return ['value' => $o->order_no, 'label' => $label];
        })->values()->all());
    }

    public function show(string $order): JsonResponse
    {
        $this->allow('merchandise.tna.view');
        $row = $this->order($order);
        $this->syncAuto($row);

        return ApiResponse::ok($this->present($row));
    }

    /** Recalculate Plan Date: raise what is missing, re-date what is open. */
    public function recalculate(string $order): JsonResponse
    {
        $this->allow('merchandise.tna.manage');
        $row = $this->order($order);
        if ($row->ship_date === null) {
            throw ValidationException::withMessages(['order' => __('merchandise.tna.no_ship_date')]);
        }
        $plan = $this->planFor($row);
        if ($plan->isEmpty()) {
            throw ValidationException::withMessages(['order' => __('merchandise.tna.no_master')]);
        }
        DB::transaction(function () use ($row, $plan): void {
            $existing = OrderTna::query()->where('order_id', $row->id)->get()->keyBy('tna_task_id');
            $added = 0;
            $redated = 0;
            foreach ($plan as $i => [$taskId, $offset]) {
                // The TNA Master counts a milestone's day from shipment: negative is before it.
                $date = $row->ship_date->copy()->addDays($offset)->toDateString();
                $current = $existing[$taskId] ?? null;
                if ($current === null) {
                    OrderTna::query()->create(['order_id' => $row->id, 'tna_task_id' => $taskId, 'offset_days' => $offset, 'plan_date' => $date, 'sort_order' => $i + 1, 'updated_by' => $this->caller()->auditUserId()]);
                    $added++;

                    continue;
                }
                // A completed milestone keeps the date it was planned for; every one takes its place in the calendar.
                if ($current->actual_date === null && $current->plan_date?->toDateString() !== $date) {
                    $current->update(['offset_days' => $offset, 'plan_date' => $date, 'sort_order' => $i + 1]);
                    $redated++;
                } elseif ($current->sort_order !== $i + 1) {
                    $current->update(['sort_order' => $i + 1]);
                }
            }
            $this->event($row, __('merchandise.tna.recalculated', ['order' => $row->order_no, 'redated' => $redated, 'ship' => $row->ship_date->format('d-M-Y'), 'added' => $added]));
        });
        $this->syncAuto($row);

        return ApiResponse::ok($this->present($row));
    }

    /** A milestone's actual date (only where no module records it), remarks, or "complete today". */
    public function update(Request $request, string $order, string $milestone): JsonResponse
    {
        $this->allow('merchandise.tna.manage');
        $row = $this->order($order);
        $line = OrderTna::query()->where('order_id', $row->id)->whereKey($milestone)->firstOrFail();
        $task = TnaTask::query()->whereKey($line->tna_task_id)->firstOrFail();
        $data = $request->validate([
            'actual_date' => ['nullable', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'complete_today' => ['sometimes', 'boolean'],
        ], ['actual_date.before_or_equal' => __('merchandise.tna.future_actual')]);
        $actual = ($data['complete_today'] ?? false) ? CarbonImmutable::now()->toDateString() : ($data['actual_date'] ?? null);
        if ($task->source_module && $actual !== ($line->actual_date?->toDateString())) {
            throw ValidationException::withMessages(['actual_date' => __('merchandise.tna.module_records', ['module' => $task->source_module])]);
        }
        $remarks = isset($data['remarks']) && trim((string) $data['remarks']) !== '' ? trim((string) $data['remarks']) : null;
        DB::transaction(function () use ($row, $line, $task, $actual, $remarks): void {
            if ($actual !== ($line->actual_date?->toDateString())) {
                $this->event($row, $actual
                    ? __('merchandise.tna.actual_set', ['task' => $task->name, 'date' => CarbonImmutable::parse($actual)->format('d-M-Y')])
                    : __('merchandise.tna.actual_cleared', ['task' => $task->name]));
            }
            if ($remarks !== $line->remarks) {
                $this->event($row, __('merchandise.tna.remark_saved', ['task' => $task->name]));
            }
            $line->update(['actual_date' => $actual, 'remarks' => $remarks, 'updated_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->present($row));
    }

    private function order(string $order): Order
    {
        return Order::query()->where('order_no', $order)->whereIn('status', self::OPEN)->firstOrFail();
    }

    /**
     * The milestones the order should carry, as [task id, day from shipment], in calendar order: the order's
     * own template, else the one whose order type and lead time fit, else every active task with an offset.
     *
     * @return Collection<int, array{0: string, 1: int}>
     */
    private function planFor(Order $order): Collection
    {
        $template = $order->tna_template_id ? TnaTemplate::query()->whereKey($order->tna_template_id)->first() : null;
        if ($template === null && $order->conf_date && $order->ship_date) {
            $lead = (int) $order->conf_date->diffInDays($order->ship_date);
            $type = match ($order->order_type) {
                'BULK_NEW' => 'NEW', 'REPEAT' => 'REPEAT', 'DEVELOPMENT' => 'DEVELOPMENT', 'PROJECTION' => 'PROJECTION', default => null,
            };
            $template = TnaTemplate::query()->where('order_type', $type)->where('lead_from', '<=', $lead)->where('lead_to', '>=', $lead)
                ->orderByRaw('buyer_id is null')->when($order->buyer_id, fn ($q) => $q->where(fn ($w) => $w->whereNull('buyer_id')->orWhere('buyer_id', $order->buyer_id)))->first();
        }
        $tasks = TnaTask::query()->where('active', true)->get()->keyBy('id');
        $rows = $template
            ? TnaTemplateTask::query()->where('tna_template_id', $template->id)->where('active', true)->get()->filter(fn ($t) => isset($tasks[$t->tna_task_id]))
                ->map(fn ($t): array => [$t->tna_task_id, (int) $t->offset_days])
            : $tasks->filter(fn (TnaTask $t) => $t->offset_days !== null)->map(fn (TnaTask $t): array => [$t->id, (int) $t->offset_days]);

        // Earliest first, ties in the task master's order.
        return $rows->sort(fn (array $x, array $y): int => [$x[1], $tasks[$x[0]]->sort_order] <=> [$y[1], $tasks[$y[0]]->sort_order])->values();
    }

    /** Milestones a module records: read their actual date from it, and note the change once. */
    private function syncAuto(Order $order): void
    {
        $lines = OrderTna::query()->where('order_id', $order->id)->get();
        $tasks = TnaTask::query()->whereIn('id', $lines->pluck('tna_task_id'))->get()->keyBy('id');
        foreach ($lines as $line) {
            $key = $this->moduleKey($tasks[$line->tna_task_id]->source_module ?? null);
            if ($key === null) {
                continue;
            }
            $date = $this->recorded($order, $key)['date'];
            if ($date !== ($line->actual_date?->toDateString())) {
                $line->update(['actual_date' => $date, 'auto' => $date !== null]);
                if ($date !== null) {
                    $this->event($order, __('merchandise.tna.auto_updated', ['task' => $tasks[$line->tna_task_id]->name, 'module' => self::MODULES[$key], 'date' => CarbonImmutable::parse($date)->format('d-M-Y')]), null);
                }
            }
        }
    }

    /** "Order Module" → ORDER, "Fabric Booking" → FABRIC_BOOKING …; null when no module of ours records it. */
    private function moduleKey(?string $source): ?string
    {
        if ($source === null || trim($source) === '') {
            return null;
        }
        $key = preg_replace('/_MODULE$/', '', strtoupper(str_replace([' ', '-', '/'], '_', trim($source))));

        return array_key_exists($key, self::MODULES) ? $key : 'OTHER';
    }

    /**
     * What a module records for the order: the date it happened (null while waiting) and how it reads.
     *
     * @return array{date: ?string, text: ?string}
     */
    private function recorded(Order $order, string $key): array
    {
        return match ($key) {
            'ORDER' => $order->conf_date
                ? ['date' => $order->conf_date->toDateString(), 'text' => __('merchandise.tna.src.order', ['date' => $order->conf_date->format('d-M-Y')])]
                : ['date' => null, 'text' => null],
            'FABRIC_BOOKING' => (function () use ($order): array {
                $booking = FabricBooking::query()->where('order_id', $order->id)->where('booking_type', 'BULK')->where('status', 'APPROVED')->orderBy('approved_at')->first();

                return $booking
                    ? ['date' => ($booking->approved_at ?? $booking->booking_date)->toDateString(), 'text' => __('merchandise.tna.src.booking', ['no' => $booking->booking_no, 'date' => ($booking->approved_at ?? $booking->booking_date)->format('d-M-Y')])]
                    : ['date' => null, 'text' => null];
            })(),
            'LAB_DIP' => (function () use ($order): array {
                $shades = LabdipShade::query()->where('order_id', $order->id)->get(['status', 'decided_on']);
                $done = $shades->isNotEmpty() && $shades->every(fn ($s) => $s->status === 'APPROVED');
                $last = $done ? $shades->max('decided_on') : null;

                return $last ? ['date' => CarbonImmutable::parse($last)->toDateString(), 'text' => __('merchandise.tna.src.labdip', ['n' => $shades->count(), 'date' => CarbonImmutable::parse($last)->format('d-M-Y')])] : ['date' => null, 'text' => null];
            })(),
            default => ['date' => null, 'text' => null],
        };
    }

    private function event(Order $order, string $message, ?string $user = 'caller'): void
    {
        OrderTnaEvent::query()->create(['order_id' => $order->id, 'message' => $message, 'user_id' => $user === 'caller' ? $this->caller()->auditUserId() : null]);
    }

    /** @return array<string, mixed> */
    private function present(Order $order): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $style = $order->style_id ? Style::query()->whereKey($order->style_id)->first() : null;
        $buyer = ($order->buyer_id ?? $style?->buyer_id) ? Buyer::query()->whereKey($order->buyer_id ?? $style?->buyer_id)->first(['name', 'short_name']) : null;
        $office = ($order->buyer_office_id ?? $style?->buyer_office_id) ? BuyerOffice::query()->whereKey($order->buyer_office_id ?? $style?->buyer_office_id)->value('name') : null;
        $lines = OrderTna::query()->where('order_id', $order->id)->orderBy('sort_order')->get();
        $tasks = TnaTask::query()->whereIn('id', $lines->pluck('tna_task_id'))->get()->keyBy('id');
        $groups = TnaTaskGroup::query()->whereIn('id', $tasks->pluck('tna_task_group_id')->filter())->pluck('name', 'id');
        $users = User::query()->whereIn('id', $lines->pluck('updated_by')->filter())->pluck('name', 'id');

        $rows = $lines->map(function (OrderTna $l) use ($tasks, $groups, $order, $today): array {
            $task = $tasks[$l->tna_task_id];
            $key = $this->moduleKey($task->source_module);
            $d2g = $l->plan_date ? (int) $today->diffInDays(CarbonImmutable::parse($l->plan_date)->startOfDay(), false) : null;
            $status = match (true) {
                $l->actual_date !== null => 'COMPLETED',
                $d2g === null => 'NOT_STARTED',
                $d2g < 0 => 'OVERDUE',
                $d2g === 0 => 'DUE_TODAY',
                $d2g <= 7 => 'PENDING',
                default => 'PLANNED',
            };
            $source = $key && $key !== 'OTHER' ? $this->recorded($order, $key) : ['date' => null, 'text' => null];

            return [
                'id' => $l->id, 'task' => $task->name, 'department' => $task->tna_task_group_id ? ($groups[$task->tna_task_group_id] ?? null) : null,
                'plan_date' => $l->plan_date?->toDateString(), 'actual_date' => $l->actual_date?->toDateString(), 'd2g' => $d2g, 'required' => $l->required,
                'done' => $l->actual_date ? 1 : 0, 'completion' => $l->actual_date ? 100 : 0, 'status' => $status,
                'source_module' => $task->source_module, 'module_key' => $key, 'auto' => $key !== null, 'source_text' => $source['text'], 'remarks' => $l->remarks,
                'related_doc' => $key === 'ORDER' && $l->actual_date ? $order->order_no : null,
            ];
        })->values();

        $sizes = OrderSize::query()->where('order_id', $order->id)->orderBy('sort_order')->get(['colour', 'size', 'qty']);
        $total = (float) $order->total_qty;
        $colours = $sizes->groupBy('colour')->map(fn ($g, $c): array => ['colour' => $c, 'qty' => (float) $g->sum('qty')])->values();
        $sizeRows = $sizes->groupBy('size')->map(fn ($g, $s): array => ['size' => $s, 'qty' => (float) $g->sum('qty')])->values();
        $bookings = FabricBooking::query()->where('order_id', $order->id)->orderBy('id')->get(['booking_type', 'booking_no', 'booking_date', 'status']);
        $events = OrderTnaEvent::query()->where('order_id', $order->id)->orderByDesc('id')->limit(100)->get();
        $eventUsers = User::query()->whereIn('id', $events->pluck('user_id')->filter())->pluck('name', 'id');

        return [
            'order' => [
                'order_no' => $order->order_no, 'status' => $order->status, 'buyer' => $buyer ? ($buyer->short_name ?: $buyer->name) : null, 'buyer_ac' => $office,
                'style' => $style?->styleNo() ?? $order->style_no, 'po_no' => $order->po_no, 'order_date' => $order->order_date?->toDateString(),
                'ship_date' => $order->ship_date?->toDateString(), 'qty' => $total, 'unit' => $order->unit, 'gmt_type' => $order->item ?: $order->gmt_type,
                'season' => $style?->season, 'template' => $order->tna_template_id ? TnaTemplate::query()->whereKey($order->tna_template_id)->value('code') : null,
            ],
            'rows' => $rows->all(),
            'kpis' => [
                'total' => $rows->count(), 'completed' => $rows->where('status', 'COMPLETED')->count(), 'in_progress' => 0,
                'pending' => $rows->where('status', 'PENDING')->count(), 'overdue' => $rows->where('status', 'OVERDUE')->count(), 'due_today' => $rows->where('status', 'DUE_TODAY')->count(),
                'progress' => $rows->count() ? (int) round(100 * $rows->where('status', 'COMPLETED')->count() / $rows->count()) : 0,
            ],
            'styles' => [['style' => $style?->styleNo() ?? $order->style_no, 'gmt_type' => $order->item ?: $order->gmt_type, 'qty' => $total]],
            'colours' => $colours->all(), 'sizes' => $sizeRows->all(),
            'matrix' => ['sizes' => $sizeRows->pluck('size')->all(), 'rows' => $sizes->groupBy('colour')->map(fn ($g, $c): array => ['colour' => $c, 'cells' => $g->groupBy('size')->map(fn ($x) => (float) $x->sum('qty'))->all(), 'total' => (float) $g->sum('qty')])->values()->all()],
            'documents' => collect([['document' => 'PURCHASE_ORDER', 'reference' => $order->po_no ?: $order->order_no, 'date' => $order->order_date?->toDateString(), 'status' => $order->status]])
                ->merge($bookings->map(fn (FabricBooking $b): array => ['document' => "BOOKING_{$b->booking_type}", 'reference' => $b->booking_no, 'date' => $b->booking_date->toDateString(), 'status' => $b->status]))->values()->all(),
            'remarks' => $rows->filter(fn ($r) => $r['remarks'])->map(fn ($r) => ['task' => $r['task'], 'remarks' => $r['remarks']])->values()->all(),
            'history' => $events->map(fn (OrderTnaEvent $e): array => ['at' => $e->created_at?->toIso8601String(), 'by' => $e->user_id ? ($eventUsers[$e->user_id] ?? null) : null, 'message' => $e->message])->values()->all(),
            'modules' => $rows->filter(fn ($r) => $r['module_key'] !== null)->map(fn ($r) => ['task' => $r['task'], 'module' => $r['source_module'], 'done' => $r['actual_date'] !== null, 'status' => $r['status']])->values()->all(),
            'updated_by' => $lines->pluck('updated_by')->filter()->map(fn ($id) => $users[$id] ?? null)->filter()->unique()->values()->all(),
        ];
    }
}
