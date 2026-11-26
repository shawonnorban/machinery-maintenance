<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\FabricPlanning\Models\FpBatchPlan;
use App\Modules\FabricPlanning\Models\FpBatchPlanLine;
use App\Modules\FabricPlanning\Models\FpHoliday;
use App\Modules\FabricPlanning\Models\FpMachineCapacity;
use App\Modules\FabricPlanning\Models\FpScheduleBatch;
use App\Modules\FabricPlanning\Models\FpSchedulePlan;
use App\Modules\FabricPlanning\Models\FpStatusArea;
use App\Modules\FabricPlanning\Models\FpStatusGroup;
use App\Modules\FabricPlanning\Models\FpStatusRule;
use App\Modules\FabricPlanning\Support\FpOps;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
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
 * Fabric Requirement → Operations (AnnotechERP 13/02), the parts beyond the
 * screen kit, as Brickland keeps them: each constraint family's areas and
 * score bands edited in place; each section's closed days; the machines of a
 * planning week against what is planned on them; a batch plan with its
 * fabric lines (BP-00001, the planned quantity the sum of the lines); and the
 * batches on a scheduling board in running order — a batch once per board.
 * Weeks, plans and boards travel by their codes, never their keys.
 */
class FpOperationsApiController extends ApiController
{
    /** Everything the forms choose from. */
    public function options(): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');
        $company = app(TenantContext::class)->companyId();
        FpOps::ensureRuleset($company);

        return ApiResponse::ok([
            'company' => ['value' => $company, 'label' => DB::table('companies')->where('id', $company)->value('name')],
            'weeks' => FpOps::weekOptions(FpBatchPlan::query()->pluck('week_start')->merge(FpMachineCapacity::query()->pluck('week_start'))->map(fn ($d) => $d->toDateString())),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get()->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->name} ({$b->short_name})"])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'type' => $c->colour_type])->values()->all(),
            'colour_groups' => DyeColourGroup::query()->where('active', true)->orderBy('display_order')->orderBy('name')->get()->map(fn ($g) => ['value' => $g->id, 'label' => "{$g->name} ({$g->code})"])->values()->all(),
            'machines' => DyeMachine::query()->orderBy('machine_no')->get()->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no.($m->capacity ? ' · '.(float) $m->capacity.' '.$m->capacity_unit : '')])->values()->all(),
            'factories' => DB::table('factories')->where('company_id', $company)->orderBy('name')->get()->map(fn ($f) => ['value' => $f->id, 'label' => "{$f->name} ({$f->code})"])->values()->all(),
            'sections' => DB::table('departments')->where('company_id', $company)->orderBy('name')->get()->map(fn ($d) => ['value' => $d->id, 'label' => $d->name, 'code' => $d->code])->values()->all(),
            'groups' => $this->groupRows(),
        ]);
    }

    /* ---------------- Planning Constraint Ruleset ---------------- */

    public function groups(): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');
        FpOps::ensureRuleset(app(TenantContext::class)->companyId());

        return ApiResponse::ok($this->groupRows());
    }

    public function areas(int $sl): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->areaRows($this->group($sl)));
    }

    /** Save the family's areas as listed: a listed row is kept or added, an unlisted one removed. */
    public function saveAreas(Request $request, int $sl): JsonResponse
    {
        $this->allow('fabricplanning.plan.manage');
        $group = $this->group($sl);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.sl' => ['required', 'integer', 'min:0', 'max:999'],
            'rows.*.code' => ['required', 'string', 'max:24'],
            'rows.*.name' => ['required', 'string', 'max:160'],
            'rows.*.wt_factor' => ['required', 'numeric', 'min:0', 'max:999'],
            'rows.*.active' => ['boolean'],
        ], ['rows.*.name.required' => __('fabricplanning.ops.v.area_name'), 'rows.*.code.required' => __('fabricplanning.ops.v.area_code'), 'rows.*.wt_factor.required' => __('fabricplanning.ops.v.wt')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $existing = FpStatusArea::query()->where('group_id', $group->id)->get()->keyBy('id');
        $errors = [];
        $codes = [];
        foreach ($rows as $i => $r) {
            $code = strtoupper(trim($r['code']));
            $current = isset($r['id']) ? $existing->get($r['id']) : null;
            if ($current && $current->code !== $code) {
                $errors["rows.{$i}.code"] = __('fabricplanning.ops.v.code_fixed');
            } elseif (in_array($code, $codes, true) || (! $current && FpStatusArea::query()->where('code', $code)->exists())) {
                $errors["rows.{$i}.code"] = __('fabricplanning.ops.v.code_taken');
            }
            $codes[] = $code;
        }
        $kept = collect($rows)->pluck('id')->filter()->all();
        $removed = $existing->keys()->diff($kept);
        if ($removed->isNotEmpty() && FpStatusArea::query()->whereIn('parent_id', $removed)->whereNotIn('id', $removed)->exists()) {
            $errors['rows'] = __('fabricplanning.ops.v.parent_in_use');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($rows, $existing, $removed, $group, $company): void {
            FpStatusArea::query()->whereIn('id', $removed)->update(['parent_id' => null]);
            FpStatusArea::query()->whereIn('id', $removed)->delete();
            foreach ($rows as $r) {
                $values = ['sl' => $r['sl'], 'name' => trim($r['name']), 'wt_factor' => $r['wt_factor'], 'active' => (bool) ($r['active'] ?? true)];
                $current = isset($r['id']) ? $existing->get($r['id']) : null;
                $current
                    ? $current->update($values)
                    : FpStatusArea::query()->create($values + ['company_id' => $company, 'group_id' => $group->id, 'code' => strtoupper(trim($r['code']))]);
            }
        });

        return ApiResponse::ok(['rows' => $this->areaRows($group), 'groups' => $this->groupRows()]);
    }

    public function rules(int $sl): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->ruleRows($this->group($sl)));
    }

    /** Save the family's score bands as listed; bands may not overlap. */
    public function saveRules(Request $request, int $sl): JsonResponse
    {
        $this->allow('fabricplanning.plan.manage');
        $group = $this->group($sl);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.score_from' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rows.*.score_to' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rows.*.status_code' => ['required', 'string', 'max:24'],
            'rows.*.status_desc' => ['required', 'string', 'max:160'],
            'rows.*.color_code' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'rows.*.green_rank' => ['required', 'numeric', 'min:0', 'max:1'],
        ], ['rows.*.color_code.regex' => __('fabricplanning.ops.v.colour'), 'rows.*.green_rank.max' => __('fabricplanning.ops.v.rank'), 'rows.*.status_desc.required' => __('fabricplanning.ops.v.rule_desc')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $existing = FpStatusRule::query()->where('group_id', $group->id)->get()->keyBy('id');
        $errors = [];
        $codes = [];
        foreach ($rows as $i => $r) {
            $code = strtoupper(trim($r['status_code']));
            $current = isset($r['id']) ? $existing->get($r['id']) : null;
            if ($current && $current->status_code !== $code) {
                $errors["rows.{$i}.status_code"] = __('fabricplanning.ops.v.code_fixed');
            } elseif (in_array($code, $codes, true)) {
                $errors["rows.{$i}.status_code"] = __('fabricplanning.ops.v.code_taken');
            }
            if ((float) $r['score_to'] < (float) $r['score_from']) {
                $errors["rows.{$i}.score_to"] = __('fabricplanning.ops.v.band');
            }
            $codes[] = $code;
        }
        // Bands within a family must not overlap.
        $sorted = collect($rows)->map(fn ($r, $i) => $r + ['i' => $i])->sortBy(fn ($r) => (float) $r['score_from'])->values();
        for ($k = 1; $k < $sorted->count(); $k++) {
            if ((float) $sorted[$k]['score_from'] <= (float) $sorted[$k - 1]['score_to']) {
                $errors["rows.{$sorted[$k]['i']}.score_from"] = __('fabricplanning.ops.v.overlap', ['code' => strtoupper($sorted[$k - 1]['status_code'])]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($sorted, $existing, $group, $company): void {
            FpStatusRule::query()->where('group_id', $group->id)->whereNotIn('id', $sorted->pluck('id')->filter()->all())->delete();
            foreach ($sorted as $n => $r) {
                $values = ['score_from' => $r['score_from'], 'score_to' => $r['score_to'], 'status_desc' => trim($r['status_desc']), 'color_code' => strtoupper($r['color_code']), 'green_rank' => $r['green_rank'], 'sort_order' => $n + 1];
                $current = isset($r['id']) ? $existing->get($r['id']) : null;
                $current
                    ? $current->update($values)
                    : FpStatusRule::query()->create($values + ['company_id' => $company, 'group_id' => $group->id, 'status_code' => strtoupper(trim($r['status_code']))]);
            }
        });

        return ApiResponse::ok(['rows' => $this->ruleRows($group), 'groups' => $this->groupRows()]);
    }

    /* ---------------- Factory Calendar & Holidays ---------------- */

    /** The sections, each with how many days it is closed. */
    public function sections(): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->sectionRows());
    }

    public function sectionDays(string $code): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->dayRows($this->section($code)->id));
    }

    /** Save a section's closed days as listed. */
    public function saveSectionDays(Request $request, string $code): JsonResponse
    {
        $this->allow('fabricplanning.plan.manage');
        $section = $this->section($code);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.factory_id' => ['required', Rule::exists('factories', 'id')->where('company_id', $company)],
            'rows.*.day' => ['required', 'date'],
            'rows.*.day_type' => ['required', Rule::in(FpHoliday::TYPES)],
            'rows.*.name' => ['nullable', 'string', 'max:120'],
        ], ['rows.*.factory_id.required' => __('fabricplanning.ops.v.office'), 'rows.*.day.required' => __('fabricplanning.ops.v.day')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $seen = [];
        foreach ($rows as $i => $r) {
            $key = $r['factory_id'].'|'.substr($r['day'], 0, 10);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["rows.{$i}.day" => __('fabricplanning.ops.v.day_taken')]);
            }
            $seen[$key] = true;
        }
        DB::transaction(function () use ($rows, $section, $company): void {
            $existing = FpHoliday::query()->where('section_id', $section->id)->get()->keyBy('id');
            FpHoliday::query()->where('section_id', $section->id)->whereNotIn('id', collect($rows)->pluck('id')->filter()->all())->delete();
            foreach ($rows as $r) {
                $values = ['factory_id' => $r['factory_id'], 'day' => substr($r['day'], 0, 10), 'day_type' => $r['day_type'], 'name' => $r['name'] ?? null];
                $current = isset($r['id']) ? $existing->get($r['id']) : null;
                $current ? $current->update($values) : FpHoliday::query()->create($values + ['company_id' => $company, 'section_id' => $section->id]);
            }
        });

        return ApiResponse::ok(['rows' => $this->dayRows($section->id), 'sections' => $this->sectionRows()]);
    }

    /* ---------------- Dye Machine Capacity Board ---------------- */

    /** The weeks with machine capacity, each with how many machines. */
    public function capacityWeeks(): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok(FpMachineCapacity::query()->selectRaw('week_start, count(*) as machines')->groupBy('week_start')->orderByDesc('week_start')->get()
            ->map(fn ($w) => ['key' => $w->week_start->toDateString(), 'label' => FpOps::weekLabel($w->week_start->toDateString()), 'count' => (int) $w->machines])->values()->all());
    }

    /** A week's machines: what each can take, and what the batch plans already put on it. */
    public function capacityMachines(string $week): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');
        $start = FpOps::weekStart($week)->toDateString();
        $caps = FpMachineCapacity::query()->whereDate('week_start', $start)->get();
        $machines = DyeMachine::query()->whereIn('id', $caps->pluck('dye_machine_id'))->pluck('machine_no', 'id');
        $planned = FpBatchPlan::query()->whereDate('week_start', $start)->where('status', '!=', 'CANCELLED')->whereNotNull('dye_machine_id')
            ->selectRaw('dye_machine_id, sum(batches) as batches, sum(bp_qty) as qty')->groupBy('dye_machine_id')->get()->keyBy('dye_machine_id');
        $rows = $caps->map(function (FpMachineCapacity $c) use ($machines, $planned): array {
            $maxKg = round($c->capacity_kg * $c->max_load_pct / 100, 2);
            $qty = (float) ($planned[$c->dye_machine_id]->qty ?? 0);

            return [
                'id' => $c->id, 'machine' => $machines[$c->dye_machine_id] ?? '—', 'capacity_kg' => $c->capacity_kg, 'working_days' => $c->working_days, 'holidays' => $c->holidays,
                'max_load_pct' => $c->max_load_pct, 'max_load_kg' => $maxKg, 'capacity_hours' => $c->capacity_hours, 'avg_batch_qty' => $c->avg_batch_qty,
                'planned_batches' => (int) ($planned[$c->dye_machine_id]->batches ?? 0), 'planned_kg' => $qty, 'free_kg' => round($maxKg - $qty, 2),
                'load_pct' => $maxKg > 0 ? (int) round($qty * 100 / $maxKg) : 0,
            ];
        })->sortBy('machine')->values()->all();

        return ApiResponse::ok(['week' => FpOps::weekLabel($start), 'rows' => $rows]);
    }

    /* ---------------- Fabric Batch Plan Entry ---------------- */

    public function batchPlans(): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->planRows(FpBatchPlan::query()->orderByDesc('bp_no')->get()));
    }

    public function batchPlan(string $bpNo): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->planPayload($this->plan($bpNo)));
    }

    /** New batch plan: from the Batch Planning tab with fabric lines, or the New tab with a typed BP quantity. */
    public function storeBatchPlan(Request $request): JsonResponse
    {
        $this->allow('fabricplanning.plan.manage');
        $data = $this->planInput($request);
        $company = app(TenantContext::class)->companyId();
        $plan = DB::transaction(function () use ($data, $company): FpBatchPlan {
            $plan = FpBatchPlan::query()->create($data['header'] + ['company_id' => $company, 'bp_no' => FpOps::nextNumber('fp_batch_plans', 'bp_no', 'BP', $company), 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($plan, $data['lines']);

            return $plan;
        });

        return ApiResponse::created($this->planPayload($plan->fresh()));
    }

    public function updateBatchPlan(Request $request, string $bpNo): JsonResponse
    {
        $this->allow('fabricplanning.plan.manage');
        $plan = $this->plan($bpNo);
        $data = $this->planInput($request);
        DB::transaction(function () use ($plan, $data): void {
            $plan->update($data['header']);
            if ($data['lines'] !== null) {
                FpBatchPlanLine::query()->where('batch_plan_id', $plan->id)->delete();
                $this->writeLines($plan, $data['lines']);
            }
        });

        return ApiResponse::ok($this->planPayload($plan->fresh()));
    }

    /* ---------------- Batch Schedule Board ---------------- */

    public function scheduleBatches(string $planNo): JsonResponse
    {
        $this->allow('fabricplanning.plan.view');

        return ApiResponse::ok($this->boardPayload($this->board($planNo)));
    }

    /** Save the board's batches in the order listed; a batch is on a board once. */
    public function saveScheduleBatches(Request $request, string $planNo): JsonResponse
    {
        $this->allow('fabricplanning.plan.manage');
        $board = $this->board($planNo);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.bp_no' => ['required', 'string', 'distinct'],
            'rows.*.dye_machine_id' => ['nullable', Rule::exists('dye_machines', 'id')->where('company_id', $company)],
            'rows.*.planned_start' => ['nullable', 'date'],
            'rows.*.planned_end' => ['nullable', 'date'],
        ], ['rows.*.bp_no.required' => __('fabricplanning.ops.v.batch'), 'rows.*.bp_no.distinct' => __('fabricplanning.ops.v.batch_once')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $plans = FpBatchPlan::query()->whereIn('bp_no', array_column($rows, 'bp_no'))->get()->keyBy('bp_no');
        $errors = [];
        foreach ($rows as $i => $r) {
            if (! isset($plans[$r['bp_no']])) {
                $errors["rows.{$i}.bp_no"] = __('fabricplanning.ops.v.batch');
            } elseif ($plans[$r['bp_no']]->status === 'CANCELLED') {
                $errors["rows.{$i}.bp_no"] = __('fabricplanning.ops.v.batch_cancelled', ['no' => $r['bp_no']]);
            }
            if (! empty($r['planned_start']) && ! empty($r['planned_end']) && $r['planned_end'] < $r['planned_start']) {
                $errors["rows.{$i}.planned_end"] = __('fabricplanning.ops.v.end_before_start');
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($rows, $plans, $board, $company): void {
            FpScheduleBatch::query()->where('schedule_plan_id', $board->id)->delete();
            foreach ($rows as $n => $r) {
                FpScheduleBatch::query()->create([
                    'company_id' => $company, 'schedule_plan_id' => $board->id, 'batch_plan_id' => $plans[$r['bp_no']]->id, 'sequence' => $n + 1,
                    'dye_machine_id' => $r['dye_machine_id'] ?? $plans[$r['bp_no']]->dye_machine_id, 'planned_start' => $r['planned_start'] ?? null, 'planned_end' => $r['planned_end'] ?? null,
                ]);
            }
        });

        return ApiResponse::ok($this->boardPayload($board));
    }

    /* ---------------- helpers ---------------- */

    /** @return list<array<string, mixed>> */
    private function groupRows(): array
    {
        $areas = FpStatusArea::query()->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');
        $rules = FpStatusRule::query()->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');

        return FpStatusGroup::query()->orderBy('sl')->get()->map(fn (FpStatusGroup $g) => [
            'id' => $g->id, 'key' => (string) $g->sl, 'label' => "{$g->sl}. {$g->name}", 'areas' => (int) ($areas[$g->id] ?? 0), 'rules' => (int) ($rules[$g->id] ?? 0),
        ])->values()->all();
    }

    private function group(int $sl): FpStatusGroup
    {
        FpOps::ensureRuleset(app(TenantContext::class)->companyId());

        return FpStatusGroup::query()->where('sl', $sl)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function areaRows(FpStatusGroup $group): array
    {
        $codes = FpStatusArea::query()->pluck('code', 'id');

        return FpStatusArea::query()->where('group_id', $group->id)->orderBy('sl')->orderBy('code')->get()->map(fn (FpStatusArea $a) => [
            'id' => $a->id, 'sl' => $a->sl, 'code' => $a->code, 'parent' => $a->parent_id ? ($codes[$a->parent_id] ?? null) : null, 'name' => $a->name, 'wt_factor' => $a->wt_factor, 'active' => $a->active,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function ruleRows(FpStatusGroup $group): array
    {
        return FpStatusRule::query()->where('group_id', $group->id)->orderBy('score_from')->get()->map(fn (FpStatusRule $r) => [
            'id' => $r->id, 'score_from' => $r->score_from, 'score_to' => $r->score_to, 'status_code' => $r->status_code, 'status_desc' => $r->status_desc,
            'color_code' => $r->color_code, 'green_rank' => $r->green_rank, 'standing' => FpStatusRule::standing($r->green_rank),
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function sectionRows(): array
    {
        $company = app(TenantContext::class)->companyId();
        $counts = FpHoliday::query()->whereNotNull('section_id')->selectRaw('section_id, count(*) as n')->groupBy('section_id')->pluck('n', 'section_id');

        return DB::table('departments')->where('company_id', $company)->orderBy('name')->get()
            ->map(fn ($d) => ['key' => $d->code, 'label' => $d->name, 'count' => (int) ($counts[$d->id] ?? 0)])->values()->all();
    }

    private function section(string $code): object
    {
        return DB::table('departments')->where('company_id', app(TenantContext::class)->companyId())->where('code', $code)->first() ?? abort(404);
    }

    /** @return list<array<string, mixed>> */
    private function dayRows(string $sectionId): array
    {
        return FpHoliday::query()->where('section_id', $sectionId)->orderBy('day')->get()->map(fn (FpHoliday $h) => [
            'id' => $h->id, 'factory_id' => $h->factory_id, 'day' => $h->day->toDateString(), 'day_type' => $h->day_type, 'name' => $h->name,
        ])->values()->all();
    }

    /** @return array{header: array<string, mixed>, lines: list<array<string, mixed>>|null} */
    private function planInput(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $withLines = $request->has('lines');
        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_job' => ['nullable', 'string', 'max:120'],
            'order_no' => ['nullable', 'string', 'max:64'],
            'order_cat' => ['nullable', 'string', 'max:64'],
            'colour_type' => ['nullable', Rule::in(FpBatchPlan::COLOUR_TYPES)],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'colour_group_id' => [$withLines ? 'required' : 'nullable', Rule::exists('dye_colour_groups', 'id')->where('company_id', $company)],
            'dyeing_method' => ['required', Rule::in(['EXHAUST', 'COLD_PAD_BATCH', 'CONTINUOUS', 'PIGMENT'])],
            'dye_machine_id' => ['nullable', Rule::exists('dye_machines', 'id')->where('company_id', $company)],
            'shipment_date' => ['nullable', 'date'],
            'booking_confirm_date' => ['nullable', 'date'],
            'production_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'pending_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'batches' => ['required', 'integer', 'min:1', 'max:999'],
            'bp_qty' => [$withLines ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:99999999'],
            'status' => ['required', Rule::in(FpBatchPlan::STATUSES)],
            'lines' => [$withLines ? 'required' : 'nullable', 'array', 'min:1'],
            'lines.*.fabric' => ['required', 'string', 'max:240'],
            'lines.*.grey_req' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.bp_plus' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.bp_minus' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.bp_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ], [
            'week_start.required' => __('fabricplanning.ops.v.week'), 'dyeing_method.required' => __('fabricplanning.ops.v.method'), 'colour_group_id.required' => __('fabricplanning.ops.v.colour_group'),
            'batches.required' => __('fabricplanning.ops.v.batches'), 'batches.min' => __('fabricplanning.ops.v.batches'), 'status.required' => __('fabricplanning.ops.v.status'),
            'bp_qty.required' => __('fabricplanning.ops.v.bp_qty'), 'bp_qty.gt' => __('fabricplanning.ops.v.bp_qty'),
            'lines.required' => __('fabricplanning.ops.v.lines'), 'lines.min' => __('fabricplanning.ops.v.lines'),
            'lines.*.fabric.required' => __('fabricplanning.ops.v.fabric'), 'lines.*.bp_qty.required' => __('fabricplanning.ops.v.line_qty'), 'lines.*.bp_qty.gt' => __('fabricplanning.ops.v.line_qty'),
        ]);
        $lines = null;
        if ($withLines) {
            ksort($data['lines']);
            $lines = array_values($data['lines']);
            // The planned quantity is the sum of the lines; the header cannot claim another total.
            $data['bp_qty'] = round(array_sum(array_map(fn ($l) => (float) $l['bp_qty'], $lines)), 2);
        }
        $data['week_start'] = FpOps::weekStart((string) $data['week_start'])->toDateString();
        unset($data['lines']);

        return ['header' => $data, 'lines' => $lines];
    }

    /** @param list<array<string, mixed>>|null $lines */
    private function writeLines(FpBatchPlan $plan, ?array $lines): void
    {
        foreach ($lines ?? [] as $n => $l) {
            FpBatchPlanLine::query()->create([
                'company_id' => $plan->company_id, 'batch_plan_id' => $plan->id, 'fabric' => trim($l['fabric']), 'grey_req' => $l['grey_req'] ?? 0,
                'bp_plus' => $l['bp_plus'] ?? 0, 'bp_minus' => $l['bp_minus'] ?? 0, 'bp_qty' => $l['bp_qty'], 'sort_order' => $n + 1,
            ]);
        }
    }

    private function plan(string $bpNo): FpBatchPlan
    {
        return FpBatchPlan::query()->where('bp_no', strtoupper($bpNo))->first()
            ?? throw ValidationException::withMessages(['bp_no' => __('fabricplanning.ops.v.no_plan', ['no' => strtoupper($bpNo)])]);
    }

    /** @return array<string, mixed> */
    private function planPayload(FpBatchPlan $plan): array
    {
        $row = $this->planRows(collect([$plan]))[0];
        $row['lines'] = FpBatchPlanLine::query()->where('batch_plan_id', $plan->id)->orderBy('sort_order')->get()->map(fn (FpBatchPlanLine $l) => [
            'fabric' => $l->fabric, 'grey_req' => $l->grey_req, 'bp_plus' => $l->bp_plus, 'bp_minus' => $l->bp_minus, 'bp_qty' => $l->bp_qty, 'balance' => round($l->grey_req - $l->bp_qty, 4),
        ])->values()->all();

        return $row;
    }

    /**
     * @param  Collection<int, FpBatchPlan>  $plans
     * @return list<array<string, mixed>>
     */
    private function planRows(Collection $plans): array
    {
        $buyers = Buyer::query()->whereIn('id', $plans->pluck('buyer_id')->filter())->get()->keyBy('id');
        $colours = Colour::query()->whereIn('id', $plans->pluck('colour_id')->filter())->pluck('name', 'id');
        $groups = DyeColourGroup::query()->whereIn('id', $plans->pluck('colour_group_id')->filter())->pluck('name', 'id');
        $machines = DyeMachine::query()->whereIn('id', $plans->pluck('dye_machine_id')->filter())->pluck('machine_no', 'id');
        $grey = FpBatchPlanLine::query()->whereIn('batch_plan_id', $plans->pluck('id'))->selectRaw('batch_plan_id, sum(grey_req) as grey, count(*) as n')->groupBy('batch_plan_id')->get()->keyBy('batch_plan_id');

        return $plans->map(fn (FpBatchPlan $p) => [
            'bp_no' => $p->bp_no, 'week_start' => $p->week_start->toDateString(), 'week' => FpOps::weekLabel($p->week_start->toDateString()),
            'buyer_id' => $p->buyer_id, 'buyer' => isset($buyers[$p->buyer_id]) ? $buyers[$p->buyer_id]->short_name : null, 'style_job' => $p->style_job, 'order_no' => $p->order_no, 'order_cat' => $p->order_cat,
            'colour_type' => $p->colour_type, 'colour_id' => $p->colour_id, 'colour' => $colours[$p->colour_id] ?? null, 'colour_group_id' => $p->colour_group_id, 'colour_group' => $groups[$p->colour_group_id] ?? null,
            'dyeing_method' => $p->dyeing_method, 'dye_machine_id' => $p->dye_machine_id, 'machine' => $machines[$p->dye_machine_id] ?? null,
            'shipment_date' => $p->shipment_date?->toDateString(), 'booking_confirm_date' => $p->booking_confirm_date?->toDateString(), 'production_hours' => $p->production_hours, 'pending_qty' => $p->pending_qty,
            'batches' => $p->batches, 'bp_qty' => $p->bp_qty, 'grey_req' => isset($grey[$p->id]) ? (float) $grey[$p->id]->grey : null, 'lines_count' => (int) ($grey[$p->id]->n ?? 0), 'status' => $p->status,
        ])->values()->all();
    }

    private function board(string $planNo): FpSchedulePlan
    {
        return FpSchedulePlan::query()->where('plan_no', strtoupper($planNo))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function boardPayload(FpSchedulePlan $board): array
    {
        $rows = FpScheduleBatch::query()->where('schedule_plan_id', $board->id)->orderBy('sequence')->get();
        $plans = FpBatchPlan::query()->whereIn('id', $rows->pluck('batch_plan_id'))->get()->keyBy('id');
        $listed = collect($this->planRows($plans->values()))->keyBy('bp_no');
        $machines = DyeMachine::query()->pluck('machine_no', 'id');

        return [
            'plan_no' => $board->plan_no, 'name' => $board->name,
            'rows' => $rows->map(fn (FpScheduleBatch $b) => [
                'bp_no' => $plans[$b->batch_plan_id]->bp_no, 'plan' => $listed[$plans[$b->batch_plan_id]->bp_no] ?? null, 'dye_machine_id' => $b->dye_machine_id, 'machine' => $machines[$b->dye_machine_id] ?? null,
                'planned_start' => $b->planned_start?->toDateString(), 'planned_end' => $b->planned_end?->toDateString(), 'sequence' => $b->sequence,
            ])->values()->all(),
            // The batch plans that can be placed: any not cancelled.
            'batch_plans' => collect($this->planRows(FpBatchPlan::query()->where('status', '!=', 'CANCELLED')->orderBy('bp_no')->get()))->values()->all(),
        ];
    }
}
