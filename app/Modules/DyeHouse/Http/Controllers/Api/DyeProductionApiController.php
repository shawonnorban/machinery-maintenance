<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\DyeHouse\Models\DyeBatchPlan;
use App\Modules\DyeHouse\Models\DyeGreyIndent;
use App\Modules\DyeHouse\Models\DyeLabDipRecipe;
use App\Modules\DyeHouse\Models\DyeProduction;
use App\Modules\DyeHouse\Models\DyeProductionLoad;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House batch production (AnnotechERP 17/04, Sample Batch Production;
 * 17/07 writes the bulk side): the open cards of a scope to find a batch in,
 * a card's production sheet — what it is, what it is made of, the
 * requisitions it came in on, the lab dip recipes for its shade and the
 * productions already written — and a production saved with its loads, one
 * per batch per day — a bulk one naming its machine, model/combo and lab
 * dip, a closed card taking none — Bulk Batch Production's Records, Manual
 * Daily Bulk Production's load times, and Sample Grey Fabric Indent's Batch
 * and Program Lists. A card is addressed by its batch number, a production by its day.
 */
class DyeProductionApiController extends ApiController
{
    public const LISTS = ['batch-list', 'program-list', 'pending-labdip'];

    /** Open cards of a scope for the production screen's list: number, shade and weight. */
    public function batches(Request $request): JsonResponse
    {
        $this->allowEither('dyehouse.batch.view', 'dyehouse.production.view');
        $scope = $request->validate(['scope' => ['required', Rule::in(['BULK', 'SAMPLE'])]])['scope'];
        $colours = DB::table('colours')->where('company_id', DyeBatches::company())->pluck('name', 'id');

        return ApiResponse::ok(DyeBatch::query()->where('scope', $scope)->where('status', 'OPEN')->orderByDesc('batch_date')->orderByDesc('batch_no')->get(['batch_no', 'colour_id', 'card_qty'])
            ->map(fn (DyeBatch $b) => ['batch_no' => $b->batch_no, 'colour' => $colours[$b->colour_id] ?? null, 'qty' => (float) $b->card_qty])->values()->all());
    }

    public function sheet(string $no): JsonResponse
    {
        $this->allowEither('dyehouse.batch.view', 'dyehouse.production.view');

        return ApiResponse::ok($this->present($this->batch($no)));
    }

    /**
     * Save a production: new, or the one of `original_date` rewritten. One per
     * batch per day — a batch put back on a machine the same day is another
     * load on the history, not another production — and at least one load.
     */
    public function save(Request $request, string $no): JsonResponse
    {
        $batch = $this->batch($no);
        $bulk = $batch->scope === 'BULK';
        $bulk ? $this->allowEither('dyehouse.batch.manage', 'dyehouse.production.manage') : $this->allow('dyehouse.batch.manage');
        // A card closed out of dyeing and finishing (17/07) takes no more production.
        if ($batch->status === 'CLOSED') {
            throw ValidationException::withMessages(['batch_no' => __('dyehouse.prod.v.batch_closed', ['no' => $batch->batch_no])]);
        }
        $c = DyeBatches::company();
        $need = $bulk ? 'required' : 'nullable';
        $data = $request->validate([
            'original_date' => ['nullable', 'date'],
            'production_date' => ['required', 'date'],
            'requisition_id' => ['nullable', Rule::exists('dye_grey_indents', 'id')->where('company_id', $c)->where('batch_id', $batch->id)],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'machine_id' => [$need, Rule::exists('dye_machines', 'id')->where('company_id', $c)],
            'model_combo' => [$need, 'string', 'max:120'],
            'lab_dip_ref' => [$need, 'string', 'max:40'],
            'bypass_check_roll' => ['nullable', 'boolean'],
            'scouring' => ['nullable', 'boolean'],
            'liquor_ratio' => ['required', 'regex:/^1:\d+(\.\d+)?$/'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'loads' => ['required', 'array', 'min:1', 'max:50'],
            'loads.*.machine_id' => ['nullable', Rule::exists('dye_machines', 'id')->where('company_id', $c)],
            'loads.*.load_time' => ['nullable', 'date'],
            'loads.*.unload_time' => ['nullable', 'date'],
            'loads.*.check_roll_at' => ['nullable', 'date'],
            'loads.*.load_type' => ['nullable', 'string', 'max:24'],
            'loads.*.status' => ['nullable', Rule::in(DyeProductionLoad::STATUSES)],
        ], [
            'production_date.required' => __('dyehouse.batch.v.production_date'), 'liquor_ratio.required' => __('dyehouse.batch.v.liquor_ratio'),
            'liquor_ratio.regex' => __('dyehouse.batch.v.liquor_ratio'), 'loads.required' => __('dyehouse.batch.v.loads'), 'loads.min' => __('dyehouse.batch.v.loads'),
            'requisition_id.exists' => __('dyehouse.batch.v.req_other_batch', ['no' => $batch->batch_no]),
            'machine_id.required' => __('dyehouse.prod.v.machine'), 'model_combo.required' => __('dyehouse.prod.v.model_combo'), 'lab_dip_ref.required' => __('dyehouse.prod.v.lab_dip'),
        ]);
        ksort($data['loads']);
        foreach (array_values($data['loads']) as $i => $l) {
            if (! empty($l['load_time']) && ! empty($l['unload_time']) && CarbonImmutable::parse($l['unload_time']) < CarbonImmutable::parse($l['load_time'])) {
                throw ValidationException::withMessages(["loads.{$i}.unload_time" => __('dyehouse.batch.v.unload_before_load', ['n' => $i + 1])]);
            }
        }
        $row = ! empty($data['original_date']) ? DyeProduction::query()->where('batch_id', $batch->id)->whereDate('production_date', $data['original_date'])->firstOrFail() : null;
        $twin = DyeProduction::query()->where('batch_id', $batch->id)->whereDate('production_date', $data['production_date'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists();
        if ($twin) {
            throw ValidationException::withMessages(['production_date' => __('dyehouse.batch.v.production_twice', ['no' => $batch->batch_no, 'date' => $data['production_date']])]);
        }
        DB::transaction(function () use ($batch, $data, $row): void {
            $head = ['production_date' => $data['production_date'], 'requisition_id' => $data['requisition_id'] ?? null, 'weight_kg' => $data['weight_kg'] ?? null,
                'dye_machine_id' => $data['machine_id'] ?? null, 'model_combo' => $data['model_combo'] ?? null, 'lab_dip_ref' => $data['lab_dip_ref'] ?? null,
                'liquor_ratio' => $data['liquor_ratio'], 'bypass_check_roll' => (bool) ($data['bypass_check_roll'] ?? false), 'scouring' => (bool) ($data['scouring'] ?? false),
                'remarks' => $data['remarks'] ?? null];
            if ($row) {
                $row->update($head);
                DyeProductionLoad::query()->where('production_id', $row->id)->delete();
            } else {
                $row = DyeProduction::query()->create($head + ['batch_id' => $batch->id, 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach (array_values($data['loads']) as $i => $l) {
                DyeProductionLoad::query()->create(['production_id' => $row->id, 'dye_machine_id' => $l['machine_id'] ?? null, 'load_time' => $l['load_time'] ?? null,
                    'unload_time' => $l['unload_time'] ?? null, 'check_roll_at' => $l['check_roll_at'] ?? null, 'load_type' => ($l['load_type'] ?? '') ?: 'Dyeing', 'status' => $l['status'] ?? null, 'sort_order' => ($i + 1) * 10]);
            }
        });

        return ApiResponse::ok($this->present($batch->refresh()), [], empty($data['original_date']) ? 201 : 200);
    }

    /**
     * Bulk Batch Production's Records: every production of a scope, newest
     * first — the card, its lot and shade, the floor's figures and its loads.
     */
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('dyehouse.batch.view', 'dyehouse.production.view');
        $scope = $request->validate(['scope' => ['required', Rule::in(['BULK', 'SAMPLE'])]])['scope'];
        $n = DyeBatches::names();
        $rows = DyeProduction::query()->join('dye_batches', 'dye_batches.id', '=', 'dye_productions.batch_id')->where('dye_batches.scope', $scope)
            ->orderByDesc('dye_productions.production_date')->orderByDesc('dye_batches.batch_no')
            ->get(['dye_productions.*', 'dye_batches.batch_no', 'dye_batches.lot_no', 'dye_batches.colour_id', 'dye_batches.buyer_id', 'dye_batches.order_no', 'dye_batches.card_qty']);
        $loads = DyeProductionLoad::query()->whereIn('production_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('production_id');

        return ApiResponse::ok($rows->map(function ($p) use ($n, $loads) {
            $mine = $loads[$p->id] ?? collect();
            $minutes = (int) $mine->sum(fn (DyeProductionLoad $l) => $l->load_time && $l->unload_time ? $l->load_time->diffInMinutes($l->unload_time) : 0);

            return [
                'batch_no' => $p->batch_no, 'production_date' => $p->production_date->toDateString(), 'lot_no' => $p->lot_no, 'colour' => $n['colours'][$p->colour_id] ?? null,
                'buyer' => $n['buyers'][$p->buyer_id] ?? null, 'order_no' => $p->order_no, 'machine' => $n['machines'][$p->dye_machine_id] ?? null,
                'weight_kg' => $p->weight_kg === null ? (float) $p->card_qty : (float) $p->weight_kg, 'model_combo' => $p->model_combo, 'lab_dip_ref' => $p->lab_dip_ref,
                'liquor_ratio' => $p->liquor_ratio, 'bypass_check_roll' => (bool) $p->bypass_check_roll, 'scouring' => (bool) $p->scouring,
                'loads' => $mine->count(), 'last_status' => $mine->last()?->status, 'duration' => $minutes ? sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60) : null,
            ];
        })->values()->all());
    }

    /**
     * Manual Daily Bulk Production: every load recorded against a bulk card,
     * newest first, with the minutes worked out from its two times.
     */
    public function loads(): JsonResponse
    {
        $this->allowEither('dyehouse.batch.view', 'dyehouse.production.view');
        $n = DyeBatches::names();
        $stamp = fn ($t) => $t?->format('Y-m-d\TH:i');
        $rows = DyeProductionLoad::query()->join('dye_productions', 'dye_productions.id', '=', 'dye_production_loads.production_id')
            ->join('dye_batches', 'dye_batches.id', '=', 'dye_productions.batch_id')->where('dye_batches.scope', 'BULK')
            ->orderByDesc('dye_productions.production_date')->orderByDesc('dye_batches.batch_no')->orderBy('dye_production_loads.sort_order')
            ->get(['dye_production_loads.*', 'dye_productions.production_date', 'dye_productions.dye_machine_id as production_machine', 'dye_batches.batch_no', 'dye_batches.lot_no',
                'dye_batches.colour_id', 'dye_batches.card_qty', 'dye_batches.status as batch_status']);

        return ApiResponse::ok($rows->map(fn ($l) => [
            'id' => $l->id, 'batch_no' => $l->batch_no, 'production_date' => CarbonImmutable::parse($l->production_date)->toDateString(), 'lot_no' => $l->lot_no,
            'colour' => $n['colours'][$l->colour_id] ?? null, 'qty' => (float) $l->card_qty, 'machine' => $n['machines'][$l->dye_machine_id ?? $l->production_machine] ?? null,
            'load_type' => $l->load_type, 'status' => $l->status, 'load_time' => $stamp($l->load_time), 'unload_time' => $stamp($l->unload_time),
            'minutes' => $l->load_time && $l->unload_time ? (int) $l->load_time->diffInMinutes($l->unload_time) : null, 'closed' => $l->batch_status === 'CLOSED',
        ])->values()->all());
    }

    /**
     * Manual Daily Bulk Production's Save Production: the load and unload
     * times the machine feed could not report — the only two cells written.
     * A machine that came off before it went on is refused.
     */
    public function saveLoads(Request $request): JsonResponse
    {
        $this->allowEither('dyehouse.batch.manage', 'dyehouse.production.manage');
        $data = $request->validate([
            'loads' => ['required', 'array', 'min:1', 'max:500'],
            'loads.*.id' => ['required', 'string'],
            'loads.*.load_time' => ['nullable', 'date'],
            'loads.*.unload_time' => ['nullable', 'date'],
        ], ['loads.required' => __('dyehouse.prod.v.no_changes'), 'loads.min' => __('dyehouse.prod.v.no_changes')]);
        $rows = DyeProductionLoad::query()->whereIn('id', collect($data['loads'])->pluck('id'))->get()->keyBy('id');
        $cards = DB::table('dye_productions')->join('dye_batches', 'dye_batches.id', '=', 'dye_productions.batch_id')
            ->whereIn('dye_productions.id', $rows->pluck('production_id'))->get(['dye_productions.id', 'dye_batches.batch_no', 'dye_batches.status', 'dye_batches.scope'])->keyBy('id');
        foreach ($data['loads'] as $k => $l) {
            $row = $rows[$l['id']] ?? null;
            $card = $row ? ($cards[$row->production_id] ?? null) : null;
            abort_unless($card && $card->scope === 'BULK', 404);
            if ($card->status === 'CLOSED') {
                throw ValidationException::withMessages(["loads.{$k}.load_time" => __('dyehouse.prod.v.batch_closed', ['no' => $card->batch_no])]);
            }
            if (! empty($l['load_time']) && ! empty($l['unload_time']) && CarbonImmutable::parse($l['unload_time']) < CarbonImmutable::parse($l['load_time'])) {
                throw ValidationException::withMessages(["loads.{$k}.unload_time" => __('dyehouse.prod.v.unload_before_load', ['no' => $card->batch_no])]);
            }
        }
        DB::transaction(function () use ($data, $rows): void {
            foreach ($data['loads'] as $l) {
                $rows[$l['id']]->update(['load_time' => $l['load_time'] ?? null, 'unload_time' => $l['unload_time'] ?? null]);
            }
        });

        return ApiResponse::ok(['saved' => count($data['loads'])]);
    }

    /** Sample Grey Fabric Indent → Batch and Program Lists: the sample cards, the sample loading plans, or the cards whose shade has no lab dip recipe yet. */
    public function lists(Request $request, string $kind): JsonResponse
    {
        $this->allow('dyehouse.batch.view');
        abort_unless(in_array($kind, self::LISTS, true), 404);
        $f = $request->validate(['from' => ['required', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $to = $f['to'] ?? CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $n = DyeBatches::names();
        $cards = fn () => DyeBatch::query()->where('scope', 'SAMPLE')->whereBetween('batch_date', [$f['from'], $to])->orderBy('batch_date')->orderBy('batch_no')->get();
        $plans = DyeBatchPlan::query()->pluck('code', 'id');
        [$columns, $rows] = match ($kind) {
            'batch-list' => [
                [['key' => 'batch_no'], ['key' => 'batch_date', 'type' => 'date'], ['key' => 'buyer'], ['key' => 'order_no'], ['key' => 'colour'], ['key' => 'qty', 'type' => 'number'],
                    ['key' => 'plan'], ['key' => 'requisition'], ['key' => 'status', 'type' => 'status']],
                $cards()->map(fn (DyeBatch $b) => ['batch_no' => $b->batch_no, 'batch_date' => $b->batch_date->toDateString(), 'buyer' => $n['buyers'][$b->buyer_id] ?? null,
                    'order_no' => $b->order_no, 'colour' => $n['colours'][$b->colour_id] ?? null, 'qty' => (float) $b->card_qty, 'plan' => $plans[$b->plan_id] ?? null,
                    'requisition' => DyeGreyIndent::query()->where('batch_id', $b->id)->value('req_no'), 'status' => $b->status])->values()->all(),
            ],
            'program-list' => [
                [['key' => 'code'], ['key' => 'name'], ['key' => 'plan_date', 'type' => 'date'], ['key' => 'batches', 'type' => 'number'], ['key' => 'qty', 'type' => 'number'], ['key' => 'laid_out', 'type' => 'number']],
                DyeBatchPlan::query()->where('scope', 'SAMPLE')->whereBetween('plan_date', [$f['from'], $to])->orderBy('plan_date')->get()->map(function (DyeBatchPlan $p) {
                    $on = DyeBatch::query()->where('plan_id', $p->id)->get(['card_qty', 'planned_start']);

                    return ['code' => $p->code, 'name' => $p->name, 'plan_date' => $p->plan_date->toDateString(), 'batches' => $on->count(), 'qty' => round((float) $on->sum('card_qty'), 2),
                        'laid_out' => $on->whereNotNull('planned_start')->count()];
                })->values()->all(),
            ],
            'pending-labdip' => [
                [['key' => 'batch_no'], ['key' => 'batch_date', 'type' => 'date'], ['key' => 'buyer'], ['key' => 'order_no'], ['key' => 'colour'], ['key' => 'qty', 'type' => 'number']],
                $cards()->filter(fn (DyeBatch $b) => ! DyeLabDipRecipe::query()->where('buyer_id', $b->buyer_id)->where('colour_id', $b->colour_id)->exists())
                    ->map(fn (DyeBatch $b) => ['batch_no' => $b->batch_no, 'batch_date' => $b->batch_date->toDateString(), 'buyer' => $n['buyers'][$b->buyer_id] ?? null,
                        'order_no' => $b->order_no, 'colour' => $n['colours'][$b->colour_id] ?? null, 'qty' => (float) $b->card_qty])->values()->all(),
            ],
        };

        return ApiResponse::ok(['report' => $kind, 'from' => $f['from'], 'to' => $to, 'columns' => $columns, 'rows' => $rows]);
    }

    private function batch(string $no): DyeBatch
    {
        return DyeBatch::query()->where('batch_no', DyeBatches::batchNo($no))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(DyeBatch $b): array
    {
        $n = DyeBatches::names();
        $stamp = fn ($t) => $t?->format('Y-m-d\TH:i');
        $productions = DyeProduction::query()->where('batch_id', $b->id)->orderByDesc('production_date')->get();
        $loads = DyeProductionLoad::query()->whereIn('production_id', $productions->pluck('id'))->orderBy('sort_order')->get()->groupBy('production_id');
        $recipes = DyeLabDipRecipe::query()->where('buyer_id', $b->buyer_id)->where('colour_id', $b->colour_id)->orderBy('recipe_no')->orderBy('option_no')->get();
        $methods = DB::table('dye_methods')->pluck('name', 'id');

        return [
            'batch_no' => $b->batch_no, 'scope' => $b->scope, 'status' => $b->status, 'lot_no' => $b->lot_no, 'card_qty' => (float) $b->card_qty, 'colour' => $n['colours'][$b->colour_id] ?? null,
            'colour_group' => $n['groups'][$b->colour_group_id] ?? null, 'buyer' => $n['buyers'][$b->buyer_id] ?? null, 'style' => $b->style_ref, 'order_no' => $b->order_no,
            'dyeing_method' => $b->dyeing_method, 'machine_id' => $b->dye_machine_id,
            'items' => DyeBatchItem::query()->where('batch_id', $b->id)->orderBy('sort_order')->get()
                ->map(fn (DyeBatchItem $i) => ['kind' => $i->kind, 'description' => $i->description, 'qty_kg' => (float) $i->qty_kg, 'rolls' => $i->rolls, 'pcs' => $i->pcs, 'yds' => (float) $i->yds])->values()->all(),
            'requisitions' => DyeGreyIndent::query()->where('batch_id', $b->id)->orderBy('req_no')->get(['id', 'req_no', 'req_date'])
                ->map(fn ($r) => ['value' => $r->id, 'label' => $r->req_no, 'date' => $r->req_date->toDateString()])->values()->all(),
            // The approved lab dip recipes for the shade the batch is matched to.
            'recipes' => $recipes->map(fn ($r) => implode(' · ', array_filter([$r->recipe_no, __('dyehouse.batch.prod.option', ['n' => $r->option_no]), $methods[$r->dye_method_id] ?? null, $r->fiber_composition])))->values()->all(),
            'productions' => $productions->map(fn (DyeProduction $p) => [
                'production_date' => $p->production_date->toDateString(), 'requisition_id' => $p->requisition_id, 'weight_kg' => $p->weight_kg === null ? null : (float) $p->weight_kg,
                'machine_id' => $p->dye_machine_id, 'model_combo' => $p->model_combo, 'lab_dip_ref' => $p->lab_dip_ref, 'liquor_ratio' => $p->liquor_ratio, 'bypass_check_roll' => (bool) $p->bypass_check_roll, 'scouring' => (bool) $p->scouring, 'remarks' => $p->remarks,
                'loads' => ($loads[$p->id] ?? collect())->map(fn (DyeProductionLoad $l) => ['machine_id' => $l->dye_machine_id, 'load_time' => $stamp($l->load_time),
                    'unload_time' => $stamp($l->unload_time), 'check_roll_at' => $stamp($l->check_roll_at), 'load_type' => $l->load_type, 'status' => $l->status])->values()->all(),
            ])->values()->all(),
        ];
    }
}
