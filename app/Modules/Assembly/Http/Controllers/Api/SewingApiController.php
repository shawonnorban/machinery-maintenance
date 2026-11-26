<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmBundleIn;
use App\Modules\Assembly\Models\AsmBundleOut;
use App\Modules\Assembly\Models\AsmFinishingIn;
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
 * Assembly Floor → Sewing (AnnotechERP 20/01), as Brickland keeps it:
 * Bundle IN — a printed cut bundle card scanned onto a line (Section > Floor
 * > Line), the line's plan fetched with it, confirmed once, the pieces short
 * in the bag taken off what the line is handed; Bundle OUT — the card scanned
 * off the line again, the whole bundle or a short output, never more than
 * went on, each scan recorded on its own; and the Sewing Dashboard, every
 * line's day on one board. A receipt or an out is withdrawn at its desk while
 * its day is open and nothing has followed it.
 */
class SewingApiController extends ApiController
{
    private ?Collection $lineIndex = null;

    private ?Collection $buyerNames = null;

    /** What the desks pick from: their days, the floors with their sewing lines. */
    public function options(): JsonResponse
    {
        $this->allow('assembly.sewing.view');
        $lines = $this->lineIndex()->where('active', true);

        return ApiResponse::ok([
            'today' => SewingFloor::today()->toDateString(),
            'days' => SewingFloor::days(),
            'floors' => $lines->groupBy('floor_id')->map(fn ($g, $id) => ['value' => $id, 'label' => $g->first()->floor])->values()->all(),
            'lines' => $lines->map(fn ($l) => ['value' => $l->id, 'label' => SewingFloor::lineLabel($l), 'floor_id' => $l->floor_id])->values()->all(),
        ]);
    }

    /** What a scanned card resolves to on this line — the bundle and the line's plan — before anything is saved. */
    public function resolve(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40'], 'date' => ['required', 'date'], 'line_id' => ['required', 'string', 'max:26']]);
        $card = $this->receivable($q['code']);
        $line = $this->lineIndex()->get($q['line_id']) ?? throw ValidationException::withMessages(['line_id' => __('assembly.v.line')]);
        $plan = SewingFloor::plan($line->id, CarbonImmutable::parse($q['date'])->toDateString());

        return ApiResponse::ok(['bundle' => $this->card($card), 'plan' => $plan, 'line' => SewingFloor::lineLabel($line), 'floor' => $line->floor,
            'style_differs' => $plan['style'] !== null && strcasecmp((string) $plan['style'], (string) $card->style) !== 0]);
    }

    /** One day's receipts at the Bundle IN desk, its chips, and the day line by line. */
    public function ins(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.view');
        $date = $this->day($request);
        $rows = AsmBundleIn::query()->whereDate('receive_date', $date)->orderBy('created_at')->orderBy('id')->get();
        $outs = AsmBundleOut::query()->whereIn('bundle_in_id', $rows->pluck('id'))->get()->keyBy('bundle_in_id');
        $cards = CutBundle::query()->whereIn('id', $rows->pluck('bundle_id'))->get()->keyBy('id');
        $open = SewingFloor::isOpen($date);
        $present = $rows->values()->map(fn ($r, $i) => $this->presentIn($r, $cards[$r->bundle_id], $outs[$r->id] ?? null, $i + 1, $open));

        return ApiResponse::ok([
            'date' => $date, 'open' => $open, 'rows' => $present->all(),
            'bundles' => $rows->count(), 'qty' => (int) $rows->sum('qty'), 'short' => (int) $rows->sum('short_qty'),
            'by_line' => $rows->groupBy('line_id')->map(fn ($g, $lineId) => ['line' => SewingFloor::lineLabel($this->lineIndex()->get($lineId)), 'floor' => $this->lineIndex()->get($lineId)?->floor,
                'bundles' => $g->count(), 'bundle_qty' => (int) $g->sum('bundle_qty'), 'short' => (int) $g->sum('short_qty'), 'qty' => (int) $g->sum('qty')])->sortBy('line')->values()->all(),
        ]);
    }

    /** Confirm a scanned card into sewing, onto its line, once. */
    public function receive(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'], 'receive_date' => ['required', 'date'], 'section' => ['nullable', 'string', 'max:40'],
            'floor_id' => ['required', 'string', 'max:26'], 'line_id' => ['required', 'string', 'max:26'], 'short_qty' => ['nullable', 'integer', 'min:0'],
        ], ['floor_id.required' => __('assembly.v.floor'), 'line_id.required' => __('assembly.v.line')]);
        $date = CarbonImmutable::parse($data['receive_date'])->toDateString();
        $this->assertOpen($date, 'receive_date');
        $line = $this->lineIndex()->get($data['line_id']);
        if (! $line || ! $line->active || $line->floor_id !== $data['floor_id']) {
            throw ValidationException::withMessages(['line_id' => __('assembly.v.line_floor')]);
        }

        $in = DB::transaction(function () use ($data, $date, $line) {
            $card = $this->receivable($data['code'], true);
            if ($card->cut_date->toDateString() > $date) {
                throw ValidationException::withMessages(['receive_date' => __('assembly.v.before_cut', ['date' => $card->cut_date->format('d-m-Y')])]);
            }
            $short = (int) ($data['short_qty'] ?? 0);
            if ($short >= $card->qty) {
                throw ValidationException::withMessages(['short_qty' => __('assembly.v.short', ['qty' => $card->qty])]);
            }
            $plan = SewingFloor::plan($line->id, $date);

            return AsmBundleIn::query()->create([
                'bundle_id' => $card->id, 'receive_date' => $date, 'section' => trim((string) ($data['section'] ?? '')) ?: 'Sewing', 'floor_id' => $line->floor_id, 'line_id' => $line->id,
                'bundle_qty' => $card->qty, 'short_qty' => $short, 'qty' => $card->qty - $short, 'smv' => $plan['smv'], 'manpower' => $plan['manpower'], 'daily_target' => $plan['target'],
                'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        return ApiResponse::created($this->presentIn($in, CutBundle::query()->findOrFail($in->bundle_id), null, null, true));
    }

    /** Withdraw receipts (the ticked ones, or the whole day's) — only while the day is open and the bundle has not gone out. */
    public function withdrawIns(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.manage');
        $nos = $this->picked($request);
        $count = DB::transaction(function () use ($nos) {
            $ins = AsmBundleIn::query()->join('cut_bundles as b', 'b.id', '=', 'asm_bundle_ins.bundle_id')->whereIn('b.bundle_no', $nos)->lockForUpdate()->get(['asm_bundle_ins.*', 'b.bundle_no']);
            $this->assertFound($nos, $ins->pluck('bundle_no'), 'assembly.v.not_received');
            foreach ($ins as $in) {
                if (! SewingFloor::isOpen($in->receive_date->toDateString())) {
                    throw ValidationException::withMessages(['bundles' => __('assembly.v.day_closed', ['no' => $in->bundle_no])]);
                }
                if (AsmBundleOut::query()->where('bundle_in_id', $in->id)->exists()) {
                    throw ValidationException::withMessages(['bundles' => __('assembly.v.gone_out', ['no' => $in->bundle_no])]);
                }
            }
            AsmBundleIn::query()->whereIn('id', $ins->pluck('id'))->delete();

            return $ins->count();
        });

        return ApiResponse::ok(['withdrawn' => $count]);
    }

    /** One day's outs at the Bundle OUT desk, its chips, and the sewing WIP line by line. */
    public function outs(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.view');
        $date = $this->day($request);
        $rows = AsmBundleOut::query()->whereDate('out_date', $date)->orderBy('created_at')->orderBy('id')->get();
        $ins = AsmBundleIn::query()->whereIn('id', $rows->pluck('bundle_in_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');
        $open = SewingFloor::isOpen($date);
        $taken = AsmFinishingIn::query()->whereIn('sewing_out_id', $rows->pluck('id'))->pluck('sewing_out_id')->flip();

        return ApiResponse::ok([
            'date' => $date, 'open' => $open,
            'rows' => $rows->map(fn ($o) => $this->presentOut($o, $ins[$o->bundle_in_id], $cards[$ins[$o->bundle_in_id]->bundle_id], $open && ! isset($taken[$o->id]), isset($taken[$o->id])))->values()->all(),
            'bundles' => $rows->count(), 'output' => (int) $rows->sum('output_qty'), 'balance' => (int) $rows->sum('balance_qty'),
            'full' => $rows->where('mode', 'FULL')->count(), 'short' => $rows->where('mode', 'SHORT')->count(),
            'wip' => $this->wip($date),
        ]);
    }

    /** Scan a bundle off its line: the whole bundle (its input as output) or a short output — never more than went on, once. */
    public function sendOut(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'], 'out_date' => ['required', 'date'], 'mode' => ['required', Rule::in(AsmBundleOut::MODES)],
            'output_qty' => ['nullable', 'required_if:mode,SHORT', 'integer', 'min:1'],
        ], ['output_qty.required_if' => __('assembly.v.output_required')]);
        $date = CarbonImmutable::parse($data['out_date'])->toDateString();
        $this->assertOpen($date, 'out_date');

        $out = DB::transaction(function () use ($data, $date) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $in = AsmBundleIn::query()->where('bundle_id', $card->id)->lockForUpdate()->first() ?? throw ValidationException::withMessages(['code' => __('assembly.v.not_received', ['no' => $card->bundle_no])]);
            if (AsmBundleOut::query()->where('bundle_in_id', $in->id)->exists()) {
                throw ValidationException::withMessages(['code' => __('assembly.v.already_out', ['no' => $card->bundle_no])]);
            }
            if ($in->receive_date->toDateString() > $date) {
                throw ValidationException::withMessages(['out_date' => __('assembly.v.before_in', ['date' => $in->receive_date->format('d-m-Y')])]);
            }
            $output = $data['mode'] === 'FULL' ? $in->qty : (int) $data['output_qty'];
            if ($output > $in->qty) {
                throw ValidationException::withMessages(['output_qty' => __('assembly.v.exceeds', ['qty' => $in->qty])]);
            }

            return AsmBundleOut::query()->create(['bundle_in_id' => $in->id, 'out_date' => $date, 'mode' => $data['mode'], 'output_qty' => $output, 'balance_qty' => $in->qty - $output, 'created_by' => $this->caller()->auditUserId()]);
        });
        $in = AsmBundleIn::query()->findOrFail($out->bundle_in_id);

        return ApiResponse::created($this->presentOut($out, $in, CutBundle::query()->findOrFail($in->bundle_id), true));
    }

    /** Withdraw outs (the ticked ones, or the whole day's) while the day is open and finishing has not received the bundle: it goes back on its line. */
    public function withdrawOuts(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.manage');
        $nos = $this->picked($request);
        $count = DB::transaction(function () use ($nos) {
            $outs = AsmBundleOut::query()->join('asm_bundle_ins as i', 'i.id', '=', 'asm_bundle_outs.bundle_in_id')->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')
                ->whereIn('b.bundle_no', $nos)->lockForUpdate()->get(['asm_bundle_outs.*', 'b.bundle_no']);
            $this->assertFound($nos, $outs->pluck('bundle_no'), 'assembly.v.not_out');
            foreach ($outs as $out) {
                if (! SewingFloor::isOpen($out->out_date->toDateString())) {
                    throw ValidationException::withMessages(['bundles' => __('assembly.v.day_closed', ['no' => $out->bundle_no])]);
                }
                if (AsmFinishingIn::query()->where('sewing_out_id', $out->id)->exists()) {
                    throw ValidationException::withMessages(['bundles' => __('assembly.v.in_finishing', ['no' => $out->bundle_no])]);
                }
            }
            AsmBundleOut::query()->whereIn('id', $outs->pluck('id'))->delete();

            return $outs->count();
        });

        return ApiResponse::ok(['withdrawn' => $count]);
    }

    /**
     * Every sewing line's day on one board: the plan (style, target), the
     * output, achievement and efficiency, bundles in, running and out, the
     * WIP, and what waits for end-line QC. Good, rework and reject come with
     * Quality Management (20/03).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('assembly.sewing.view');
        $q = $request->validate(['date' => ['nullable', 'date'], 'line_id' => ['nullable', 'string', 'max:26']]);
        $date = isset($q['date']) ? CarbonImmutable::parse($q['date'])->toDateString() : SewingFloor::today()->toDateString();
        $lines = $this->lineIndex()->where('active', true)->when($q['line_id'] ?? null, fn ($c, $id) => $c->where('id', $id));
        $ins = AsmBundleIn::query()->whereIn('line_id', $lines->keys())->whereDate('receive_date', '<=', $date)->get();
        $outs = AsmBundleOut::query()->whereIn('bundle_in_id', $ins->pluck('id'))->whereDate('out_date', '<=', $date)->get()->keyBy('bundle_in_id');

        $rows = $lines->values()->map(function ($line) use ($ins, $outs, $date) {
            $mine = $ins->where('line_id', $line->id);
            $todayIn = $mine->filter(fn ($i) => $i->receive_date->toDateString() === $date);
            $mineOut = $mine->map(fn ($i) => $outs[$i->id] ?? null)->filter();
            $todayOut = $mineOut->filter(fn ($o) => $o->out_date->toDateString() === $date);
            $running = $mine->filter(fn ($i) => ! isset($outs[$i->id]));
            $plan = SewingFloor::plan($line->id, $date);
            $output = (int) $todayOut->sum('output_qty');
            $minutes = $plan['manpower'] && $plan['hours'] ? $plan['manpower'] * $plan['hours'] * 60 : 0;

            return [
                'line' => SewingFloor::lineLabel($line), 'floor' => $line->floor, 'style' => $plan['style'], 'target' => $plan['target'] ?? 0, 'output' => $output,
                'achievement' => $plan['target'] ? round($output / $plan['target'] * 100, 2) : null,
                'efficiency' => $minutes && $plan['smv'] ? round($output * $plan['smv'] / $minutes * 100, 2) : null,
                'bundles_in' => $todayIn->count(), 'running' => $running->count(), 'bundles_out' => $todayOut->count(),
                'wip' => (int) $mine->sum('qty') - (int) $mineOut->sum('output_qty'),
                'good' => 0, 'rework' => 0, 'reject' => 0, 'qc_pending' => (int) $mineOut->sum('output_qty'),
                'status' => $running->isNotEmpty() || $todayIn->isNotEmpty() || $todayOut->isNotEmpty() ? 'RUNNING' : 'IDLE',
            ];
        });

        return ApiResponse::ok(['date' => $date, 'rows' => $rows->all(), 'pending_module' => ['good' => '20/03', 'rework' => '20/03', 'reject' => '20/03']]);
    }

    /** Sewing WIP line by line, up to a day: what went on, what came off, and what is still on the line. */
    private function wip(string $date): array
    {
        $ins = AsmBundleIn::query()->whereDate('receive_date', '<=', $date)->get(['id', 'line_id', 'qty']);
        $outs = AsmBundleOut::query()->whereIn('bundle_in_id', $ins->pluck('id'))->whereDate('out_date', '<=', $date)->get(['bundle_in_id', 'output_qty'])->keyBy('bundle_in_id');

        return $ins->groupBy('line_id')->map(function ($g, $lineId) use ($outs) {
            $line = $this->lineIndex()->get($lineId);
            $in = (int) $g->sum('qty');
            $out = (int) $g->sum(fn ($i) => $outs[$i->id]->output_qty ?? 0);

            return ['line' => SewingFloor::lineLabel($line), 'floor' => $line?->floor, 'bundles' => $g->count(), 'running' => $g->filter(fn ($i) => ! isset($outs[$i->id]))->count(),
                'in_qty' => $in, 'output' => $out, 'wip' => $in - $out];
        })->sortBy('line')->values()->all();
    }

    /** A card that may go into sewing: known, printed, not cancelled, not received before. */
    private function receivable(string $code, bool $lock = false): CutBundle
    {
        $card = SewingFloor::card($code) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $code])]);
        if ($lock) {
            $card = CutBundle::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
        }
        if ($card->status === 'CANCELLED') {
            throw ValidationException::withMessages(['code' => __('assembly.v.cancelled', ['no' => $card->bundle_no])]);
        }
        if ($card->status === 'CREATED') {
            throw ValidationException::withMessages(['code' => __('assembly.v.unprinted', ['no' => $card->bundle_no])]);
        }
        $prior = AsmBundleIn::query()->where('bundle_id', $card->id)->first();
        if ($prior) {
            throw ValidationException::withMessages(['code' => __('assembly.v.already_in', ['no' => $card->bundle_no, 'date' => $prior->receive_date->format('d-m-Y'), 'line' => SewingFloor::lineLabel($this->lineIndex()->get($prior->line_id))])]);
        }

        return $card;
    }

    private function day(Request $request): string
    {
        $q = $request->validate(['date' => ['nullable', 'date']]);

        return isset($q['date']) ? CarbonImmutable::parse($q['date'])->toDateString() : SewingFloor::today()->toDateString();
    }

    private function assertOpen(string $date, string $field): void
    {
        if (! SewingFloor::isOpen($date)) {
            throw ValidationException::withMessages([$field => __('assembly.v.window', ['days' => SewingFloor::WINDOW_DAYS])]);
        }
    }

    /** @return list<string> */
    private function picked(Request $request): array
    {
        $data = $request->validate(['bundles' => ['required', 'array', 'min:1', 'max:500'], 'bundles.*' => ['string', 'max:24']], ['bundles.required' => __('assembly.v.pick'), 'bundles.min' => __('assembly.v.pick')]);

        return array_values(array_unique($data['bundles']));
    }

    private function assertFound(array $asked, Collection $found, string $key): void
    {
        $missing = array_values(array_diff($asked, $found->all()));
        if ($missing) {
            throw ValidationException::withMessages(['bundles' => __($key, ['no' => $missing[0]])]);
        }
    }

    private function lineIndex(): Collection
    {
        return $this->lineIndex ??= SewingFloor::lines($this->caller()->companyId);
    }

    private function buyer(?string $id): ?string
    {
        $this->buyerNames ??= DB::table('buyers')->where('company_id', $this->caller()->companyId)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);

        return $id ? ($this->buyerNames[$id] ?? null) : null;
    }

    /** The card's own facts, as both desks show them. */
    private function card(CutBundle $b): array
    {
        return ['bundle_no' => $b->bundle_no, 'barcode' => $b->barcode(), 'po_no' => $b->po_no, 'card_no' => $b->serial_from.'-'.$b->serial_to, 'cutting_no' => $b->cutting_no, 'buyer' => $this->buyer($b->buyer_id),
            'style' => $b->style, 'item' => $b->garment, 'colour' => $b->colour, 'size' => $b->size, 'parts_name' => $b->parts_name, 'qty' => $b->qty, 'cut_date' => $b->cut_date->toDateString()];
    }

    private function presentIn(AsmBundleIn $in, CutBundle $card, ?AsmBundleOut $out, ?int $sl, bool $open): array
    {
        $line = $this->lineIndex()->get($in->line_id);

        return $this->card($card) + [
            'sl' => $sl, 'time' => $in->created_at?->setTimezone('Asia/Dhaka')->format('H:i'), 'receive_date' => $in->receive_date->toDateString(),
            'bundle_qty' => $in->bundle_qty, 'in_qty' => $in->qty, 'short' => $in->short_qty, 'section' => $in->section, 'floor' => $line?->floor, 'line' => SewingFloor::lineLabel($line),
            'smv' => $in->smv !== null ? (float) $in->smv : null, 'manpower' => $in->manpower, 'daily_target' => $in->daily_target,
            'bundle_status' => SewingFloor::bundleStatus($in, $out), 'status' => 'RECEIVED', 'can_withdraw' => $open && $out === null,
        ];
    }

    private function presentOut(AsmBundleOut $out, AsmBundleIn $in, CutBundle $card, bool $open, bool $inFinishing = false): array
    {
        return $this->card($card) + [
            'line' => SewingFloor::lineLabel($this->lineIndex()->get($in->line_id)), 'in_qty' => $in->qty, 'output' => $out->output_qty, 'balance' => $out->balance_qty, 'mode' => $out->mode,
            'bundle_status' => $inFinishing ? 'IN_FINISHING' : SewingFloor::bundleStatus($in, $out), 'out_at' => $out->created_at?->setTimezone('Asia/Dhaka')->format('d-m-Y h:i A'), 'out_date' => $out->out_date->toDateString(),
            'status' => $out->mode === 'FULL' ? 'FULL' : 'SHORT', 'can_withdraw' => $open,
        ];
    }
}
