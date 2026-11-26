<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitMachineLoad;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Support\KnitProduction;
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
 * Knit Production → Production (AnnotechERP 15/04) → Knit Machine Loading
 * Plan, as Brickland works it: which knit card runs on which machine, shift
 * by shift. A machine runs one card per shift, so planning a cell that
 * already holds one replaces it; clearing a cell hands the shift back and its
 * card returns to Pending Knit Card (live cards nobody has booked a shift
 * for — a running card stays there until it is planned). Plan (Kg) defaults
 * to the machine's capacity plan for the shift; Produced (Kg) is what the
 * floor rolled on that machine, that card, that day and shift.
 */
class KnitMachineLoadApiController extends ApiController
{
    private const RANGES = ['1W' => 7, '2W' => 14, '1M' => 30, '2M' => 60];

    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $o = KnitProduction::options();

        return ApiResponse::ok(collect($o)->only(['company', 'floors', 'machines', 'shifts', 'categories'])->all() + [
            'ranges' => array_keys(self::RANGES),
            'dias' => collect($o['machines'])->pluck('dia')->filter()->unique()->sort()->values()->all(),
        ]);
    }

    /** The grid: machines down the side, day × shift across, each cell its booked card; and the pending cards. */
    public function grid(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'range' => ['nullable', Rule::in(array_keys(self::RANGES))], 'floor' => ['nullable', 'string'],
            'dia' => ['nullable', 'string', 'max:24'], 'machine' => ['nullable', 'string'],
        ]);
        $from = CarbonImmutable::parse($f['from'] ?? now()->toDateString())->startOfDay();
        $to = $from->addDays(self::RANGES[$f['range'] ?? '1W'] - 1);
        $machines = collect(KnitProduction::options()['machines'])
            ->filter(fn ($m) => (empty($f['floor']) || $m['floor_id'] === $f['floor']) && (empty($f['dia']) || $m['dia'] === $f['dia'])
                && (empty($f['machine']) || $m['value'] === $f['machine']))->values();
        $loads = KnitMachineLoad::query()->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])->whereIn('machine_id', $machines->pluck('value'))->get();
        $cards = KnitCard::query()->whereIn('id', $loads->pluck('card_id')->unique())->get()->keyBy('id');
        $produced = $this->produced($loads);
        $days = [];
        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $days[] = $d->toDateString();
        }

        return ApiResponse::ok([
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days, 'machines' => $machines->all(),
            'cells' => $loads->mapWithKeys(fn (KnitMachineLoad $l) => [$l->plan_date->toDateString().'|'.$l->shift_id.'|'.$l->machine_id => [
                'kc_no' => $cards[$l->card_id]->kc_no ?? null, 'colour' => $cards[$l->card_id]->colour_name ?? null, 'plan_qty' => $l->plan_qty,
                'produced' => $produced[$l->id] ?? 0.0,
            ]])->all(),
            'pending' => $this->pending(),
            'cards' => KnitProduction::cardPanel(KnitCard::query()->whereIn('status', KnitCard::LIVE)->orderByDesc('kc_no')->limit(300)->get()),
        ]);
    }

    /** Plan a cell: the card booked on that machine-shift, replacing whatever held it. */
    public function plan(Request $request): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'plan_date' => ['required', 'date'], 'shift_id' => ['required', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'machine_id' => ['required', Rule::exists('knit_machines', 'id')->where('company_id', $company)], 'kc_no' => ['required', 'string'],
            'plan_qty' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
        ], ['kc_no.required' => __('knitting.ml.v.card')]);
        $card = KnitCard::query()->where('kc_no', $data['kc_no'])->first();
        if (! $card || ! in_array($card->status, KnitCard::LIVE, true)) {
            throw ValidationException::withMessages(['kc_no' => __('knitting.ml.v.card_live', ['no' => $data['kc_no']])]);
        }
        $qty = isset($data['plan_qty']) ? (float) $data['plan_qty'] : KnitProduction::targetFor($data['machine_id'], $data['shift_id'], $data['plan_date']);
        $load = KnitMachineLoad::query()->updateOrCreate(
            ['plan_date' => $data['plan_date'], 'shift_id' => $data['shift_id'], 'machine_id' => $data['machine_id']],
            ['card_id' => $card->id, 'plan_qty' => $qty, 'created_by' => $this->caller()->auditUserId()],
        );

        return ApiResponse::ok(['kc_no' => $card->kc_no, 'colour' => $card->colour_name, 'plan_qty' => $load->plan_qty, 'produced' => $this->produced(collect([$load]))[$load->id] ?? 0.0,
            'pending' => $this->pending(), 'message' => __('knitting.ml.done.planned', ['no' => $card->kc_no])]);
    }

    /** Clear a cell: the shift is handed back. */
    public function clear(Request $request): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $data = $request->validate(['plan_date' => ['required', 'date'], 'shift_id' => ['required', 'string'], 'machine_id' => ['required', 'string']]);
        KnitMachineLoad::query()->whereDate('plan_date', $data['plan_date'])->where('shift_id', $data['shift_id'])->where('machine_id', $data['machine_id'])->delete();

        return ApiResponse::ok(['pending' => $this->pending(), 'message' => __('knitting.ml.done.cleared')]);
    }

    /** Planned Shifts: every planned machine-shift with what the floor got. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $loads = KnitMachineLoad::query()->orderByDesc('plan_date')->limit(1000)->get();
        $produced = $this->produced($loads);
        $machines = collect(KnitProduction::options()['machines'])->keyBy('value');
        $floors = collect(KnitProduction::options()['floors'])->pluck('label', 'value');
        $shifts = DB::table('hr_shifts')->whereIn('id', $loads->pluck('shift_id')->unique())->get(['id', 'name', 'start_time'])->keyBy('id');
        $cards = collect(KnitProduction::cardPanel(KnitCard::query()->whereIn('id', $loads->pluck('card_id')->unique())->get()))->keyBy('kc_no');
        $kc = KnitCard::query()->whereIn('id', $loads->pluck('card_id')->unique())->pluck('kc_no', 'id');

        return ApiResponse::ok($loads->sortBy(fn ($l) => [$l->plan_date->toDateString(), (string) ($shifts[$l->shift_id]->start_time ?? '')])->reverse()->map(function (KnitMachineLoad $l) use ($produced, $machines, $floors, $shifts, $cards, $kc) {
            $m = $machines[$l->machine_id] ?? null;
            $card = $cards[$kc[$l->card_id] ?? ''] ?? null;

            return [
                'plan_date' => $l->plan_date->toDateString(), 'shift_id' => $l->shift_id, 'shift' => $shifts[$l->shift_id]->name ?? null, 'machine_id' => $l->machine_id,
                'machine' => $m['label'] ?? null, 'dia_gauge' => $m ? trim(($m['dia'] ?? '').' x '.($m['gauge'] ?? ''), ' x') : null,
                'floor_id' => $m['floor_id'] ?? null, 'floor' => $floors[$m['floor_id'] ?? ''] ?? null, 'kc_no' => $kc[$l->card_id] ?? null,
                'job' => $card['buyer_style_order'] ?? null, 'colour' => $card['colour'] ?? null, 'plan_qty' => $l->plan_qty, 'produced' => $produced[$l->id] ?? 0.0,
            ];
        })->values()->all());
    }

    /** Live cards nobody has booked a shift for. */
    private function pending(): array
    {
        $cards = KnitCard::query()->whereIn('status', KnitCard::LIVE)->whereNotIn('id', KnitMachineLoad::query()->select('card_id'))->orderByDesc('kc_no')->limit(300)->get();

        return KnitProduction::cardPanel($cards);
    }

    /** @return array<string, float> load id → net kg rolled on that machine, card, day and shift */
    private function produced(Collection $loads): array
    {
        if ($loads->isEmpty()) {
            return [];
        }
        $rolls = KnitRoll::query()->whereIn('card_id', $loads->pluck('card_id')->unique())->whereIn('machine_id', $loads->pluck('machine_id')->unique())->get();
        $sums = $rolls->groupBy(fn (KnitRoll $r) => $r->roll_at->toDateString().'|'.$r->shift_id.'|'.$r->machine_id.'|'.$r->card_id)
            ->map(fn ($g) => round((float) $g->sum(fn ($r) => (float) $r->weight - (float) $r->deducted), 3));

        return $loads->mapWithKeys(fn (KnitMachineLoad $l) => [$l->id => (float) ($sums[$l->plan_date->toDateString().'|'.$l->shift_id.'|'.$l->machine_id.'|'.$l->card_id] ?? 0)])->all();
    }
}
