<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmBundleIn;
use App\Modules\Assembly\Models\AsmBundleOut;
use App\Modules\Assembly\Models\AsmFinishingIn;
use App\Modules\Assembly\Models\AsmFinishingOut;
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
 * Assembly Floor → Finishing (AnnotechERP 20/02), as Brickland keeps it:
 * Bundle IN — a bundle out of sewing scanned onto a finishing line (Section
 * > Floor > Line), its order, style and quantity fetched with the Sewing OUT
 * check, received once with what sewing sent out; Bundle OUT — scanned off
 * the same line with its Good, Reject and Rework, which must add up to the
 * bundle; and the Finishing Dashboard — the range's figures, Target vs
 * Actual by line, floor, section or unit, the WIP / IN / OUT / Reject /
 * Rework / Pending Bundle / Daily Production tabs, the rework a supervisor
 * moves on to completion, and a bundle's trace from cutting to finishing.
 */
class FinishingApiController extends ApiController
{
    private const VIEWS = ['line', 'floor', 'section', 'unit'];

    private const MAX_RANGE_DAYS = 92;

    private ?Collection $lineIndex = null;

    private ?Collection $sewingLines = null;

    private array $orders = [];

    private array $users = [];

    private ?Collection $buyerNames = null;

    /** What the desks and the dashboard pick from: the finishing sections, floors and lines, the shifts and the units. */
    public function options(): JsonResponse
    {
        $this->allow('assembly.finishing.view');
        $active = $this->lineIndex()->where('active', true);
        $filed = $active->filter(fn ($l) => $l->section_id && $l->floor_id);

        return ApiResponse::ok([
            'today' => FinishingFloor::today()->toDateString(),
            'sections' => $filed->groupBy('section_id')->map(fn ($g, $id) => ['value' => $id, 'label' => $g->first()->section])->sortBy('label')->values()->all(),
            'floors' => $filed->groupBy(fn ($l) => $l->section_id.'|'.$l->floor_id)->map(fn ($g) => ['value' => $g->first()->floor_id, 'label' => $g->first()->floor, 'section_id' => $g->first()->section_id])->sortBy('label')->values()->all(),
            'lines' => $filed->map(fn ($l) => ['value' => $l->id, 'label' => $l->label, 'section_id' => $l->section_id, 'floor_id' => $l->floor_id])->values()->all(),
            'unfiled' => $active->reject(fn ($l) => $l->section_id && $l->floor_id)->pluck('label')->values()->all(),
            'shifts' => DB::table('hr_shifts')->where('company_id', $this->caller()->companyId)->where('active', true)->orderBy('start_time')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.($s->code ? ' ('.$s->code.')' : '')])->all(),
        ]);
    }

    /** A line's target and today's progress — what the desks' Planning & Target panel shows once a line is picked. */
    public function line(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.view');
        $q = $request->validate(['line_id' => ['required', 'string', 'max:26']]);

        return ApiResponse::ok($this->lineProgress($this->pickLine($q['line_id'])));
    }

    /**
     * What a scanned bundle resolves to at a desk — its card, where it stands,
     * the process before, its order's plan and this line's progress — and,
     * when it may not be confirmed here, why. Nothing is saved.
     */
    public function resolve(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40'], 'line_id' => ['required', 'string', 'max:26'], 'kind' => ['required', Rule::in(['in', 'out'])]]);
        $line = $this->pickLine($q['line_id']);
        $card = SewingFloor::card($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $q['code']])]);
        [$sewOut, $fin, $finOut] = $this->stages($card);

        return ApiResponse::ok([
            'bundle' => $this->snapshot($card, $sewOut, $fin, $finOut),
            'plan' => $this->orderPlan($card),
            'line' => $this->lineProgress($line),
            'blocker' => $q['kind'] === 'in' ? $this->inBlocker($card, $sewOut, $fin) : $this->outBlocker($card, $fin, $finOut, $line),
        ]);
    }

    /** Today's scans at a desk, every finishing line, newest first. */
    public function recent(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.view');
        $q = $request->validate(['kind' => ['required', Rule::in(['in', 'out'])]]);
        $today = FinishingFloor::today()->toDateString();
        if ($q['kind'] === 'in') {
            $ins = AsmFinishingIn::query()->whereDate('in_date', $today)->orderByDesc('created_at')->orderByDesc('id')->get();
            $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');

            return ApiResponse::ok(['date' => $today, 'rows' => $ins->map(fn ($i) => $this->presentIn($i, $cards[$i->bundle_id]))->values()->all()]);
        }
        $outs = AsmFinishingOut::query()->whereDate('out_date', $today)->orderByDesc('created_at')->orderByDesc('id')->get();
        $ins = AsmFinishingIn::query()->whereIn('id', $outs->pluck('finishing_in_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');

        return ApiResponse::ok(['date' => $today, 'rows' => $outs->map(fn ($o) => $this->presentOut($o, $ins[$o->finishing_in_id], $cards[$ins[$o->finishing_in_id]->bundle_id]))->values()->all()]);
    }

    /** Receive a bundle out of sewing onto a finishing line, once, with what sewing sent out. */
    public function receive(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.manage');
        $data = $this->location($request);
        $line = $this->locatedLine($data);

        $in = DB::transaction(function () use ($data, $line) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $card = CutBundle::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            [$sewOut, $fin] = $this->stages($card);
            if ($why = $this->inBlocker($card, $sewOut, $fin)) {
                throw ValidationException::withMessages(['code' => $why]);
            }

            return AsmFinishingIn::query()->create([
                'bundle_id' => $card->id, 'sewing_out_id' => $sewOut->id, 'in_date' => FinishingFloor::today()->toDateString(),
                'section_id' => $line->section_id, 'floor_id' => $line->floor_id, 'line_id' => $line->id, 'qty' => $sewOut->output_qty,
                'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        return ApiResponse::created($this->presentIn($in, CutBundle::query()->findOrFail($in->bundle_id)));
    }

    /** Scan a bundle off its finishing line: Good + Reject + Rework, adding up to what went on, once. */
    public function sendOut(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.manage');
        $data = $this->location($request, [
            'good_qty' => ['required', 'integer', 'min:0'], 'reject_qty' => ['required', 'integer', 'min:0'], 'rework_qty' => ['required', 'integer', 'min:0'],
        ]);
        $line = $this->locatedLine($data);

        $out = DB::transaction(function () use ($data, $line) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $fin = AsmFinishingIn::query()->where('bundle_id', $card->id)->lockForUpdate()->first();
            $finOut = $fin ? AsmFinishingOut::query()->where('finishing_in_id', $fin->id)->first() : null;
            if ($why = $this->outBlocker($card, $fin, $finOut, $line)) {
                throw ValidationException::withMessages(['code' => $why]);
            }
            $sum = (int) $data['good_qty'] + (int) $data['reject_qty'] + (int) $data['rework_qty'];
            if ($sum !== $fin->qty) {
                throw ValidationException::withMessages(['good_qty' => __('assembly.v.fin_sum', ['sum' => $sum, 'qty' => $fin->qty])]);
            }

            return AsmFinishingOut::query()->create([
                'finishing_in_id' => $fin->id, 'out_date' => FinishingFloor::today()->toDateString(),
                'good_qty' => (int) $data['good_qty'], 'reject_qty' => (int) $data['reject_qty'], 'rework_qty' => (int) $data['rework_qty'],
                'rework_status' => (int) $data['rework_qty'] > 0 ? 'PENDING' : null, 'created_by' => $this->caller()->auditUserId(),
            ]);
        });
        $fin = AsmFinishingIn::query()->findOrFail($out->finishing_in_id);

        return ApiResponse::created($this->presentOut($out, $fin, CutBundle::query()->findOrFail($fin->bundle_id)));
    }

    /** Move a bundle's rework on — pending → processing → completed (or straight to completed); a supervisor's call. */
    public function reworkStatus(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.supervise');
        $data = $request->validate(['bundle_no' => ['required', 'string', 'max:24'], 'status' => ['required', Rule::in(['PROCESSING', 'COMPLETED'])]]);

        $out = DB::transaction(function () use ($data) {
            $out = AsmFinishingOut::query()->join('asm_finishing_ins as i', 'i.id', '=', 'asm_finishing_outs.finishing_in_id')->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')
                ->where('b.bundle_no', $data['bundle_no'])->where('asm_finishing_outs.rework_qty', '>', 0)->lockForUpdate()->first(['asm_finishing_outs.*'])
                ?? throw ValidationException::withMessages(['bundle_no' => __('assembly.v.no_rework', ['no' => $data['bundle_no']])]);
            $allowed = ['PENDING' => ['PROCESSING', 'COMPLETED'], 'PROCESSING' => ['COMPLETED']][$out->rework_status] ?? [];
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => __('assembly.v.rework_move', ['no' => $data['bundle_no'], 'from' => __('assembly.rework.'.$out->rework_status), 'to' => __('assembly.rework.'.$data['status'])])]);
            }
            $now = now();
            $who = $this->caller()->auditUserId();
            $out->rework_status = $data['status'];
            if ($data['status'] === 'PROCESSING' || $out->rework_started_at === null) {
                $out->rework_started_at = $now;
                $out->rework_started_by = $who;
            }
            if ($data['status'] === 'COMPLETED') {
                $out->rework_completed_at = $now;
                $out->rework_completed_by = $who;
            }
            $out->save();

            return $out;
        });

        return ApiResponse::ok(['bundle_no' => $data['bundle_no'], 'rework_status' => $out->rework_status]);
    }

    /**
     * The Finishing Dashboard for a range: the key figures, Target vs Actual
     * by line, floor, section or unit, and the WIP, Bundle IN, Bundle OUT,
     * Reject, Rework, Pending Bundle and Daily Production tabs.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.view');
        $q = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'shift_id' => ['nullable', 'string', 'max:26'], 'section_id' => ['nullable', 'string', 'max:26'],
            'floor_id' => ['nullable', 'string', 'max:26'], 'line_id' => ['nullable', 'string', 'max:26'], 'view' => ['nullable', Rule::in(self::VIEWS)],
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
        [$f, $t] = [$from->toDateString(), $to->toDateString()];

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

        $allIns = AsmFinishingIn::query()->whereIn('line_id', $ids)->whereDate('in_date', '<=', $t)->get();
        $allOuts = AsmFinishingOut::query()->whereIn('finishing_in_id', $allIns->pluck('id'))->get()->keyBy('finishing_in_id');
        $cards = CutBundle::query()->whereIn('id', $allIns->pluck('bundle_id'))->get()->keyBy('id');

        $ins = $allIns->filter(fn ($i) => $i->in_date->toDateString() >= $f)->filter($inShift)->sortBy([['created_at', 'desc'], ['id', 'desc']])->values();
        $outs = $allOuts->filter(fn ($o) => $o->out_date->toDateString() >= $f && $o->out_date->toDateString() <= $t)->filter($inShift)->sortBy([['created_at', 'desc'], ['id', 'desc']])->values();
        $wip = $allIns->filter(fn ($i) => ! isset($allOuts[$i->id]) || $allOuts[$i->id]->out_date->toDateString() > $t)->sortBy([['created_at', 'desc'], ['id', 'desc']])->values();
        $rework = $allOuts->filter(fn ($o) => $o->rework_qty > 0 && ($o->rework_status !== 'COMPLETED' || ($o->rework_completed_at && $o->rework_completed_at->setTimezone('Asia/Dhaka')->toDateString() >= $f && $o->rework_completed_at->setTimezone('Asia/Dhaka')->toDateString() <= $t)))
            ->sortBy([['created_at', 'desc'], ['id', 'desc']])->values();
        $insById = $allIns->keyBy('id');
        $pending = $this->pendingBundles();

        $outLine = fn ($o) => $insById[$o->finishing_in_id]->line_id;
        $groupKey = fn ($line) => match ($view) {
            'floor' => $line->floor_id, 'section' => $line->section_id, 'unit' => $line->unit_id, default => $line->id
        };
        $groupLabel = fn ($line) => match ($view) {
            'floor' => $line->floor, 'section' => $line->section, 'unit' => $line->unit, default => $line->label
        };
        $performance = $lines->groupBy(fn ($l) => (string) $groupKey($l))->map(function ($group) use ($ins, $outs, $wip, $outLine, $days, $groupLabel, $view) {
            $mine = $group->pluck('id')->all();
            $gOuts = $outs->filter(fn ($o) => in_array($outLine($o), $mine, true));
            $gWip = $wip->whereIn('line_id', $mine);
            $first = $group->first();
            $target = (int) $group->sum(fn ($l) => (int) $l->target_per_day) * $days;
            $good = (int) $gOuts->sum('good_qty');

            return [
                'label' => $groupLabel($first) ?? __('assembly.fin.unfiled_group'), 'sub' => $view === 'line' ? trim(($first->section ?? '—').' · '.($first->floor ?? '—')) : null,
                'lines' => $group->count(), 'target' => $target ?: null, 'actual' => $good, 'achievement' => $target ? round($good / $target * 100, 1) : null,
                'bundle_in' => $ins->whereIn('line_id', $mine)->count(), 'bundle_out' => $gOuts->count(), 'wip' => $gWip->count(), 'wip_qty' => (int) $gWip->sum('qty'),
                'reject' => (int) $gOuts->sum('reject_qty'), 'rework' => (int) $gOuts->sum('rework_qty'),
            ];
        })->sortBy('label')->values();

        $target = (int) $lines->sum(fn ($l) => (int) $l->target_per_day) * $days;
        $good = (int) $outs->sum('good_qty');
        $daily = collect(range(0, $days - 1))->map(function ($n) use ($from, $ins, $outs) {
            $d = $from->addDays($n)->toDateString();
            $dOuts = $outs->filter(fn ($o) => $o->out_date->toDateString() === $d);

            return ['day' => $d, 'bundle_in' => $ins->filter(fn ($i) => $i->in_date->toDateString() === $d)->count(), 'bundle_out' => $dOuts->count(),
                'good' => (int) $dOuts->sum('good_qty'), 'reject' => (int) $dOuts->sum('reject_qty'), 'rework' => (int) $dOuts->sum('rework_qty')];
        });
        $outRow = fn ($o) => $this->presentOut($o, $insById[$o->finishing_in_id], $cards[$insById[$o->finishing_in_id]->bundle_id]);

        return ApiResponse::ok([
            'from' => $f, 'to' => $t, 'view' => $view, 'shift' => $shift?->name,
            'summary' => [
                'bundle_in' => $ins->count(), 'bundle_in_qty' => (int) $ins->sum('qty'), 'bundle_out' => $outs->count(), 'wip_bundles' => $wip->count(), 'wip_qty' => (int) $wip->sum('qty'),
                'good' => $good, 'target' => $target ?: null, 'achievement' => $target ? round($good / $target * 100, 1) : null,
                'reject' => (int) $outs->sum('reject_qty'), 'rework' => (int) $outs->sum('rework_qty'),
                'rework_pending' => (int) $allOuts->filter(fn ($o) => in_array($o->rework_status, ['PENDING', 'PROCESSING'], true))->sum('rework_qty'),
                'pending_bundles' => count($pending), 'pending_qty' => (int) collect($pending)->sum('qty'), 'lines' => $lines->count(),
            ],
            'performance' => $performance->all(),
            'wip' => $wip->map(fn ($i) => $this->presentIn($i, $cards[$i->bundle_id]) + ['status' => 'IN_FINISHING'])->all(),
            'ins' => $ins->map(fn ($i) => $this->presentIn($i, $cards[$i->bundle_id]))->all(),
            'outs' => $outs->map($outRow)->all(),
            'rework' => $rework->map($outRow)->all(),
            'pending' => $pending,
            'daily' => $daily->all(),
            'unfiled' => $this->lineIndex()->where('active', true)->reject(fn ($l) => $l->section_id && $l->floor_id)->pluck('label')->values()->all(),
        ]);
    }

    /** A bundle's way from cutting through sewing and finishing, step by step. */
    public function trace(Request $request): JsonResponse
    {
        $this->allow('assembly.finishing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $card = SewingFloor::card($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $q['code']])]);
        $sewIn = AsmBundleIn::query()->where('bundle_id', $card->id)->first();
        [$sewOut, $fin, $finOut] = $this->stages($card);
        $at = fn ($ts) => $ts?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i');
        $sewLine = $sewIn ? SewingFloor::lineLabel($this->sewingLines()->get($sewIn->line_id)) : null;
        $finLine = $fin ? $this->lineIndex()->get($fin->line_id)?->label : null;

        $steps = array_values(array_filter([
            ['stage' => 'CUT', 'at' => $card->cut_date->toDateString(), 'by' => $this->userName($card->created_by), 'params' => ['cutting' => $card->cutting_no, 'qty' => $card->qty, 'parts' => $card->parts_name]],
            $card->printed_at ? ['stage' => 'PRINT', 'at' => $at($card->printed_at), 'by' => null, 'params' => ['n' => (int) $card->print_count]] : null,
            $sewIn ? ['stage' => 'SEW_IN', 'at' => $at($sewIn->created_at), 'by' => $this->userName($sewIn->created_by), 'params' => ['line' => $sewLine, 'qty' => $sewIn->qty, 'short' => $sewIn->short_qty]] : null,
            $sewOut ? ['stage' => 'SEW_OUT', 'at' => $at($sewOut->created_at), 'by' => $this->userName($sewOut->created_by), 'params' => ['line' => $sewLine, 'qty' => $sewOut->output_qty, 'balance' => $sewOut->balance_qty]] : null,
            $fin ? ['stage' => 'FIN_IN', 'at' => $at($fin->created_at), 'by' => $this->userName($fin->created_by), 'params' => ['line' => $finLine, 'qty' => $fin->qty]] : null,
            $finOut ? ['stage' => 'FIN_OUT', 'at' => $at($finOut->created_at), 'by' => $this->userName($finOut->created_by), 'params' => ['line' => $finLine, 'good' => $finOut->good_qty, 'reject' => $finOut->reject_qty, 'rework' => $finOut->rework_qty]] : null,
            $finOut?->rework_started_at && $finOut->rework_status !== 'PENDING' && $finOut->rework_started_at != $finOut->rework_completed_at ? ['stage' => 'REWORK_START', 'at' => $at($finOut->rework_started_at), 'by' => $this->userName($finOut->rework_started_by), 'params' => ['qty' => $finOut->rework_qty]] : null,
            $finOut?->rework_completed_at ? ['stage' => 'REWORK_DONE', 'at' => $at($finOut->rework_completed_at), 'by' => $this->userName($finOut->rework_completed_by), 'params' => ['qty' => $finOut->rework_qty]] : null,
        ]));

        return ApiResponse::ok(['bundle' => $this->snapshot($card, $sewOut, $fin, $finOut), 'steps' => $steps]);
    }

    /** Out of sewing, not yet received into finishing — across the whole floor. */
    private function pendingBundles(): array
    {
        $outs = AsmBundleOut::query()->whereNotIn('id', AsmFinishingIn::query()->select('sewing_out_id'))->orderBy('created_at')->orderBy('id')->get();
        $ins = AsmBundleIn::query()->whereIn('id', $outs->pluck('bundle_in_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');

        return $outs->map(function ($o) use ($ins, $cards) {
            $in = $ins[$o->bundle_in_id];
            $card = $cards[$in->bundle_id];

            return $this->card($card) + ['qty' => $o->output_qty, 'sewing_line' => SewingFloor::lineLabel($this->sewingLines()->get($in->line_id)), 'sewing_out_at' => $o->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i')];
        })->values()->all();
    }

    /** @return array{0: ?AsmBundleOut, 1: ?AsmFinishingIn, 2: ?AsmFinishingOut} */
    private function stages(CutBundle $card): array
    {
        $sewIn = AsmBundleIn::query()->where('bundle_id', $card->id)->first();
        $sewOut = $sewIn ? AsmBundleOut::query()->where('bundle_in_id', $sewIn->id)->first() : null;
        $fin = AsmFinishingIn::query()->where('bundle_id', $card->id)->first();
        $finOut = $fin ? AsmFinishingOut::query()->where('finishing_in_id', $fin->id)->first() : null;

        return [$sewOut, $fin, $finOut];
    }

    /** Why a bundle may not be received into finishing, or null. */
    private function inBlocker(CutBundle $card, ?AsmBundleOut $sewOut, ?AsmFinishingIn $fin): ?string
    {
        if ($card->status === 'CANCELLED') {
            return __('assembly.v.cancelled', ['no' => $card->bundle_no]);
        }
        if ($fin) {
            return __('assembly.v.fin_already_in', ['no' => $card->bundle_no, 'date' => $fin->in_date->format('d-m-Y'), 'line' => $this->lineIndex()->get($fin->line_id)?->label]);
        }
        if (! $sewOut) {
            return __('assembly.v.not_sewn', ['no' => $card->bundle_no]);
        }
        if ($sewOut->output_qty < 1) {
            return __('assembly.v.no_output', ['no' => $card->bundle_no]);
        }

        return null;
    }

    /** Why a bundle may not be scanned off this finishing line, or null. */
    private function outBlocker(CutBundle $card, ?AsmFinishingIn $fin, ?AsmFinishingOut $finOut, object $line): ?string
    {
        if (! $fin) {
            return __('assembly.v.fin_not_in', ['no' => $card->bundle_no]);
        }
        if ($finOut) {
            return __('assembly.v.fin_already_out', ['no' => $card->bundle_no, 'date' => $finOut->out_date->format('d-m-Y')]);
        }
        if ($fin->line_id !== $line->id) {
            return __('assembly.v.fin_other_line', ['no' => $card->bundle_no, 'line' => $this->lineIndex()->get($fin->line_id)?->label]);
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
            throw ValidationException::withMessages(['line_id' => __('assembly.v.fin_line')]);
        }

        return $line;
    }

    private function pickLine(string $id): object
    {
        $line = $this->lineIndex()->get($id);
        if (! $line || ! $line->active) {
            throw ValidationException::withMessages(['line_id' => __('assembly.v.fin_line')]);
        }

        return $line;
    }

    /** A line's target and today's progress on it. */
    private function lineProgress(object $line): array
    {
        $today = FinishingFloor::today()->toDateString();
        $ins = AsmFinishingIn::query()->where('line_id', $line->id)->get(['id', 'in_date', 'qty']);
        $outs = AsmFinishingOut::query()->whereIn('finishing_in_id', $ins->pluck('id'))->get()->keyBy('finishing_in_id');
        $todayOuts = $outs->filter(fn ($o) => $o->out_date->toDateString() === $today);
        $hours = FinishingFloor::hours($line->id);
        $target = $line->target_per_day ? (int) $line->target_per_day : null;
        $good = (int) $todayOuts->sum('good_qty');
        $wip = $ins->filter(fn ($i) => ! isset($outs[$i->id]));

        return [
            'line' => $line->label, 'section' => $line->section, 'floor' => $line->floor, 'manpower' => $line->manpower !== null ? (int) $line->manpower : null,
            'daily_target' => $target, 'hours' => $hours, 'hourly_target' => $target && $hours ? (int) round($target / $hours) : null,
            'today_in' => $ins->filter(fn ($i) => $i->in_date->toDateString() === $today)->count(), 'today_out' => $todayOuts->count(),
            'good' => $good, 'reject' => (int) $todayOuts->sum('reject_qty'), 'rework' => (int) $todayOuts->sum('rework_qty'),
            'wip' => $wip->count(), 'wip_qty' => (int) $wip->sum('qty'), 'achievement' => $target ? round($good / $target * 100, 1) : null,
        ];
    }

    /** The bundle's order's locked master plan and how far finishing has got with the order. */
    private function orderPlan(CutBundle $card): ?array
    {
        $plan = FinishingFloor::orderPlan($card->order_id);
        if (! $plan) {
            return null;
        }
        $done = (int) AsmFinishingOut::query()->join('asm_finishing_ins as i', 'i.id', '=', 'asm_finishing_outs.finishing_in_id')->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')
            ->where('b.order_id', $card->order_id)->sum('asm_finishing_outs.good_qty');
        $planned = (int) ($plan->allocated_qty ?: $plan->order_qty);

        return [
            'plan_no' => $plan->plan_no, 'smv' => $plan->smv !== null ? (float) $plan->smv : null, 'sam' => $plan->sam !== null ? (float) $plan->sam : null,
            'start' => $plan->sewing_start, 'end' => $plan->sewing_end, 'ship_date' => $plan->ship_date,
            'planned_qty' => $planned, 'completed' => $done, 'remaining' => max(0, $planned - $done),
        ];
    }

    /** The card as the desks show it: its facts, the step before, where it is now. */
    private function snapshot(CutBundle $card, ?AsmBundleOut $sewOut, ?AsmFinishingIn $fin, ?AsmFinishingOut $finOut): array
    {
        $line = $fin ? $this->lineIndex()->get($fin->line_id) : null;
        $status = $finOut ? 'FINISHING_OUT' : ($fin ? 'IN_FINISHING' : ($sewOut ? 'READY' : 'NOT_READY'));

        return $this->card($card) + [
            'qty' => $fin?->qty ?? $sewOut?->output_qty ?? $card->qty, 'status' => $status,
            'previous' => $sewOut ? ['stage' => 'SEW_OUT', 'at' => $sewOut->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i')] : null,
            'in_at' => $fin?->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'section' => $line?->section, 'floor' => $line?->floor, 'line' => $line?->label,
        ];
    }

    private function card(CutBundle $b): array
    {
        return ['bundle_no' => $b->bundle_no, 'barcode' => $b->barcode(), 'order_no' => $this->orderNo($b->order_id), 'po_no' => $b->po_no, 'buyer' => $this->buyer($b->buyer_id),
            'style' => $b->style, 'colour' => $b->colour, 'size' => $b->size, 'cutting_no' => $b->cutting_no, 'bundle_qty' => $b->qty];
    }

    private function presentIn(AsmFinishingIn $in, CutBundle $card): array
    {
        $line = $this->lineIndex()->get($in->line_id);

        return $this->card($card) + ['qty' => $in->qty, 'section' => $line?->section, 'floor' => $line?->floor, 'line' => $line?->label,
            'in_at' => $in->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'time' => $in->created_at?->setTimezone('Asia/Dhaka')->format('H:i'), 'by' => $this->userName($in->created_by)];
    }

    private function presentOut(AsmFinishingOut $out, AsmFinishingIn $in, CutBundle $card): array
    {
        $line = $this->lineIndex()->get($in->line_id);

        return $this->card($card) + ['qty' => $in->qty, 'good' => $out->good_qty, 'reject' => $out->reject_qty, 'rework' => $out->rework_qty, 'rework_status' => $out->rework_status,
            'section' => $line?->section, 'floor' => $line?->floor, 'line' => $line?->label,
            'out_at' => $out->created_at?->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'), 'time' => $out->created_at?->setTimezone('Asia/Dhaka')->format('H:i'), 'by' => $this->userName($out->created_by)];
    }

    private function lineIndex(): Collection
    {
        return $this->lineIndex ??= FinishingFloor::lines($this->caller()->companyId);
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
