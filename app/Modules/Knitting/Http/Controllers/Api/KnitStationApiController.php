<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitMachineLoad;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollSheet;
use App\Modules\Knitting\Models\KnitStation;
use App\Modules\Knitting\Models\KnitStationMachine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Operator Roll
 * Production Entry, as Brickland works it: an operator's station shows its
 * machines as tabs (Tab Settings for Machine(s) ticks which, the station
 * opening on the first). On a machine's tab the operator weighs each roll as
 * it comes off: it lands on the card the loading plan booked for that
 * machine this shift — else the card last running on it — on the day's roll
 * sheet for that card, numbered on after the card's last roll, exactly as a
 * roll keyed on Manual Roll Production Entry would.
 */
class KnitStationApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $o = KnitProduction::options();

        return ApiResponse::ok(collect($o)->only(['machines', 'shifts', 'employees', 'floors'])->all() + [
            'stations' => $this->stationRows(),
            'cards' => KnitProduction::cardPanel(KnitCard::query()->whereIn('status', KnitCard::LIVE)->orderByDesc('kc_no')->limit(300)->get()),
            'current_shift' => self::currentShift(),
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($code)));
    }

    public function save(Request $request, ?string $code = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $code ? $this->find($code) : null;
        $company = app(TenantContext::class)->companyId();
        // A code is kept upper-cased, so ST-01 and st-01 are one station.
        if (is_string($request->input('code'))) {
            $request->merge(['code' => mb_strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.\-]*$/', Rule::unique('knit_stations', 'code')->where('company_id', $company)->ignore($row?->id)],
            'name' => ['required', 'string', 'max:120'], 'operator_id' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'active' => ['boolean'], 'machine_ids' => ['sometimes', 'array', 'max:100'], 'machine_ids.*' => ['distinct', Rule::exists('knit_machines', 'id')->where('company_id', $company)],
        ], [
            'code.required' => __('knitting.op.v.code'), 'code.unique' => __('knitting.op.v.code_taken'), 'code.regex' => __('knitting.op.v.code_format'), 'name.required' => __('knitting.op.v.name'),
        ]);
        if (array_key_exists('machine_ids', $data) && $data['machine_ids'] === []) {
            throw ValidationException::withMessages(['machine_ids' => __('knitting.op.v.no_machines')]);
        }
        $row = DB::transaction(function () use ($row, $data, $request): KnitStation {
            $head = ['code' => mb_strtoupper(trim($data['code'])), 'name' => trim($data['name']), 'operator_id' => $data['operator_id'] ?? null, 'active' => $request->boolean('active', true)];
            $row ??= KnitStation::query()->create($head);
            $row->update($head);
            // The machine list is the Tab Settings' to write; the station form leaves it as it is.
            if (array_key_exists('machine_ids', $data)) {
                KnitStationMachine::query()->where('station_id', $row->id)->delete();
                foreach (array_values($data['machine_ids']) as $i => $id) {
                    KnitStationMachine::query()->create(['company_id' => $row->company_id, 'station_id' => $row->id, 'machine_id' => $id, 'sort_order' => $i + 1]);
                }
            }

            return $row;
        });

        return $code ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    /** A roll off one of the station's machines, onto its card's roll sheet for today. */
    public function roll(Request $request, string $code): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $station = $this->find($code);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'machine_id' => ['required', 'string'], 'kc_no' => ['required', 'string'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'deducted' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'shift_id' => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'operator_id' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)], 'comments' => ['nullable', 'string', 'max:300'],
        ], [
            'kc_no.required' => __('knitting.op.v.card'), 'weight.required' => __('knitting.op.v.weight'), 'weight.gt' => __('knitting.op.v.weight'),
        ]);
        if (! KnitStationMachine::query()->where('station_id', $station->id)->where('machine_id', $data['machine_id'])->exists()) {
            throw ValidationException::withMessages(['machine_id' => __('knitting.op.v.not_here', ['station' => $station->code])]);
        }
        $card = KnitCard::query()->where('kc_no', $data['kc_no'])->first();
        if (! $card || ! in_array($card->status, KnitCard::LIVE, true)) {
            throw ValidationException::withMessages(['kc_no' => __('knitting.op.v.card')]);
        }
        if ((float) ($data['deducted'] ?? 0) > (float) $data['weight']) {
            throw ValidationException::withMessages(['deducted' => __('knitting.op.v.deducted')]);
        }
        $roll = DB::transaction(function () use ($card, $data, $station): KnitRoll {
            $sheet = KnitRollSheet::query()->where('card_id', $card->id)->whereDate('created_at', now()->toDateString())->orderByDesc('created_at')->first()
                ?? KnitRollSheet::query()->create([
                    'sheet_no' => KnitProduction::nextNumber('RS', 'knit_roll_sheets', 'sheet_no', now()->toDateString()), 'card_id' => $card->id, 'created_by' => $this->caller()->auditUserId(),
                ]);
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['knit_rolls:'.$card->id]);
            $no = 1 + (int) KnitRoll::query()->where('card_id', $card->id)->max('roll_no');
            $roll = KnitRoll::query()->create([
                'sheet_id' => $sheet->id, 'card_id' => $card->id, 'roll_no' => $no, 'roll_code' => "{$card->kc_no}/{$no}", 'roll_at' => now(),
                'shift_id' => $data['shift_id'] ?? self::currentShift(), 'operator_id' => $data['operator_id'] ?? $station->operator_id, 'machine_id' => $data['machine_id'],
                'weight' => (float) $data['weight'], 'deducted' => (float) ($data['deducted'] ?? 0), 'movement' => 'IN_KNITTING', 'quality' => 'PENDING',
                'comments' => $data['comments'] ?? null,
            ]);
            $card->update(['machine_id' => $data['machine_id'], 'status' => $card->status === 'OPEN' ? 'RUNNING' : $card->status]);

            return $roll;
        });

        return ApiResponse::created($this->present($station->refresh()) + ['roll_code' => $roll->roll_code, 'message' => __('knitting.op.done.roll', ['no' => $roll->roll_code])]);
    }

    /** The shift running now, by the clock. */
    public static function currentShift(): ?string
    {
        $now = now()->format('H:i:s');
        foreach (DB::table('hr_shifts')->where('company_id', app(TenantContext::class)->companyId())->orderBy('start_time')->get(['id', 'start_time', 'end_time']) as $s) {
            $start = (string) $s->start_time;
            $end = (string) $s->end_time;
            $in = $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
            if ($in) {
                return $s->id;
            }
        }

        return null;
    }

    private function find(string $code): KnitStation
    {
        return KnitStation::query()->where('code', $code)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function stationRows(): array
    {
        $rows = KnitStation::query()->orderBy('code')->get();
        $counts = KnitStationMachine::query()->groupBy('station_id')->selectRaw('station_id, count(*) as n')->pluck('n', 'station_id');
        $people = DB::table('hr_employees')->whereIn('id', $rows->pluck('operator_id')->filter())->pluck('name', 'id');

        return $rows->map(fn (KnitStation $s) => ['value' => $s->code, 'label' => "{$s->code} — {$s->name}", 'name' => $s->name, 'operator' => $people[$s->operator_id] ?? null,
            'machines' => (int) ($counts[$s->id] ?? 0), 'active' => $s->active])->values()->all();
    }

    /** @return array<string, mixed> the station, and per machine the card it is on and today's rolls */
    private function present(KnitStation $s): array
    {
        $links = KnitStationMachine::query()->where('station_id', $s->id)->orderBy('sort_order')->get();
        $machineIds = $links->pluck('machine_id');
        $today = now()->toDateString();
        $shift = self::currentShift();
        $planned = KnitMachineLoad::query()->whereDate('plan_date', $today)->where('shift_id', $shift)->whereIn('machine_id', $machineIds)->pluck('card_id', 'machine_id');
        $running = KnitCard::query()->whereIn('status', KnitCard::LIVE)->whereIn('machine_id', $machineIds)->orderByDesc('updated_at')->get()->unique('machine_id')->pluck('id', 'machine_id');
        $cardIds = $planned->values()->merge($running->values())->unique();
        $panels = collect(KnitProduction::cardPanel(KnitCard::query()->whereIn('id', $cardIds)->get()))->keyBy('kc_no');
        $kc = KnitCard::query()->whereIn('id', $cardIds)->pluck('kc_no', 'id');
        $rolls = KnitRoll::query()->whereIn('machine_id', $machineIds)->whereDate('roll_at', $today)->orderByDesc('roll_at')->get()->groupBy('machine_id');
        $shifts = DB::table('hr_shifts')->pluck('name', 'id');
        $people = DB::table('hr_employees')->pluck('name', 'id');
        $machines = collect(KnitProduction::options()['machines'])->keyBy('value');

        return [
            'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'operator_id' => $s->operator_id, 'active' => $s->active, 'current_shift' => $shift,
            'machine_ids' => $machineIds->values()->all(),
            'machines' => $links->map(function (KnitStationMachine $l) use ($planned, $running, $panels, $kc, $rolls, $shifts, $people, $machines) {
                $cardId = $planned[$l->machine_id] ?? $running[$l->machine_id] ?? null;
                $m = $machines[$l->machine_id] ?? null;

                return [
                    'machine_id' => $l->machine_id, 'machine' => $m['label'] ?? null, 'dia' => $m['dia'] ?? null, 'gauge' => $m['gauge'] ?? null,
                    'planned' => isset($planned[$l->machine_id]), 'card' => $cardId ? ($panels[$kc[$cardId] ?? ''] ?? null) : null,
                    'rolls' => ($rolls[$l->machine_id] ?? collect())->map(fn (KnitRoll $r) => [
                        'roll_code' => $r->roll_code, 'at' => CarbonImmutable::parse($r->roll_at)->format('H:i'), 'shift' => $shifts[$r->shift_id] ?? null,
                        'operator' => $people[$r->operator_id] ?? null, 'weight' => (float) $r->weight, 'deducted' => (float) $r->deducted,
                        'net' => round((float) $r->weight - (float) $r->deducted, 3), 'quality' => $r->quality,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
