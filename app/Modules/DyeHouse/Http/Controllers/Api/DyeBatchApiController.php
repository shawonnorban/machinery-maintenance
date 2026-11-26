<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\DyeHouse\Models\DyeBatchPlan;
use App\Modules\DyeHouse\Models\DyeGreyIndent;
use App\Modules\DyeHouse\Models\DyeGreyIndentLine;
use App\Modules\DyeHouse\Models\DyeGreyIssue;
use App\Modules\DyeHouse\Models\DyeGreyOrderTransfer;
use App\Modules\DyeHouse\Models\DyeLeftoverTransfer;
use App\Modules\DyeHouse\Models\DyePtBill;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use App\Modules\FabricPlanning\Models\FpBatchPlan;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitGreyTransfer;
use App\Modules\Knitting\Models\KnitGreyTransferLine;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Support\GreyStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Batch (Bulk) (AnnotechERP 17/03): what its screens pick from;
 * the Dye Batch Plan board — machines down the side, the clock across the
 * top — with the cards pulled onto a plan and the hours each is booked for;
 * Batch Card Splitting; the batch store's issue against a grey requisition;
 * and grey still on a batch transferred to left-over at batch end. A card,
 * a plan and a requisition are addressed by their numbers.
 */
class DyeBatchApiController extends ApiController
{
    private const LBS_KG = 0.45359237;

    public function options(): JsonResponse
    {
        // Requisition (17/05) picks from the same lists.
        $this->allowEither('dyehouse.batch.view', 'dyehouse.requisition.view');
        $c = DyeBatches::company();
        DyeHouseDefaults::colourGroups($c);
        $n = DyeBatches::names();
        $carded = DyeBatch::query()->whereNotNull('fp_batch_plan_id')->distinct()->pluck('fp_batch_plan_id')->all();

        return ApiResponse::ok([
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('short_name')->get(['id', 'code', 'short_name', 'name'])
                ->map(fn ($b) => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')])->values()->all(),
            'orders' => DyeBatches::orders(),
            'chemicals' => DyeBatches::chemicals(),
            'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->code ? "{$x->name} ({$x->code})" : $x->name])->values()->all(),
            // Brickland's Dyeing Rate: the shade band and what water and wash cost under it.
            'rates' => DB::table('dye_colour_groups')->where('company_id', $c)->where('active', true)->orderBy('display_order')->get(['id', 'name', 'water_cost', 'wash_cost'])
                ->map(fn ($g) => ['value' => $g->id, 'label' => "{$g->name} · water ".(float) $g->water_cost.' / wash '.(float) $g->wash_cost])->values()->all(),
            'machines' => DB::table('dye_machines')->where('company_id', $c)->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'capacity', 'capacity_unit', 'is_sample'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no, 'capacity' => (float) $m->capacity, 'unit' => $m->capacity_unit, 'is_sample' => (bool) $m->is_sample])->values()->all(),
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'companies' => [['value' => $c, 'label' => $n['companies'][$c] ?? $c]],
            'batches' => DyeBatch::query()->where('status', 'OPEN')->orderByDesc('batch_date')->orderByDesc('batch_no')->limit(2000)->get(['id', 'batch_no', 'scope', 'card_qty', 'colour_id', 'plan_id', 'buyer_id', 'order_id', 'dye_machine_id'])
                ->map(fn ($b) => ['value' => $b->id, 'label' => $b->batch_no, 'scope' => $b->scope, 'qty' => (float) $b->card_qty, 'colour' => $n['colours'][$b->colour_id] ?? null, 'on_plan' => $b->plan_id !== null,
                    'colour_id' => $b->colour_id, 'buyer_id' => $b->buyer_id, 'order_id' => $b->order_id, 'machine_id' => $b->dye_machine_id])->values()->all(),
            // Released Fabric Batch Plans not yet cut into cards, for the board to pull.
            'fp_plans' => FpBatchPlan::query()->where('status', 'RELEASED')->whereNotIn('id', $carded)->orderBy('bp_no')->get()
                ->map(fn (FpBatchPlan $p) => ['value' => $p->id, 'label' => trim(implode(' · ', array_filter([$p->bp_no, $n['buyers'][$p->buyer_id] ?? null, $n['colours'][$p->colour_id] ?? null,
                    __('dyehouse.batch.board.fp_batches', ['n' => $p->batches, 'qty' => (float) $p->bp_qty])])))])->values()->all(),
            'plans' => DyeBatchPlan::query()->orderByDesc('plan_date')->get()->map(fn (DyeBatchPlan $p) => ['value' => $p->code, 'id' => $p->id, 'label' => "{$p->code} · {$p->name} · ".$p->plan_date->format('d M Y'),
                'scope' => $p->scope, 'party_id' => $p->party_id, 'active' => $p->active])->values()->all(),
            'employees' => DB::table('hr_employees')->where('company_id', $c)->orderBy('employee_code')->get(['id', 'employee_code', 'name'])
                ->map(fn ($e) => ['value' => $e->id, 'label' => trim("{$e->employee_code} — {$e->name}", ' —')])->values()->all(),
            // Cards that have run (a production on file): what a finishing check may be made on.
            'run_batches' => DyeBatch::query()->whereIn('id', DB::table('dye_productions')->where('company_id', $c)->distinct()->pluck('batch_id'))->orderBy('batch_no')->get(['id', 'batch_no', 'scope', 'lot_no', 'card_qty'])
                ->map(fn ($b) => ['value' => $b->id, 'label' => $b->batch_no, 'scope' => $b->scope, 'lot_no' => $b->lot_no, 'qty' => (float) $b->card_qty])->values()->all(),
            'pt_programmes' => DB::table('dye_pt_programmes')->where('company_id', $c)->orderBy('program_no')->get(['id', 'program_no', 'supplier_id', 'challan_id'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->program_no, 'supplier_id' => $p->supplier_id, 'challan_id' => $p->challan_id])->values()->all(),
            'pt_challans' => DB::table('dye_pt_challans')->where('company_id', $c)->orderByDesc('challan_date')->get(['id', 'challan_no', 'supplier_id'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->challan_no, 'supplier_id' => $x->supplier_id])->values()->all(),
            'scopes' => DyeBatchPlan::SCOPES, 'scales' => DyeBatchPlan::SCALES, 'kinds' => DyeBatchItem::KINDS, 'bill_statuses' => DyePtBill::STATUSES,
            'trans_types' => DyeGreyOrderTransfer::TYPES, 'transfer_statuses' => DyeGreyOrderTransfer::STATUSES, 'reasons' => DyeLeftoverTransfer::REASONS,
            'leftover_statuses' => DyeLeftoverTransfer::STATUSES,
        ]);
    }

    // ── Dye Batch Plan board ──────────────────────────────────────────────

    /** A plan's board over a window: its machines, the bars booked in it, and the cards still waiting to be laid out. */
    public function board(Request $request, string $code): JsonResponse
    {
        $this->allow('dyehouse.batch.view');
        $plan = $this->plan($code);
        $f = $request->validate(['from' => ['nullable', 'date'], 'scale' => ['nullable', 'integer', Rule::in(DyeBatchPlan::SCALES)], 'width' => ['nullable', 'numeric', 'between:4,60']]);
        $scale = (int) ($f['scale'] ?? $plan->scale_hours);
        $width = (float) ($f['width'] ?? $plan->cell_width);
        // Board hours are the floor's wall clock: kept and shown as keyed, with no zone moved.
        $from = CarbonImmutable::parse($f['from'] ?? $plan->window_from ?? $plan->plan_date->toDateString().' 00:00')->startOfHour();
        $columns = max(1, (int) floor(100 / $width));
        $to = $from->addHours($columns * $scale);
        $n = DyeBatches::names();
        $machines = DB::table('dye_machines')->where('company_id', DyeBatches::company())->where('active', true)->where('is_sample', $plan->scope === 'SAMPLE')->orderBy('machine_no')
            ->get(['id', 'machine_no', 'capacity', 'capacity_unit']);
        $card = fn (DyeBatch $b) => ['batch_no' => $b->batch_no, 'qty' => (float) $b->card_qty, 'colour' => $n['colours'][$b->colour_id] ?? null, 'group' => $n['groups'][$b->colour_group_id] ?? null,
            'buyer' => $n['buyers'][$b->buyer_id] ?? null, 'order_no' => $b->order_no, 'lot_no' => $b->lot_no, 'machine_id' => $b->dye_machine_id,
            'start' => $b->planned_start?->format('Y-m-d\TH:i'), 'end' => $b->planned_end?->format('Y-m-d\TH:i')];
        $batches = DyeBatch::query()->where('plan_id', $plan->id)->where('status', 'OPEN')->orderBy('batch_no')->get();

        return ApiResponse::ok([
            'plan' => ['code' => $plan->code, 'name' => $plan->name, 'scope' => $plan->scope, 'plan_date' => $plan->plan_date->toDateString(), 'party' => $n['companies'][$plan->party_id] ?? null,
                'scale' => $plan->scale_hours, 'width' => (float) $plan->cell_width],
            'window' => ['from' => $from->format('Y-m-d\TH:i'), 'to' => $to->format('Y-m-d\TH:i'), 'scale' => $scale, 'width' => $width, 'columns' => $columns],
            'machines' => $machines->map(fn ($m) => ['id' => $m->id, 'machine_no' => $m->machine_no, 'capacity' => (float) $m->capacity, 'unit' => $m->capacity_unit])->values()->all(),
            'bars' => $batches->filter(fn (DyeBatch $b) => $b->planned_start && $b->planned_start < $to && $b->planned_end > $from)->map($card)->values()->all(),
            'waiting' => $batches->whereNull('planned_start')->map($card)->values()->all(),
        ]);
    }

    /** Pull cards onto a plan: a released Fabric Batch Plan, cut into its cards, or cards already raised that are on no plan. */
    public function pull(Request $request, string $code): JsonResponse
    {
        $this->allow('dyehouse.batch.manage');
        $plan = $this->plan($code);
        $data = $request->validate([
            'fp_batch_plan_id' => ['nullable', 'required_without:batch_nos', Rule::exists('fp_batch_plans', 'id')->where('company_id', DyeBatches::company())],
            'batch_nos' => ['nullable', 'array', 'max:200'], 'batch_nos.*' => ['string', 'max:24'],
        ], ['fp_batch_plan_id.required_without' => __('dyehouse.batch.v.pull_what')]);
        $pulled = DB::transaction(function () use ($data, $plan): array {
            if (! empty($data['fp_batch_plan_id'])) {
                // A Fabric Batch Plan is bulk; its cards run on a bulk plan.
                if ($plan->scope !== 'BULK') {
                    throw ValidationException::withMessages(['fp_batch_plan_id' => __('dyehouse.batch.v.fp_on_sample', ['plan' => $plan->code])]);
                }

                return array_map(fn (DyeBatch $b) => $b->batch_no, DyeBatches::cardsFrom(FpBatchPlan::query()->findOrFail($data['fp_batch_plan_id']), $plan->id, $plan->plan_date->toDateString(), $this->caller()->auditUserId()));
            }
            $out = [];
            foreach (array_unique(array_map(fn ($v) => DyeBatches::batchNo($v), $data['batch_nos'])) as $no) {
                $b = $this->batch($no);
                if ($b->status !== 'OPEN' || $b->scope !== $plan->scope || ($b->plan_id && $b->plan_id !== $plan->id)) {
                    throw ValidationException::withMessages(['batch_nos' => __('dyehouse.batch.v.cannot_pull', ['no' => $b->batch_no])]);
                }
                $b->update(['plan_id' => $plan->id]);
                $out[] = $b->batch_no;
            }

            return $out;
        });

        return ApiResponse::ok(['pulled' => $pulled]);
    }

    /** Lay a card out on a machine from an hour, for so many hours: one batch at a time per machine, never heavier than the machine takes. */
    public function place(Request $request, string $code): JsonResponse
    {
        $this->allow('dyehouse.batch.manage');
        $plan = $this->plan($code);
        $data = $request->validate([
            'batch_no' => ['required', 'string', 'max:24'],
            'machine_id' => ['required', Rule::exists('dye_machines', 'id')->where('company_id', DyeBatches::company())->where('active', true)],
            'start' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:240'],
        ], ['machine_id.required' => __('dyehouse.batch.v.machine'), 'start.required' => __('dyehouse.batch.v.start'), 'hours.gt' => __('dyehouse.batch.v.hours')]);
        $batch = $this->batch($data['batch_no']);
        if ($batch->plan_id !== $plan->id || $batch->status !== 'OPEN') {
            throw ValidationException::withMessages(['batch_no' => __('dyehouse.batch.v.not_on_plan', ['no' => $batch->batch_no, 'plan' => $plan->code])]);
        }
        $machine = DB::table('dye_machines')->where('id', $data['machine_id'])->first(['machine_no', 'capacity', 'capacity_unit', 'is_sample']);
        if ((bool) $machine->is_sample !== ($plan->scope === 'SAMPLE')) {
            throw ValidationException::withMessages(['machine_id' => __('dyehouse.batch.v.machine_scope', ['mc' => $machine->machine_no])]);
        }
        $capacityKg = (float) $machine->capacity * ($machine->capacity_unit === 'LBS' ? self::LBS_KG : 1);
        if ($capacityKg > 0 && (float) $batch->card_qty > $capacityKg + 0.005) {
            throw ValidationException::withMessages(['machine_id' => __('dyehouse.batch.v.over_capacity', ['no' => $batch->batch_no, 'qty' => (float) $batch->card_qty, 'mc' => $machine->machine_no, 'cap' => round($capacityKg, 2)])]);
        }
        $start = CarbonImmutable::parse($data['start']);
        $end = $start->addMinutes((int) round((float) $data['hours'] * 60));
        DB::transaction(function () use ($batch, $data, $start, $end, $machine): void {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['dye_machine_booking:'.$data['machine_id']]);
            $clash = DyeBatch::query()->where('dye_machine_id', $data['machine_id'])->where('status', 'OPEN')->whereKeyNot($batch->id)->whereNotNull('planned_start')
                ->where('planned_start', '<', $end)->where('planned_end', '>', $start)->value('batch_no');
            if ($clash) {
                throw ValidationException::withMessages(['start' => __('dyehouse.batch.v.overlap', ['mc' => $machine->machine_no, 'no' => $clash])]);
            }
            $batch->update(['dye_machine_id' => $data['machine_id'], 'planned_start' => $start, 'planned_end' => $end]);
        });

        return ApiResponse::ok(['batch_no' => $batch->batch_no, 'start' => $start->format('Y-m-d\TH:i'), 'end' => $end->format('Y-m-d\TH:i')]);
    }

    /** Clearing a bar hands the hours back and returns the card to the strip. */
    public function clear(string $code, string $batchNo): JsonResponse
    {
        $this->allow('dyehouse.batch.manage');
        $plan = $this->plan($code);
        $batch = $this->batch($batchNo);
        if ($batch->plan_id !== $plan->id) {
            throw ValidationException::withMessages(['batch_no' => __('dyehouse.batch.v.not_on_plan', ['no' => $batch->batch_no, 'plan' => $plan->code])]);
        }
        $batch->update(['planned_start' => null, 'planned_end' => null]);

        return ApiResponse::ok(['batch_no' => $batch->batch_no]);
    }

    /** Save view: the board opens on this scale, width and window next time. */
    public function saveView(Request $request, string $code): JsonResponse
    {
        $this->allow('dyehouse.batch.manage');
        $plan = $this->plan($code);
        $data = $request->validate(['scale' => ['required', 'integer', Rule::in(DyeBatchPlan::SCALES)], 'width' => ['required', 'numeric', 'between:4,60'], 'from' => ['nullable', 'date']],
            ['width.between' => __('dyehouse.batch.v.cell_width')]);
        $plan->update(['scale_hours' => $data['scale'], 'cell_width' => $data['width'], 'window_from' => isset($data['from']) ? CarbonImmutable::parse($data['from']) : null]);

        return ApiResponse::ok(['code' => $plan->code]);
    }

    // ── Batch cards and Batch Card Splitting ───────────────────────────────

    /** Open cards for Batch Card Splitting's list: number and weight. */
    public function batches(): JsonResponse
    {
        $this->allow('dyehouse.batch.view');

        return ApiResponse::ok(DyeBatch::query()->where('status', 'OPEN')->where('scope', 'BULK')->orderByDesc('batch_date')->orderByDesc('batch_no')->get(['batch_no', 'card_qty'])
            ->map(fn (DyeBatch $b) => ['batch_no' => $b->batch_no, 'qty' => (float) $b->card_qty])->values()->all());
    }

    public function showBatch(string $no): JsonResponse
    {
        $this->allow('dyehouse.batch.view');

        return ApiResponse::ok($this->presentBatch($this->batch($no)));
    }

    /**
     * Split a card: the new card takes a new number, its own lot and machine and
     * what moves onto it; the same comes off the original in the one
     * transaction, so the two always add up to what was planned.
     */
    public function split(Request $request, string $no): JsonResponse
    {
        $this->allow('dyehouse.batch.manage');
        $old = $this->batch($no);
        if ($old->status !== 'OPEN') {
            throw ValidationException::withMessages(['batch_no' => __('dyehouse.batch.v.batch_closed', ['no' => $old->batch_no])]);
        }
        $data = $request->validate([
            'plan_qty' => ['required', 'numeric', 'gt:0'], 'card_qty' => ['required', 'numeric', 'gt:0'], 'lot_no' => ['required', 'string', 'max:40'],
            'machine_id' => ['nullable', Rule::exists('dye_machines', 'id')->where('company_id', DyeBatches::company())],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'string'],
            'lines.*.qty_kg' => ['nullable', 'numeric', 'min:0'], 'lines.*.issue_kg' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rolls' => ['nullable', 'integer', 'min:0'], 'lines.*.issue_rolls' => ['nullable', 'integer', 'min:0'],
            'lines.*.pcs' => ['nullable', 'integer', 'min:0'], 'lines.*.issue_pcs' => ['nullable', 'integer', 'min:0'], 'lines.*.yds' => ['nullable', 'numeric', 'min:0'],
        ], [
            'plan_qty.required' => __('dyehouse.batch.v.split_plan_qty'), 'plan_qty.gt' => __('dyehouse.batch.v.split_plan_qty'), 'card_qty.required' => __('dyehouse.batch.v.split_card_qty'),
            'card_qty.gt' => __('dyehouse.batch.v.split_card_qty'), 'lot_no.required' => __('dyehouse.batch.v.split_lot'), 'lines.required' => __('dyehouse.batch.v.split_lines'),
            'lines.min' => __('dyehouse.batch.v.split_lines'), 'lines.*.item_id.required' => __('dyehouse.batch.v.split_item'),
        ]);
        ksort($data['lines']);
        if ((float) $data['plan_qty'] >= (float) $old->plan_qty - 0.005) {
            throw ValidationException::withMessages(['plan_qty' => __('dyehouse.batch.v.split_whole', ['what' => __('dyehouse.batch.f.present_plan_qty'), 'qty' => (float) $old->plan_qty])]);
        }
        if ((float) $data['card_qty'] >= (float) $old->card_qty - 0.005) {
            throw ValidationException::withMessages(['card_qty' => __('dyehouse.batch.v.split_whole', ['what' => __('dyehouse.batch.f.present_card_qty'), 'qty' => (float) $old->card_qty])]);
        }
        $items = DyeBatchItem::query()->where('batch_id', $old->id)->get()->keyBy('id');
        $fields = ['qty_kg', 'issue_kg', 'rolls', 'issue_rolls', 'pcs', 'issue_pcs', 'yds'];
        $moves = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $item = $items[$l['item_id']] ?? null;
            if (! $item) {
                throw ValidationException::withMessages(["lines.{$i}.item_id" => __('dyehouse.batch.v.split_item_other', ['no' => $old->batch_no])]);
            }
            $move = ['item' => $item] + array_fill_keys($fields, 0);
            foreach ($fields as $k) {
                $move[$k] = (float) ($l[$k] ?? 0) + (float) ($moves[$item->id][$k] ?? 0);
                if ($move[$k] > (float) $item->{$k} + 0.0005) {
                    throw ValidationException::withMessages(["lines.{$i}.{$k}" => __('dyehouse.batch.v.split_over', ['item' => $item->description, 'have' => (float) $item->{$k}])]);
                }
            }
            if (array_sum(array_intersect_key($move, array_flip($fields))) <= 0) {
                throw ValidationException::withMessages(["lines.{$i}.qty_kg" => __('dyehouse.batch.v.split_nothing', ['item' => $item->description])]);
            }
            $moves[$item->id] = $move;
        }
        $new = DB::transaction(function () use ($old, $data, $moves, $fields): DyeBatch {
            $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
            $new = DyeBatch::query()->create(collect($old->only(['scope', 'fp_batch_plan_id', 'plan_id', 'buyer_id', 'order_id', 'order_no', 'style_ref', 'colour_id', 'colour_group_id',
                'dyeing_method', 'store_id']))->all() + [
                    'batch_no' => DyeBatches::nextNumber('B-', 'dye_batches', 'batch_no', $today), 'batch_date' => $today, 'source' => 'SPLIT', 'parent_id' => $old->id,
                    'dye_machine_id' => $data['machine_id'] ?? null, 'lot_no' => trim($data['lot_no']), 'plan_qty' => $data['plan_qty'], 'card_qty' => $data['card_qty'],
                    'status' => 'OPEN', 'created_by' => $this->caller()->auditUserId(),
                ]);
            foreach (array_values($moves) as $i => $m) {
                $item = $m['item'];
                DyeBatchItem::query()->create(['batch_id' => $new->id, 'kind' => $item->kind, 'description' => $item->description, 'sort_order' => ($i + 1) * 10] + array_intersect_key($m, array_flip($fields)));
                $item->update(collect($fields)->mapWithKeys(fn ($k) => [$k => (float) $item->{$k} - $m[$k]])->all());
            }
            $old->update(['plan_qty' => (float) $old->plan_qty - (float) $data['plan_qty'], 'card_qty' => (float) $old->card_qty - (float) $data['card_qty']]);

            return $new;
        });

        return ApiResponse::ok(['new' => $this->presentBatch($new), 'old' => $this->presentBatch($old->refresh())], [], 201);
    }

    // ── Bulk Grey Fabric Issue Register ───────────────────────────────────

    /** A requisition's lines, what has been issued, and the batch-store stock lines its card's number is on. */
    public function issueSheet(string $reqNo): JsonResponse
    {
        $this->allowEither('dyehouse.batch.view', 'dyehouse.batch.issue');

        return ApiResponse::ok($this->presentIssue($this->indent($reqNo)));
    }

    /** The store issues against a requisition, lot by lot: each comes off the grey ledger, never more than the lot holds or the requisition asks. */
    public function issue(Request $request, string $reqNo): JsonResponse
    {
        $this->allowEither('dyehouse.batch.issue', 'dyehouse.batch.manage');
        $indent = $this->indent($reqNo);
        $data = $request->validate([
            'issue_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.lot_id' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ], ['issue_date.required' => __('dyehouse.batch.v.issue_date'), 'lines.required' => __('dyehouse.batch.v.issue_lines'), 'lines.*.qty.gt' => __('dyehouse.batch.v.issue_qty')]);
        ksort($data['lines']);
        $batchNos = $this->stockNumbers($indent->batch_id);
        $asked = (float) DyeGreyIndentLine::query()->where('indent_id', $indent->id)->sum('req_kg');
        $issued = (float) DyeGreyIssue::query()->where('indent_id', $indent->id)->sum('qty_kg');
        $total = array_sum(array_map(fn ($l) => (float) $l['qty'], $data['lines']));
        if ($issued + $total > $asked + 0.0005) {
            throw ValidationException::withMessages(['lines' => __('dyehouse.batch.v.issue_over_req', ['no' => $indent->req_no, 'left' => round($asked - $issued, 3)])]);
        }
        DB::transaction(function () use ($indent, $data, $batchNos): void {
            GreyStore::assertOpen($data['issue_date'], 'issue_date');
            foreach (array_values($data['lines']) as $i => $l) {
                $lot = KnitGreyLot::query()->find($l['lot_id']);
                if (! $lot || ! in_array($lot->batch_no, $batchNos, true)) {
                    throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('dyehouse.batch.v.issue_lot', ['no' => $batchNos[0]])]);
                }
                $have = GreyStore::balance($lot->id);
                // A weighed roll leaves whole.
                $qty = $lot->roll_id ? $have : (float) $l['qty'];
                if ($qty > $have + 0.0005 || $qty <= 0) {
                    throw ValidationException::withMessages(["lines.{$i}.qty" => __('dyehouse.batch.v.issue_over_lot', ['have' => round($have, 3)])]);
                }
                $issue = DyeGreyIssue::query()->create(['indent_id' => $indent->id, 'lot_id' => $lot->id, 'issue_date' => $data['issue_date'], 'qty_kg' => $qty, 'rolls' => $lot->roll_id ? 1 : 0,
                    'created_by' => $this->caller()->auditUserId()]);
                GreyStore::move('DYE_ISSUE', $indent->req_no, $data['issue_date'], ['lot_id' => $lot->id, 'qty' => -$qty, 'rolls' => $lot->roll_id ? -1 : 0, 'doc_line_id' => $issue->id]);
            }
        });

        return ApiResponse::ok($this->presentIssue($indent->refresh()), [], 201);
    }

    // ── Grey to Leftover Transfer (batch end) ──────────────────────────────

    /** Grey still on a batch in the batch store, by stock line. */
    public function leftoverLots(): JsonResponse
    {
        $this->allow('dyehouse.batch.view');
        $status = DyeBatch::query()->pluck('status', 'batch_no');

        return ApiResponse::ok(collect(GreyStore::lots())->filter(fn ($l) => ! empty($l['batch_no']))
            ->map(fn ($l) => $l + ['batch_status' => $status[$l['batch_no']] ?? null])->values()->all());
    }

    /**
     * Make Transfer: the ticked lines, at the weight given, into a left-over
     * store — one received Batch to Leftover movement per store and order it
     * came from, as Knit Production's own register keeps them.
     */
    public function makeLeftover(Request $request): JsonResponse
    {
        $this->allow('dyehouse.batch.manage');
        $data = $request->validate([
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', DyeBatches::company())],
            'transfer_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.lot_id' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ], ['store_id.required' => __('dyehouse.batch.v.leftover_store'), 'lines.required' => __('dyehouse.batch.v.leftover_lines'), 'lines.min' => __('dyehouse.batch.v.leftover_lines')]);
        ksort($data['lines']);
        $made = DB::transaction(function () use ($data): array {
            $date = $data['transfer_date'];
            GreyStore::assertOpen($date, 'transfer_date');
            $groups = [];
            foreach (array_values($data['lines']) as $i => $l) {
                $lot = KnitGreyLot::query()->find($l['lot_id']);
                if (! $lot || ! $lot->batch_no) {
                    throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('dyehouse.batch.v.leftover_not_batch')]);
                }
                if ($lot->store_id === $data['store_id']) {
                    throw ValidationException::withMessages(['store_id' => __('dyehouse.batch.v.leftover_same_store')]);
                }
                $have = GreyStore::balance($lot->id);
                $qty = $lot->roll_id ? $have : (float) $l['qty'];
                if ($qty > $have + 0.0005 || $qty <= 0) {
                    throw ValidationException::withMessages(["lines.{$i}.qty" => __('dyehouse.batch.v.issue_over_lot', ['have' => round($have, 3)])]);
                }
                $groups[$lot->store_id.'|'.$lot->order_id][] = [$lot, $qty];
            }
            $nos = [];
            foreach ($groups as $lines) {
                $first = $lines[0][0];
                $t = KnitGreyTransfer::query()->create([
                    'transfer_no' => GreyStore::nextNumber('BLO', 'knit_grey_transfers', 'transfer_no', $date), 'kind' => 'BATCH_TO_LEFTOVER', 'transfer_date' => $date, 'trans_type' => 'BULK_TO_BULK',
                    'buyer_id' => $first->buyer_id, 'order_id' => $first->order_id, 'from_store_id' => $first->store_id, 'to_store_id' => $data['store_id'],
                    'remarks' => __('dyehouse.batch.g2l.remark'), 'status' => 'RECEIVED', 'created_by' => $this->caller()->auditUserId(),
                ]);
                $out = [];
                $in = [];
                foreach ($lines as $k => [$lot, $qty]) {
                    $landed = GreyStore::lotIn($lot, $data['store_id'], $date, ['batch_no' => null]);
                    $line = KnitGreyTransferLine::query()->create(['transfer_id' => $t->id, 'sort_order' => ($k + 1) * 10, 'lot_id' => $lot->id, 'rolls' => $lot->roll_id ? 1 : 0, 'qty' => $qty,
                        'to_lot_id' => $landed->id]);
                    $out[] = ['lot_id' => $lot->id, 'qty' => -$qty, 'rolls' => $lot->roll_id ? -1 : 0, 'doc_line_id' => $line->id];
                    $in[] = ['lot_id' => $landed->id, 'qty' => $qty, 'rolls' => $lot->roll_id ? 1 : 0, 'doc_line_id' => $line->id];
                    if ($lot->roll_id) {
                        KnitRoll::query()->whereKey($lot->roll_id)->update(['movement' => 'LEFT_OVER']);
                    }
                }
                GreyStore::post('TRANSFER_OUT', $t->transfer_no, $date, $out);
                GreyStore::post('TRANSFER_IN', $t->transfer_no, $date, $in);
                $nos[] = $t->transfer_no;
            }

            return $nos;
        });

        return ApiResponse::ok(['transfers' => $made], [], 201);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * The batch numbers a card's grey lies under in the store: its own, and — for
     * a sample drawn off a bulk batch — the bulk batch's, which the rolls still carry.
     *
     * @return list<string>
     */
    private function stockNumbers(string $batchId): array
    {
        $b = DyeBatch::query()->findOrFail($batchId);
        $parent = $b->source === 'FROM_BULK' && $b->parent_id ? DyeBatch::query()->whereKey($b->parent_id)->value('batch_no') : null;

        return array_values(array_filter([$b->batch_no, $parent]));
    }

    private function plan(string $code): DyeBatchPlan
    {
        return DyeBatchPlan::query()->where('code', $code)->firstOrFail();
    }

    private function batch(string $no): DyeBatch
    {
        return DyeBatch::query()->where('batch_no', DyeBatches::batchNo($no))->firstOrFail();
    }

    private function indent(string $no): DyeGreyIndent
    {
        return DyeGreyIndent::query()->where('req_no', $no)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function presentBatch(DyeBatch $b): array
    {
        $n = DyeBatches::names();

        return [
            'batch_no' => $b->batch_no, 'batch_date' => $b->batch_date->toDateString(), 'source' => $b->source, 'status' => $b->status, 'plan_qty' => (float) $b->plan_qty, 'card_qty' => (float) $b->card_qty,
            'lot_no' => $b->lot_no, 'machine_id' => $b->dye_machine_id, 'machine' => $n['machines'][$b->dye_machine_id] ?? null, 'colour' => $n['colours'][$b->colour_id] ?? null,
            'buyer' => $n['buyers'][$b->buyer_id] ?? null, 'order_no' => $b->order_no,
            'items' => DyeBatchItem::query()->where('batch_id', $b->id)->orderBy('sort_order')->get()->map(fn (DyeBatchItem $i) => [
                'id' => $i->id, 'kind' => $i->kind, 'description' => $i->description, 'qty_kg' => (float) $i->qty_kg, 'issue_kg' => (float) $i->issue_kg, 'rolls' => $i->rolls,
                'issue_rolls' => $i->issue_rolls, 'pcs' => $i->pcs, 'issue_pcs' => $i->issue_pcs, 'yds' => (float) $i->yds,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentIssue(DyeGreyIndent $indent): array
    {
        $batchNos = $this->stockNumbers($indent->batch_id);
        $batchNo = $batchNos[0];
        $issues = DyeGreyIssue::query()->where('indent_id', $indent->id)->orderBy('created_at')->get();
        $lots = KnitGreyLot::query()->whereIn('batch_no', $batchNos)->get();
        // An issued lot may since have left the batch (a roll now in left-over); it is still named.
        $byId = $lots->keyBy('id')->union(KnitGreyLot::query()->whereIn('id', $issues->pluck('lot_id'))->get()->keyBy('id'));
        $names = GreyStore::names($byId->values());

        return [
            'req_no' => $indent->req_no, 'req_date' => $indent->req_date->toDateString(), 'batch_no' => $batchNo,
            'req_kg' => round((float) DyeGreyIndentLine::query()->where('indent_id', $indent->id)->sum('req_kg'), 3), 'issue_kg' => round((float) $issues->sum('qty_kg'), 3),
            'lines' => DyeGreyIndentLine::query()->where('indent_id', $indent->id)->orderBy('sort_order')->get()->map(fn ($l) => ['kind' => $l->kind, 'description' => $l->description, 'req_kg' => (float) $l->req_kg, 'rolls' => $l->rolls])->values()->all(),
            'lots' => $lots->map(fn (KnitGreyLot $l) => GreyStore::describe($l, $names) + ['balance' => GreyStore::balance($l->id)])->filter(fn ($l) => $l['balance'] > 0.00005)->values()->all(),
            'issues' => $issues->map(fn (DyeGreyIssue $i) => ['issue_date' => $i->issue_date->toDateString(), 'qty' => (float) $i->qty_kg, 'rolls' => $i->rolls,
                'lot' => isset($byId[$i->lot_id]) ? GreyStore::describe($byId[$i->lot_id], $names)['label'] : null])->values()->all(),
        ];
    }
}
