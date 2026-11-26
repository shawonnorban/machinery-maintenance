<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmBundleIn;
use App\Modules\Assembly\Models\AsmBundleOut;
use App\Modules\Assembly\Models\AsmFinishingIn;
use App\Modules\Assembly\Models\AsmFinishingOut;
use App\Modules\Assembly\Models\AsmQualityIn;
use App\Modules\Assembly\Models\AsmQualityInspection;
use App\Modules\Assembly\Models\AsmQualityOut;
use App\Modules\Assembly\Support\FinishingFloor;
use App\Modules\Assembly\Support\SewingFloor;
use App\Modules\Cutting\Models\CutBundle;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Quality Management (AnnotechERP 20/03), as Brickland keeps
 * it: Quality IN — a bundle out of Finishing scanned onto a quality line
 * (Section > Floor > Line) and received once with the good pieces finishing
 * sent on; Quality Inspection — the bundle judged into Pass, Reject and
 * Rework adding up to what is inspected, with the defect, the rework coming
 * back for another round once a supervisor marks it completed; Quality OUT —
 * the judged bundle released to the next process, refused while rework is
 * still open; and the Quality Dashboard with its trend, inspection result,
 * Target vs Actual, the ten report tabs and a bundle's history.
 */
class QualityApiController extends ApiController
{
    private const VIEWS = ['line', 'floor', 'section', 'unit'];

    private const MAX_RANGE_DAYS = 92;

    private const KINDS = ['in', 'inspect', 'out'];

    private ?Collection $lineIndex = null;

    private ?Collection $finishingLines = null;

    private ?Collection $sewingLines = null;

    private array $orders = [];

    private array $users = [];

    private ?Collection $buyerNames = null;

    /** Finishing OUT times by id, preloaded for a long list. */
    private array $finOutTimes = [];

    /** What the desks and the dashboard pick from: the quality sections, floors and lines, the shifts and the defect types. */
    public function options(): JsonResponse
    {
        $this->allow('assembly.quality.view');
        $active = $this->lineIndex()->where('active', true);
        $filed = $active->filter(fn ($l) => $l->section_id && $l->floor_id);

        return ApiResponse::ok([
            'today' => FinishingFloor::today()->toDateString(),
            'sections' => $filed->groupBy('section_id')->map(fn ($g, $id) => ['value' => $id, 'label' => $g->first()->section])->sortBy('label')->values()->all(),
            'floors' => $filed->groupBy(fn ($l) => $l->section_id.'|'.$l->floor_id)->map(fn ($g) => ['value' => $g->first()->floor_id, 'label' => $g->first()->floor, 'section_id' => $g->first()->section_id])->sortBy('label')->values()->all(),
            'lines' => $filed->map(fn ($l) => ['value' => $l->id, 'label' => $l->label, 'code' => $l->code, 'section_id' => $l->section_id, 'floor_id' => $l->floor_id])->values()->all(),
            'unfiled' => $active->reject(fn ($l) => $l->section_id && $l->floor_id)->pluck('label')->values()->all(),
            'shifts' => DB::table('hr_shifts')->where('company_id', $this->caller()->companyId)->where('active', true)->orderBy('start_time')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.($s->code ? ' ('.$s->code.')' : '')])->all(),
            'defect_types' => AsmQualityInspection::DEFECT_TYPES,
        ]);
    }

    /** A quality line today — what the desks show once a line is picked. */
    public function line(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.view');
        $q = $request->validate(['line_id' => ['required', 'string', 'max:26']]);

        return ApiResponse::ok($this->lineToday($this->pickLine($q['line_id'])));
    }

    /**
     * What a scanned bundle resolves to at a desk — its card, where it stands,
     * what this desk would judge, the open rework — and, when it may not be
     * confirmed here, why. Nothing is saved.
     */
    public function resolve(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40'], 'line_id' => ['required', 'string', 'max:26'], 'kind' => ['required', Rule::in(self::KINDS)]]);
        $line = $this->pickLine($q['line_id']);
        $card = SewingFloor::card($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $q['code']])]);
        [$finOut, $qin, $insps, $qout] = $this->stages($card);
        $state = $qin ? $this->state($qin, $insps, $qout) : null;
        $blocker = match ($q['kind']) {
            'in' => $this->inBlocker($card, $finOut, $qin),
            'inspect' => $this->inspectBlocker($card, $qin, $state, $line),
            default => $this->outBlocker($card, $qin, $state, $line),
        };
        $open = $state['open_rework'] ?? null;

        return ApiResponse::ok([
            'bundle' => $this->snapshot($card, $finOut, $qin, $state),
            'inspection' => $q['kind'] === 'inspect' && ! $blocker ? ['qty' => $state['to_inspect'], 'round' => $state['next_round']] : null,
            'rework' => $open && $q['kind'] !== 'in' ? ['inspection_id' => $open->id, 'status' => $open->rework_status, 'qty' => $open->rework_qty, 'round' => $open->round] : null,
            'line' => $this->lineToday($line),
            'blocker' => $blocker,
        ]);
    }

    /** Today's scans at a desk, every quality line, newest first. */
    public function recent(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.view');
        $q = $request->validate(['kind' => ['required', Rule::in(self::KINDS)]]);
        $today = FinishingFloor::today()->toDateString();
        if ($q['kind'] === 'in') {
            $ins = AsmQualityIn::query()->whereDate('in_date', $today)->orderByDesc('created_at')->orderByDesc('id')->get();
            $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');

            return ApiResponse::ok(['date' => $today, 'rows' => $ins->map(fn ($i) => $this->presentIn($i, $cards[$i->bundle_id]))->values()->all()]);
        }
        $rows = $q['kind'] === 'inspect'
            ? AsmQualityInspection::query()->whereDate('inspected_on', $today)->orderByDesc('created_at')->orderByDesc('id')->get()
            : AsmQualityOut::query()->whereDate('out_date', $today)->orderByDesc('created_at')->orderByDesc('id')->get();
        $ins = AsmQualityIn::query()->whereIn('id', $rows->pluck('quality_in_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');
        $present = $q['kind'] === 'inspect'
            ? fn ($r) => $this->presentInspection($r, $ins[$r->quality_in_id], $cards[$ins[$r->quality_in_id]->bundle_id])
            : fn ($r) => $this->presentOut($r, $ins[$r->quality_in_id], $cards[$ins[$r->quality_in_id]->bundle_id]);

        return ApiResponse::ok(['date' => $today, 'rows' => $rows->map($present)->values()->all()]);
    }

    /** Receive a bundle out of Finishing onto a quality line, once, with the good pieces finishing sent on. */
    public function receive(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.manage');
        $data = $this->location($request);
        $line = $this->locatedLine($data);

        $in = DB::transaction(function () use ($data, $line) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $card = CutBundle::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            [$finOut, $qin] = $this->stages($card);
            if ($why = $this->inBlocker($card, $finOut, $qin)) {
                throw ValidationException::withMessages(['code' => $why]);
            }

            return AsmQualityIn::query()->create([
                'bundle_id' => $card->id, 'finishing_out_id' => $finOut->id, 'in_date' => FinishingFloor::today()->toDateString(),
                'section_id' => $line->section_id, 'floor_id' => $line->floor_id, 'line_id' => $line->id, 'qty' => $finOut->good_qty,
                'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        return ApiResponse::created($this->presentIn($in, CutBundle::query()->findOrFail($in->bundle_id)));
    }

    /** Judge what stands to be inspected on a bundle: Pass + Reject + Rework, with the defect when anything failed. */
    public function inspect(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.manage');
        $data = $this->location($request, [
            'pass_qty' => ['required', 'integer', 'min:0', 'max:999999'], 'reject_qty' => ['required', 'integer', 'min:0', 'max:999999'], 'rework_qty' => ['required', 'integer', 'min:0', 'max:999999'],
            'defect_type' => ['nullable', Rule::in(AsmQualityInspection::DEFECT_TYPES)], 'remark' => ['nullable', 'string', 'max:300'],
        ]);
        $line = $this->locatedLine($data);
        $failed = (int) $data['reject_qty'] + (int) $data['rework_qty'];
        $defect = $data['defect_type'] ?? null;
        $remark = isset($data['remark']) ? trim($data['remark']) : null;
        if ($failed > 0 && ! $defect) {
            throw ValidationException::withMessages(['defect_type' => __('assembly.v.qm_defect')]);
        }
        if ($failed === 0 && $defect) {
            throw ValidationException::withMessages(['defect_type' => __('assembly.v.qm_defect_none')]);
        }
        if ($defect === 'OTHER' && ! $remark) {
            throw ValidationException::withMessages(['remark' => __('assembly.v.qm_remark')]);
        }

        $row = DB::transaction(function () use ($data, $line, $defect, $remark) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $qin = AsmQualityIn::query()->where('bundle_id', $card->id)->lockForUpdate()->first();
            [, , $insps, $qout] = $this->stages($card);
            $state = $qin ? $this->state($qin, $insps, $qout) : null;
            if ($why = $this->inspectBlocker($card, $qin, $state, $line)) {
                throw ValidationException::withMessages(['code' => $why]);
            }
            $sum = (int) $data['pass_qty'] + (int) $data['reject_qty'] + (int) $data['rework_qty'];
            if ($sum !== $state['to_inspect']) {
                throw ValidationException::withMessages(['pass_qty' => __('assembly.v.qm_sum', ['sum' => $sum, 'qty' => $state['to_inspect']])]);
            }

            return AsmQualityInspection::query()->create([
                'quality_in_id' => $qin->id, 'round' => $state['next_round'], 'inspected_on' => FinishingFloor::today()->toDateString(), 'inspected_qty' => $sum,
                'pass_qty' => (int) $data['pass_qty'], 'reject_qty' => (int) $data['reject_qty'], 'rework_qty' => (int) $data['rework_qty'],
                'defect_type' => $defect, 'remark' => $remark ?: null, 'rework_status' => (int) $data['rework_qty'] > 0 ? 'PENDING' : null,
                'created_by' => $this->caller()->auditUserId(),
            ]);
        });
        $qin = AsmQualityIn::query()->findOrFail($row->quality_in_id);

        return ApiResponse::created($this->presentInspection($row, $qin, CutBundle::query()->findOrFail($qin->bundle_id)));
    }

    /** Release a judged bundle from Quality, once nothing on it is left to judge. */
    public function sendOut(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.manage');
        $data = $this->location($request, ['remarks' => ['nullable', 'string', 'max:300']]);
        $line = $this->locatedLine($data);

        $out = DB::transaction(function () use ($data, $line) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $qin = AsmQualityIn::query()->where('bundle_id', $card->id)->lockForUpdate()->first();
            [, , $insps, $qout] = $this->stages($card);
            $state = $qin ? $this->state($qin, $insps, $qout) : null;
            if ($why = $this->outBlocker($card, $qin, $state, $line)) {
                throw ValidationException::withMessages(['code' => $why]);
            }

            return AsmQualityOut::query()->create([
                'quality_in_id' => $qin->id, 'out_date' => FinishingFloor::today()->toDateString(),
                'pass_qty' => $state['pass'], 'reject_qty' => $state['reject'], 'rework_qty' => $state['rework'], 'final_status' => $state['final_status'],
                'remarks' => isset($data['remarks']) && trim($data['remarks']) !== '' ? trim($data['remarks']) : null, 'created_by' => $this->caller()->auditUserId(),
            ]);
        });
        $qin = AsmQualityIn::query()->findOrFail($out->quality_in_id);

        return ApiResponse::created($this->presentOut($out, $qin, CutBundle::query()->findOrFail($qin->bundle_id)));
    }

    /** Move an inspection's rework on — pending → processing → completed (or straight to completed); a supervisor's call. */
    public function reworkStatus(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.supervise');
        $data = $request->validate(['inspection_id' => ['required', 'string', 'max:26'], 'status' => ['required', Rule::in(['PROCESSING', 'COMPLETED'])]]);

        [$row, $no] = DB::transaction(function () use ($data) {
            $row = AsmQualityInspection::query()->whereKey($data['inspection_id'])->where('rework_qty', '>', 0)->lockForUpdate()->first();
            $no = $row ? CutBundle::query()->whereKey(AsmQualityIn::query()->whereKey($row->quality_in_id)->value('bundle_id'))->value('bundle_no') : null;
            if (! $row) {
                throw ValidationException::withMessages(['inspection_id' => __('assembly.v.qm_no_rework')]);
            }
            $allowed = ['PENDING' => ['PROCESSING', 'COMPLETED'], 'PROCESSING' => ['COMPLETED']][$row->rework_status] ?? [];
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => __('assembly.v.rework_move', ['no' => $no, 'from' => __('assembly.rework.'.$row->rework_status), 'to' => __('assembly.rework.'.$data['status'])])]);
            }
            $now = now();
            $who = $this->caller()->auditUserId();
            $row->rework_status = $data['status'];
            if ($data['status'] === 'PROCESSING' || $row->rework_started_at === null) {
                $row->rework_started_at = $now;
                $row->rework_started_by = $who;
            }
            if ($data['status'] === 'COMPLETED') {
                $row->rework_completed_at = $now;
                $row->rework_completed_by = $who;
            }
            $row->save();

            return [$row, $no];
        });

        return ApiResponse::ok(['inspection_id' => $row->id, 'bundle_no' => $no, 'rework_status' => $row->rework_status]);
    }

    /**
     * The Quality Dashboard for a range: the key figures, the trend over the
     * last 7 or 30 days, the inspection result, Target vs Actual by line,
     * floor, section or unit, and the report tabs.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.view');
        $q = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'shift_id' => ['nullable', 'string', 'max:26'], 'section_id' => ['nullable', 'string', 'max:26'],
            'floor_id' => ['nullable', 'string', 'max:26'], 'line_id' => ['nullable', 'string', 'max:26'], 'view' => ['nullable', Rule::in(self::VIEWS)], 'trend' => ['nullable', Rule::in(['7', '30', 7, 30])],
        ]);
        $today = FinishingFloor::today();
        $from = isset($q['from']) ? CarbonImmutable::parse($q['from'])->startOfDay() : $today;
        $to = isset($q['to']) ? CarbonImmutable::parse($q['to'])->startOfDay() : $today;
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_order')]);
        }
        $days = (int) $from->diffInDays($to) + 1;
        if ($days > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_max', ['days' => self::MAX_RANGE_DAYS])]);
        }
        $view = $q['view'] ?? 'line';
        $trendDays = (int) ($q['trend'] ?? 7);
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $trendFrom = $to->subDays($trendDays - 1)->toDateString();

        $shift = isset($q['shift_id']) ? DB::table('hr_shifts')->where('company_id', $this->caller()->companyId)->where('id', $q['shift_id'])->first() : null;
        // Lines not filed under a section and a floor take no scans, so they stay out of view (named in the warning).
        $lines = $this->lineIndex()->where('active', true)->filter(fn ($l) => $l->section_id && $l->floor_id)
            ->when($q['section_id'] ?? null, fn ($c, $id) => $c->where('section_id', $id))
            ->when($q['floor_id'] ?? null, fn ($c, $id) => $c->where('floor_id', $id))
            ->when($q['line_id'] ?? null, fn ($c, $id) => $c->where('id', $id));
        if ($shift) {
            $covered = FinishingFloor::shiftLines($shift->id, $f, $t)->all();
            $planned = FinishingFloor::plannedLines()->all();
            $lines = $lines->filter(fn ($l) => in_array($l->id, $covered, true) || ! in_array($l->id, $planned, true));
        }
        $ids = $lines->keys();
        $inShift = fn ($row) => ! $shift || FinishingFloor::inShift($row->created_at, $shift);

        $allIns = AsmQualityIn::query()->whereIn('line_id', $ids)->get()->keyBy('id');
        $allInsps = AsmQualityInspection::query()->whereIn('quality_in_id', $allIns->keys())->orderBy('round')->get();
        $allOuts = AsmQualityOut::query()->whereIn('quality_in_id', $allIns->keys())->get()->keyBy('quality_in_id');
        $byIn = $allInsps->groupBy('quality_in_id');
        $this->finOutTimes = AsmFinishingOut::query()->whereIn('id', $allIns->pluck('finishing_out_id'))->pluck('created_at', 'id')->all();
        $cards = CutBundle::query()->whereIn('id', $allIns->pluck('bundle_id'))->get()->keyBy('id');
        $states = $allIns->map(fn ($i) => $this->state($i, $byIn->get($i->id, collect()), $allOuts[$i->id] ?? null));
        $lineOf = fn ($row) => $allIns[$row->quality_in_id]->line_id;
        $newest = [['created_at', 'desc'], ['id', 'desc']];

        $ins = $allIns->filter(fn ($i) => $i->in_date->toDateString() >= $f && $i->in_date->toDateString() <= $t)->filter($inShift)->sortBy($newest)->values();
        $insps = $allInsps->filter(fn ($r) => $r->inspected_on->toDateString() >= $f && $r->inspected_on->toDateString() <= $t)->filter($inShift)->sortBy($newest)->values();
        $outs = $allOuts->filter(fn ($o) => $o->out_date->toDateString() >= $f && $o->out_date->toDateString() <= $t)->filter($inShift)->sortBy($newest)->values();
        $wip = $allIns->filter(fn ($i) => ! isset($allOuts[$i->id]))->sortBy($newest)->values();
        $pending = $wip->filter(fn ($i) => $states[$i->id]['to_inspect'] > 0)->values();
        $local = fn ($ts) => $ts?->setTimezone('Asia/Dhaka')->toDateString();
        $rework = $allInsps->filter(fn ($r) => $r->rework_qty > 0 && ($r->rework_status !== 'COMPLETED' || ($local($r->rework_completed_at) >= $f && $local($r->rework_completed_at) <= $t)))->sortBy($newest)->values();
        $ready = $this->readyBundles();

        $groupKey = fn ($line) => match ($view) {
            'floor' => $line->floor_id, 'section' => $line->section_id, 'unit' => $line->unit_id, default => $line->id
        };
        $groupLabel = fn ($line) => match ($view) {
            'floor' => $line->floor, 'section' => $line->section, 'unit' => $line->unit, default => $line->label
        };
        $performance = $lines->groupBy(fn ($l) => (string) $groupKey($l))->map(function ($group) use ($ins, $insps, $pending, $states, $lineOf, $days, $groupLabel, $view) {
            $mine = $group->pluck('id')->all();
            $gInsps = $insps->filter(fn ($r) => in_array($lineOf($r), $mine, true));
            $first = $group->first();
            $target = (int) $group->sum(fn ($l) => (int) $l->target_per_day) * $days;
            $inspected = (int) $gInsps->sum('inspected_qty');
            $passed = (int) $gInsps->sum('pass_qty');

            return [
                'label' => $groupLabel($first) ?? __('assembly.fin.unfiled_group'), 'sub' => $view === 'line' ? trim(($first->section ?? '—').' · '.($first->floor ?? '—')) : null,
                'lines' => $group->count(), 'target' => $target ?: null, 'in_qty' => (int) $ins->whereIn('line_id', $mine)->sum('qty'),
                'inspected' => $inspected, 'passed' => $passed, 'reject' => (int) $gInsps->sum('reject_qty'), 'rework' => (int) $gInsps->sum('rework_qty'),
                'pending' => (int) $pending->whereIn('line_id', $mine)->sum(fn ($i) => $states[$i->id]['to_inspect']),
                'pass_pct' => $inspected ? round($passed / $inspected * 100, 1) : null,
            ];
        })->sortBy('label')->values();

        $trend = collect(range(0, $trendDays - 1))->map(function ($n) use ($trendFrom, $allIns, $allInsps, $inShift) {
            $d = CarbonImmutable::parse($trendFrom)->addDays($n)->toDateString();
            $dInsps = $allInsps->filter(fn ($r) => $r->inspected_on->toDateString() === $d)->filter($inShift);

            return ['day' => $d, 'in_qty' => (int) $allIns->filter(fn ($i) => $i->in_date->toDateString() === $d)->filter($inShift)->sum('qty'),
                'inspected' => (int) $dInsps->sum('inspected_qty'), 'passed' => (int) $dInsps->sum('pass_qty'), 'reject' => (int) $dInsps->sum('reject_qty'), 'rework' => (int) $dInsps->sum('rework_qty')];
        });

        $inspected = (int) $insps->sum('inspected_qty');
        $pct = fn ($v) => $inspected ? round($v / $inspected * 100, 1) : null;
        [$passQ, $rejectQ, $reworkQ] = [(int) $insps->sum('pass_qty'), (int) $insps->sum('reject_qty'), (int) $insps->sum('rework_qty')];
        $target = (int) $lines->sum(fn ($l) => (int) $l->target_per_day) * $days;
        $card = fn ($qin) => $cards[$qin->bundle_id];
        $standing = fn ($i) => $this->presentIn($i, $card($i)) + ['to_inspect' => $states[$i->id]['to_inspect'], 'stage' => $states[$i->id]['stage']];

        return ApiResponse::ok([
            'from' => $f, 'to' => $t, 'view' => $view, 'trend_days' => $trendDays, 'shift' => $shift?->name,
            'summary' => [
                'in_qty' => (int) $ins->sum('qty'), 'in_bundles' => $ins->count(), 'inspected' => $inspected, 'target' => $target ?: null,
                'passed' => $passQ, 'reject' => $rejectQ, 'rework' => $reworkQ, 'pass_pct' => $pct($passQ), 'reject_pct' => $pct($rejectQ), 'rework_pct' => $pct($reworkQ),
                'pending_qty' => (int) $pending->sum(fn ($i) => $states[$i->id]['to_inspect']), 'pending_bundles' => $pending->count(),
                'out_bundles' => $outs->count(), 'rework_open' => (int) $allInsps->filter(fn ($r) => in_array($r->rework_status, ['PENDING', 'PROCESSING'], true))->sum('rework_qty'),
                'ready_bundles' => count($ready), 'ready_qty' => (int) collect($ready)->sum('qty'), 'lines' => $lines->count(),
            ],
            'trend' => $trend->all(),
            'performance' => $performance->all(),
            'ins' => $ins->map(fn ($i) => $this->presentIn($i, $card($i)))->all(),
            'inspections' => $insps->map(fn ($r) => $this->presentInspection($r, $allIns[$r->quality_in_id], $card($allIns[$r->quality_in_id])))->all(),
            'outs' => $outs->map(fn ($o) => $this->presentOut($o, $allIns[$o->quality_in_id], $card($allIns[$o->quality_in_id])))->all(),
            'rework' => $rework->map(fn ($r) => $this->presentInspection($r, $allIns[$r->quality_in_id], $card($allIns[$r->quality_in_id])))->all(),
            'pending' => $pending->map($standing)->all(),
            'wip' => $wip->map($standing)->all(),
            'ready' => $ready,
            'unfiled' => $this->lineIndex()->where('active', true)->reject(fn ($l) => $l->section_id && $l->floor_id)->pluck('label')->values()->all(),
        ]);
    }

    /** A bundle's way from cutting through sewing, finishing and quality, step by step. */
    public function trace(Request $request): JsonResponse
    {
        $this->allow('assembly.quality.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $card = SewingFloor::card($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $q['code']])]);
        $sewIn = AsmBundleIn::query()->where('bundle_id', $card->id)->first();
        $sewOut = $sewIn ? AsmBundleOut::query()->where('bundle_in_id', $sewIn->id)->first() : null;
        $fin = AsmFinishingIn::query()->where('bundle_id', $card->id)->first();
        [$finOut, $qin, $insps, $qout] = $this->stages($card);
        $at = fn ($ts) => $ts?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i');
        $sewLine = $sewIn ? SewingFloor::lineLabel($this->sewingLines()->get($sewIn->line_id)) : null;
        $finLine = $fin ? $this->finishingLines()->get($fin->line_id)?->label : null;
        $qLine = $qin ? $this->lineIndex()->get($qin->line_id)?->label : null;
        $reworkSteps = function ($row, string $start, string $done, array $params) use ($at): array {
            return [
                $row?->rework_started_at && $row->rework_status !== 'PENDING' && $row->rework_started_at != $row->rework_completed_at ? ['stage' => $start, 'at' => $at($row->rework_started_at), 'by' => $this->userName($row->rework_started_by), 'params' => $params] : null,
                $row?->rework_completed_at ? ['stage' => $done, 'at' => $at($row->rework_completed_at), 'by' => $this->userName($row->rework_completed_by), 'params' => $params] : null,
            ];
        };

        $steps = [
            ['stage' => 'CUT', 'at' => $card->cut_date->toDateString(), 'by' => $this->userName($card->created_by), 'params' => ['cutting' => $card->cutting_no, 'qty' => $card->qty, 'parts' => $card->parts_name]],
            $card->printed_at ? ['stage' => 'PRINT', 'at' => $at($card->printed_at), 'by' => null, 'params' => ['n' => (int) $card->print_count]] : null,
            $sewIn ? ['stage' => 'SEW_IN', 'at' => $at($sewIn->created_at), 'by' => $this->userName($sewIn->created_by), 'params' => ['line' => $sewLine, 'qty' => $sewIn->qty, 'short' => $sewIn->short_qty]] : null,
            $sewOut ? ['stage' => 'SEW_OUT', 'at' => $at($sewOut->created_at), 'by' => $this->userName($sewOut->created_by), 'params' => ['line' => $sewLine, 'qty' => $sewOut->output_qty, 'balance' => $sewOut->balance_qty]] : null,
            $fin ? ['stage' => 'FIN_IN', 'at' => $at($fin->created_at), 'by' => $this->userName($fin->created_by), 'params' => ['line' => $finLine, 'qty' => $fin->qty]] : null,
            $finOut ? ['stage' => 'FIN_OUT', 'at' => $at($finOut->created_at), 'by' => $this->userName($finOut->created_by), 'params' => ['line' => $finLine, 'good' => $finOut->good_qty, 'reject' => $finOut->reject_qty, 'rework' => $finOut->rework_qty]] : null,
            ...($finOut && $finOut->rework_qty > 0 ? $reworkSteps($finOut, 'REWORK_START', 'REWORK_DONE', ['qty' => $finOut->rework_qty]) : []),
            $qin ? ['stage' => 'QM_IN', 'at' => $at($qin->created_at), 'by' => $this->userName($qin->created_by), 'params' => ['line' => $qLine, 'qty' => $qin->qty]] : null,
        ];
        foreach ($insps as $r) {
            $steps[] = ['stage' => 'QM_INSPECT', 'at' => $at($r->created_at), 'by' => $this->userName($r->created_by), 'params' => [
                'round' => $r->round, 'qty' => $r->inspected_qty, 'pass' => $r->pass_qty, 'reject' => $r->reject_qty, 'rework' => $r->rework_qty, 'defect' => $r->defect_type ? __('assembly.defect.'.$r->defect_type) : '—',
            ]];
            if ($r->rework_qty > 0) {
                array_push($steps, ...$reworkSteps($r, 'QM_REWORK_START', 'QM_REWORK_DONE', ['qty' => $r->rework_qty, 'round' => $r->round]));
            }
        }
        $steps[] = $qout ? ['stage' => 'QM_OUT', 'at' => $at($qout->created_at), 'by' => $this->userName($qout->created_by), 'params' => [
            'pass' => $qout->pass_qty, 'reject' => $qout->reject_qty, 'status' => __('assembly.qm.final.'.$qout->final_status),
        ]] : null;
        $state = $qin ? $this->state($qin, $insps, $qout) : null;

        return ApiResponse::ok(['bundle' => $this->snapshot($card, $finOut, $qin, $state), 'steps' => array_values(array_filter($steps))]);
    }

    /**
     * Where a received bundle stands: its stage, what is left to judge and in
     * which round, the open rework, what its inspections came to and the final
     * status Quality OUT would give it.
     */
    private function state(AsmQualityIn $qin, Collection $insps, ?AsmQualityOut $qout): array
    {
        $insps = $insps->sortBy('round')->values();
        $last = $insps->last();
        $open = $last && $last->rework_qty > 0 && in_array($last->rework_status, ['PENDING', 'PROCESSING'], true) ? $last : null;
        $back = $last && $last->rework_qty > 0 && $last->rework_status === 'COMPLETED';
        [$stage, $toInspect] = match (true) {
            $qout !== null => ['QUALITY_OUT', 0],
            $last === null => ['IN_QUALITY', $qin->qty],
            $open !== null => [$open->rework_status === 'PENDING' ? 'REWORK_PENDING' : 'REWORK_PROCESSING', 0],
            $back => ['READY_REINSPECT', $last->rework_qty],
            default => ['INSPECTED', 0],
        };
        $pass = (int) $insps->sum('pass_qty');
        $final = match (true) {
            $qout !== null => $qout->final_status,
            $last === null => 'NOT_INSPECTED',
            $open !== null || $back => 'REWORK_PENDING',
            $pass > 0 => 'PASS',
            default => 'REJECT',
        };

        return [
            'stage' => $stage, 'to_inspect' => $toInspect, 'next_round' => ($last?->round ?? 0) + 1, 'open_rework' => $open, 'rounds' => $insps->count(),
            'inspected' => (int) $insps->sum('inspected_qty'), 'pass' => $pass, 'reject' => (int) $insps->sum('reject_qty'), 'rework' => (int) $insps->sum('rework_qty'),
            'open_rework_qty' => $open?->rework_qty ?? ($back ? $last->rework_qty : 0), 'final_status' => $final,
        ];
    }

    /** Out of Finishing with good pieces, not yet received at Quality IN — across the whole floor. */
    private function readyBundles(): array
    {
        $outs = AsmFinishingOut::query()->where('good_qty', '>', 0)->whereNotIn('id', AsmQualityIn::query()->select('finishing_out_id'))->orderBy('created_at')->orderBy('id')->get();
        $ins = AsmFinishingIn::query()->whereIn('id', $outs->pluck('finishing_in_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');

        return $outs->map(function ($o) use ($ins, $cards) {
            $in = $ins[$o->finishing_in_id];

            return $this->card($cards[$in->bundle_id]) + ['qty' => $o->good_qty, 'finishing_line' => $this->finishingLines()->get($in->line_id)?->label, 'finishing_out_at' => $o->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i')];
        })->values()->all();
    }

    /** @return array{0: ?AsmFinishingOut, 1: ?AsmQualityIn, 2: Collection, 3: ?AsmQualityOut} */
    private function stages(CutBundle $card): array
    {
        $fin = AsmFinishingIn::query()->where('bundle_id', $card->id)->first();
        $finOut = $fin ? AsmFinishingOut::query()->where('finishing_in_id', $fin->id)->first() : null;
        $qin = AsmQualityIn::query()->where('bundle_id', $card->id)->first();
        $insps = $qin ? AsmQualityInspection::query()->where('quality_in_id', $qin->id)->orderBy('round')->get() : collect();
        $qout = $qin ? AsmQualityOut::query()->where('quality_in_id', $qin->id)->first() : null;

        return [$finOut, $qin, $insps, $qout];
    }

    /** Why a bundle may not be received into Quality, or null. */
    private function inBlocker(CutBundle $card, ?AsmFinishingOut $finOut, ?AsmQualityIn $qin): ?string
    {
        if ($card->status === 'CANCELLED') {
            return __('assembly.v.cancelled', ['no' => $card->bundle_no]);
        }
        if ($qin) {
            return __('assembly.v.qm_already_in', ['no' => $card->bundle_no, 'date' => $qin->in_date->format('d-m-Y'), 'line' => $this->lineIndex()->get($qin->line_id)?->label]);
        }
        if (! $finOut) {
            return __('assembly.v.qm_not_finished', ['no' => $card->bundle_no]);
        }
        if ($finOut->good_qty < 1) {
            return __('assembly.v.qm_no_good', ['no' => $card->bundle_no]);
        }

        return null;
    }

    /** Why a bundle may not be inspected on this quality line now, or null. */
    private function inspectBlocker(CutBundle $card, ?AsmQualityIn $qin, ?array $state, object $line): ?string
    {
        if ($why = $this->placeBlocker($card, $qin, $state, $line)) {
            return $why;
        }

        return match ($state['stage']) {
            'REWORK_PENDING', 'REWORK_PROCESSING' => __('assembly.v.qm_rework_open', ['no' => $card->bundle_no, 'qty' => $state['open_rework_qty'], 'status' => __('assembly.rework.'.$state['open_rework']->rework_status)]),
            'INSPECTED' => __('assembly.v.qm_inspected', ['no' => $card->bundle_no]),
            default => null,
        };
    }

    /** Why a bundle may not go Quality OUT from this line now, or null. */
    private function outBlocker(CutBundle $card, ?AsmQualityIn $qin, ?array $state, object $line): ?string
    {
        if ($why = $this->placeBlocker($card, $qin, $state, $line)) {
            return $why;
        }

        return match ($state['stage']) {
            'IN_QUALITY' => __('assembly.v.qm_not_inspected', ['no' => $card->bundle_no]),
            'REWORK_PENDING', 'REWORK_PROCESSING' => __('assembly.v.qm_rework_open', ['no' => $card->bundle_no, 'qty' => $state['open_rework_qty'], 'status' => __('assembly.rework.'.$state['open_rework']->rework_status)]),
            'READY_REINSPECT' => __('assembly.v.qm_reinspect', ['no' => $card->bundle_no, 'qty' => $state['to_inspect']]),
            default => null,
        };
    }

    /** Received, not yet out, and on this line. */
    private function placeBlocker(CutBundle $card, ?AsmQualityIn $qin, ?array $state, object $line): ?string
    {
        if (! $qin) {
            return __('assembly.v.qm_not_in', ['no' => $card->bundle_no]);
        }
        if ($state['stage'] === 'QUALITY_OUT') {
            return __('assembly.v.qm_already_out', ['no' => $card->bundle_no]);
        }
        if ($qin->line_id !== $line->id) {
            return __('assembly.v.qm_other_line', ['no' => $card->bundle_no, 'line' => $this->lineIndex()->get($qin->line_id)?->label]);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function location(Request $request, array $more = []): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40'], 'section_id' => ['required', 'string', 'max:26'], 'floor_id' => ['required', 'string', 'max:26'], 'line_id' => ['required', 'string', 'max:26'], ...$more,
        ], ['section_id.required' => __('assembly.v.section'), 'floor_id.required' => __('assembly.v.floor'), 'line_id.required' => __('assembly.v.line')]);
    }

    /** The picked line, active and filed under the picked section and floor. */
    private function locatedLine(array $data): object
    {
        $line = $this->lineIndex()->get($data['line_id']);
        if (! $line || ! $line->active || $line->section_id !== $data['section_id'] || $line->floor_id !== $data['floor_id']) {
            throw ValidationException::withMessages(['line_id' => __('assembly.v.qm_line')]);
        }

        return $line;
    }

    private function pickLine(string $id): object
    {
        $line = $this->lineIndex()->get($id);
        if (! $line || ! $line->active) {
            throw ValidationException::withMessages(['line_id' => __('assembly.v.qm_line')]);
        }

        return $line;
    }

    /** A quality line today: received, judged, released, and what stands on it to judge. */
    private function lineToday(object $line): array
    {
        $today = FinishingFloor::today()->toDateString();
        $ins = AsmQualityIn::query()->where('line_id', $line->id)->get();
        $insps = AsmQualityInspection::query()->whereIn('quality_in_id', $ins->pluck('id'))->orderBy('round')->get();
        $outs = AsmQualityOut::query()->whereIn('quality_in_id', $ins->pluck('id'))->get()->keyBy('quality_in_id');
        $byIn = $insps->groupBy('quality_in_id');
        $todayIns = $ins->filter(fn ($i) => $i->in_date->toDateString() === $today);
        $todayInsps = $insps->filter(fn ($r) => $r->inspected_on->toDateString() === $today);
        $wip = $ins->filter(fn ($i) => ! isset($outs[$i->id]));

        return [
            'line' => $line->label, 'code' => $line->code, 'section' => $line->section, 'floor' => $line->floor,
            'today_in' => $todayIns->count(), 'today_in_qty' => (int) $todayIns->sum('qty'),
            'inspected' => (int) $todayInsps->sum('inspected_qty'), 'passed' => (int) $todayInsps->sum('pass_qty'), 'reject' => (int) $todayInsps->sum('reject_qty'), 'rework' => (int) $todayInsps->sum('rework_qty'),
            'today_out' => $outs->filter(fn ($o) => $o->out_date->toDateString() === $today)->count(),
            'wip_bundles' => $wip->count(), 'pending_qty' => (int) $wip->sum(fn ($i) => $this->state($i, $byIn->get($i->id, collect()), null)['to_inspect']),
            'daily_target' => $line->target_per_day ? (int) $line->target_per_day : null,
        ];
    }

    /** The card as the desks show it: its facts, the step before, where it is now and what its inspections came to. */
    private function snapshot(CutBundle $card, ?AsmFinishingOut $finOut, ?AsmQualityIn $qin, ?array $state): array
    {
        $line = $qin ? $this->lineIndex()->get($qin->line_id) : null;
        $stage = $state['stage'] ?? ($finOut && $finOut->good_qty > 0 ? 'READY' : 'NOT_READY');

        return $this->card($card) + [
            'qty' => $qin?->qty ?? $finOut?->good_qty ?? $card->qty, 'stage' => $stage,
            'previous' => $finOut ? ['stage' => 'FIN_OUT', 'at' => $finOut->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i')] : null,
            'in_at' => $qin?->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'section' => $line?->section, 'floor' => $line?->floor, 'line' => $line?->label,
            'inspected' => $state['inspected'] ?? 0, 'pass' => $state['pass'] ?? 0, 'reject' => $state['reject'] ?? 0, 'rework' => $state['rework'] ?? 0,
            'open_rework' => $state['open_rework_qty'] ?? 0, 'rounds' => $state['rounds'] ?? 0, 'final_status' => $state['final_status'] ?? 'NOT_INSPECTED',
        ];
    }

    private function card(CutBundle $b): array
    {
        return ['bundle_no' => $b->bundle_no, 'barcode' => $b->barcode(), 'order_no' => $this->orderNo($b->order_id), 'po_no' => $b->po_no, 'buyer' => $this->buyer($b->buyer_id),
            'style' => $b->style, 'colour' => $b->colour, 'size' => $b->size, 'cutting_no' => $b->cutting_no, 'bundle_qty' => $b->qty];
    }

    private function place(AsmQualityIn $in): array
    {
        $line = $this->lineIndex()->get($in->line_id);

        return ['section' => $line?->section, 'floor' => $line?->floor, 'line' => $line?->label];
    }

    private function presentIn(AsmQualityIn $in, CutBundle $card): array
    {
        $finOutAt = $this->finOutTimes[$in->finishing_out_id] ??= AsmFinishingOut::query()->whereKey($in->finishing_out_id)->value('created_at');

        return $this->card($card) + $this->place($in) + ['qty' => $in->qty, 'previous' => $finOutAt ? CarbonImmutable::parse($finOutAt)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i') : null,
            'in_at' => $in->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'time' => $in->created_at?->setTimezone('Asia/Dhaka')->format('H:i'), 'by' => $this->userName($in->created_by)];
    }

    private function presentInspection(AsmQualityInspection $r, AsmQualityIn $in, CutBundle $card): array
    {
        return $this->card($card) + $this->place($in) + ['id' => $r->id, 'qty' => $in->qty, 'round' => $r->round, 'inspected' => $r->inspected_qty,
            'pass' => $r->pass_qty, 'reject' => $r->reject_qty, 'rework' => $r->rework_qty, 'defect_type' => $r->defect_type, 'remark' => $r->remark,
            'rework_status' => $r->rework_status, 'rework_completed_at' => $r->rework_completed_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'),
            'at' => $r->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'time' => $r->created_at?->setTimezone('Asia/Dhaka')->format('H:i'), 'by' => $this->userName($r->created_by)];
    }

    private function presentOut(AsmQualityOut $o, AsmQualityIn $in, CutBundle $card): array
    {
        return $this->card($card) + $this->place($in) + ['qty' => $in->qty, 'pass' => $o->pass_qty, 'reject' => $o->reject_qty, 'rework' => $o->rework_qty,
            'final_status' => $o->final_status, 'remarks' => $o->remarks,
            'at' => $o->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'time' => $o->created_at?->setTimezone('Asia/Dhaka')->format('H:i'), 'by' => $this->userName($o->created_by)];
    }

    private function lineIndex(): Collection
    {
        return $this->lineIndex ??= FinishingFloor::lines($this->caller()->companyId, 'QUALITY');
    }

    private function finishingLines(): Collection
    {
        return $this->finishingLines ??= FinishingFloor::lines($this->caller()->companyId);
    }

    private function sewingLines(): Collection
    {
        return $this->sewingLines ??= SewingFloor::lines($this->caller()->companyId);
    }

    private function orderNo(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        return $this->orders[$id] ??= DB::table('merch_orders')->where('id', $id)->value('order_no');
    }

    private function userName(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        return $this->users[$id] ??= DB::table('users')->where('id', $id)->value('name');
    }

    private function buyer(?string $id): ?string
    {
        $this->buyerNames ??= DB::table('buyers')->where('company_id', $this->caller()->companyId)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);

        return $id ? ($this->buyerNames[$id] ?? null) : null;
    }
}
