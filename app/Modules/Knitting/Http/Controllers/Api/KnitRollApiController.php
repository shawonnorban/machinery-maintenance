<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollInspection;
use App\Modules\Knitting\Models\KnitRollSheet;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
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
 * Knit Production → Production (AnnotechERP 15/04) → Manual Roll Production
 * Entry and Daily Knit Fabric Output, as Brickland works them: rolls weighed
 * against a knit card on a roll sheet (RS-…), numbered 1, 2, 3 per card —
 * the tag reads KC-2026100001/1 — with what came off the scale (Deducted)
 * kept apart; the Roll List's Pass QC, Reject and To Grey Store; and the
 * day's output per card, machine and shift against the machine's own
 * capacity plan (no plan, no target).
 */
class KnitRollApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $o = KnitProduction::options();

        return ApiResponse::ok(collect($o)->only(['company', 'machines', 'shifts', 'employees', 'parties', 'movements', 'qualities', 'buyers', 'orders', 'categories', 'colours', 'floors'])->all() + [
            'cards' => $this->cardPanel(KnitCard::query()->whereIn('status', KnitCard::LIVE)->orderByDesc('kc_no')->limit(300)->get()),
            'all_cards' => KnitCard::query()->orderByDesc('kc_no')->limit(500)->pluck('kc_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'sheets' => KnitRollSheet::query()->orderByDesc('sheet_no')->limit(300)->get(['sheet_no', 'card_id'])->map(fn ($s) => [
                'value' => $s->sheet_no, 'label' => $s->sheet_no.' · '.KnitCard::query()->whereKey($s->card_id)->value('kc_no'),
            ])->values()->all(),
        ]);
    }

    /** The Roll List: a card's rolls, or the floor's; every box narrows it. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'card' => ['nullable', 'string'], 'machine' => ['nullable', 'string'], 'movement' => ['nullable', Rule::in(KnitRoll::MOVEMENTS)],
            'quality' => ['nullable', Rule::in(KnitRoll::QUALITIES)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rolls = KnitRoll::query()
            ->when(! empty($f['card']), fn ($q) => $q->whereIn('card_id', KnitCard::query()->where('kc_no', $f['card'])->select('id')))
            ->when(! empty($f['machine']), fn ($q) => $q->where('machine_id', $f['machine']))
            ->when(! empty($f['movement']), fn ($q) => $q->where('movement', $f['movement']))
            ->when(! empty($f['quality']), fn ($q) => $q->where('quality', $f['quality']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('roll_at', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('roll_at', '<=', $f['to']))
            ->orderByDesc('roll_at')->orderByDesc('roll_no')->limit(500)->get();

        return ApiResponse::ok($this->presentRolls($rolls));
    }

    /** A card as the roll sheet heads it: whose fabric, the yarn, the machine's dia and gauge, assigned and left. */
    public function card(string $kc): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->cardPanel(collect([KnitCard::query()->where('kc_no', $kc)->firstOrFail()]))[0]);
    }

    public function showSheet(string $sheet): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->presentSheet(KnitRollSheet::query()->where('sheet_no', $sheet)->firstOrFail()));
    }

    public function saveSheet(Request $request, ?string $sheet = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $sheet ? KnitRollSheet::query()->where('sheet_no', $sheet)->firstOrFail() : null;
        $company = app(TenantContext::class)->companyId();
        $kg = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'kc_no' => ['required', 'string'],
            'rolls' => ['required', 'array', 'min:1', 'max:200'], 'rolls.*.roll_no' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'rolls.*.roll_date' => ['required', 'date'], 'rolls.*.shift_id' => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'rolls.*.operator_id' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'rolls.*.sc_party_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'rolls.*.machine_id' => ['nullable', Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            'rolls.*.weight' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'rolls.*.deducted' => $kg,
            'rolls.*.movement' => ['nullable', Rule::in(KnitRoll::MOVEMENTS)], 'rolls.*.quality' => ['nullable', Rule::in(KnitRoll::QUALITIES)],
            'rolls.*.comments' => ['nullable', 'string', 'max:300'],
        ], [
            'kc_no.required' => __('knitting.prod.v.card'), 'rolls.required' => __('knitting.prod.v.no_rolls'), 'rolls.min' => __('knitting.prod.v.no_rolls'),
            'rolls.*.roll_date.required' => __('knitting.prod.v.roll_date'), 'rolls.*.weight.required' => __('knitting.prod.v.roll_weight'),
            'rolls.*.weight.gt' => __('knitting.prod.v.roll_weight'),
        ]);
        $card = KnitCard::query()->where('kc_no', $data['kc_no'])->first();
        if (! $card || ($row && $row->card_id !== $card->id) || (! $row && ! in_array($card->status, KnitCard::LIVE, true))) {
            throw ValidationException::withMessages(['kc_no' => __('knitting.prod.v.card')]);
        }
        $rolls = YarnStore::inOrder($data['rolls']);
        $mine = $row ? KnitRoll::query()->where('sheet_id', $row->id)->get()->keyBy('roll_no') : collect();
        $taken = KnitRoll::query()->where('card_id', $card->id)->when($row, fn ($q) => $q->where('sheet_id', '!=', $row->id))->pluck('roll_no')->all();
        $errors = [];
        $seen = [];
        foreach ($rolls as $i => $r) {
            if ((float) ($r['deducted'] ?? 0) > (float) $r['weight']) {
                $errors["rolls.{$i}.deducted"] = __('knitting.prod.v.deducted', ['n' => $i + 1]);
            }
            if (! empty($r['roll_no'])) {
                if (in_array((int) $r['roll_no'], $taken, true) || in_array((int) $r['roll_no'], $seen, true)) {
                    $errors["rolls.{$i}.roll_no"] = __('knitting.prod.v.roll_taken', ['no' => "{$card->kc_no}/{$r['roll_no']}"]);
                }
                $seen[] = (int) $r['roll_no'];
            }
        }
        // A roll already inspected stays on the sheet.
        foreach ($mine as $no => $roll) {
            if (! in_array((int) $no, $seen, true) && KnitRollInspection::query()->where('roll_id', $roll->id)->exists()) {
                $errors['rolls'] = __('knitting.prod.v.roll_inspected', ['no' => $roll->roll_code]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $row = DB::transaction(function () use ($row, $card, $rolls, $mine, $taken, $seen): KnitRollSheet {
            $row ??= KnitRollSheet::query()->create([
                'sheet_no' => KnitProduction::nextNumber('RS', 'knit_roll_sheets', 'sheet_no', now()->toDateString()), 'card_id' => $card->id,
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $next = max([0, ...$taken, ...$seen]) + 1;
            $kept = [];
            foreach ($rolls as $r) {
                $no = ! empty($r['roll_no']) ? (int) $r['roll_no'] : $next++;
                $values = [
                    'roll_at' => CarbonImmutable::parse($r['roll_date'])->startOfDay(), 'shift_id' => $r['shift_id'] ?? null, 'operator_id' => $r['operator_id'] ?? null,
                    'sc_party_id' => $r['sc_party_id'] ?? null, 'machine_id' => $r['machine_id'] ?? null, 'weight' => (float) $r['weight'], 'deducted' => (float) ($r['deducted'] ?? 0),
                    'movement' => $r['movement'] ?? 'IN_KNITTING', 'quality' => $r['quality'] ?? 'PENDING', 'comments' => $r['comments'] ?? null,
                ];
                if ($mine->has($no)) {
                    $mine[$no]->update($values);
                } else {
                    KnitRoll::query()->create($values + ['sheet_id' => $row->id, 'card_id' => $card->id, 'roll_no' => $no, 'roll_code' => "{$card->kc_no}/{$no}"]);
                }
                $kept[] = $no;
            }
            KnitRoll::query()->where('sheet_id', $row->id)->whereNotIn('roll_no', $kept)->delete();
            $last = KnitRoll::query()->where('card_id', $card->id)->whereNotNull('machine_id')->orderByDesc('roll_at')->orderByDesc('roll_no')->value('machine_id');
            $card->update(['machine_id' => $last ?? $card->machine_id, 'status' => $card->status === 'OPEN' ? 'RUNNING' : $card->status]);

            return $row;
        });

        return $sheet ? ApiResponse::ok($this->presentSheet($row->refresh())) : ApiResponse::created($this->presentSheet($row));
    }

    /** The Roll List's Pass QC, Reject and To Grey Store. */
    public function rollAction(Request $request, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $code = $request->validate(['roll' => ['required', 'string']])['roll'];
        $roll = KnitRoll::query()->where('roll_code', $code)->firstOrFail();
        $patch = match ($action) {
            'pass' => ['quality' => 'PASSED'],
            'reject' => ['quality' => 'REJECTED'],
            'grey' => ['movement' => 'GREY_STORE'],
            default => abort(404),
        };
        if ($action === 'grey' && $roll->movement !== 'IN_KNITTING') {
            throw ValidationException::withMessages(['roll' => __('knitting.prod.v.roll_moved', ['no' => $roll->roll_code, 'where' => __("knitting.prod.movement.{$roll->movement}")])]);
        }
        if ($action === 'grey' && $roll->quality === 'REJECTED') {
            throw ValidationException::withMessages(['roll' => __('knitting.prod.v.roll_rejected', ['no' => $roll->roll_code])]);
        }
        $roll->update($patch);

        return ApiResponse::ok($this->presentRolls(collect([$roll->refresh()]))[0]);
    }

    /**
     * Daily Knit Fabric Output: one line per day, card, machine and shift —
     * that shift's net kg, the card's whole day, the machine's planned
     * target for the shift, and what the card still has to knit.
     */
    public function output(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:16'], 'colour' => ['nullable', 'string', 'max:80'], 'floor' => ['nullable', 'string'],
        ]);
        $orders = KnitProdOrder::query()
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_id', $f['order']))
            ->when(! empty($f['category']), fn ($q) => $q->where('category_code', $f['category']))
            ->get()->keyBy('id');
        $cards = KnitCard::query()->whereIn('prod_order_id', $orders->keys())
            ->when(! empty($f['colour']), fn ($q) => $q->where('colour_name', 'ilike', '%'.addcslashes($f['colour'], '%_\\').'%'))
            ->when(! empty($f['floor']), fn ($q) => $q->where('floor_id', $f['floor']))
            ->get()->keyBy('id');
        $rolls = KnitRoll::query()->whereIn('card_id', $cards->keys())
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('roll_at', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('roll_at', '<=', $f['to']))
            ->orderBy('roll_at')->get();
        $net = fn ($r) => (float) $r->weight - (float) $r->deducted;
        $machines = DB::table('knit_machines')->whereIn('id', $rolls->pluck('machine_id')->filter())->get(['id', 'machine_no', 'rpm', 'dia_id', 'floor_id'])->keyBy('id');
        $refs = DB::table('knit_ref_entries')->whereIn('id', $machines->pluck('dia_id')->filter())->pluck('name', 'id');
        $shifts = DB::table('hr_shifts')->whereIn('id', $rolls->pluck('shift_id')->filter())->pluck('name', 'id');
        $floors = DB::table('floors')->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $merch = Order::query()->whereIn('id', $orders->pluck('order_id'))->get()->keyBy('id');
        $byDay = $rolls->groupBy(fn ($r) => $r->roll_at->toDateString().'|'.$r->card_id);
        $out = [];
        foreach ($rolls->groupBy(fn ($r) => $r->roll_at->toDateString().'|'.$r->card_id.'|'.$r->machine_id.'|'.$r->shift_id) as $key => $group) {
            [$day, $cardId, $machineId, $shiftId] = explode('|', $key);
            $card = $cards[$cardId];
            $po = $orders[$card->prod_order_id];
            $o = $merch[$po->order_id] ?? null;
            $m = $machineId ? ($machines[$machineId] ?? null) : null;
            $toDate = KnitRoll::query()->where('card_id', $cardId)->whereDate('roll_at', '<=', $day)->get()->sum($net);
            $out[] = [
                'date' => $day, 'floor' => $floors[$card->floor_id ?? $m?->floor_id] ?? null, 'kind' => $po->order_kind, 'buyer' => $buyers[$po->buyer_id] ?? null,
                'style' => $o?->work_style_no ?: $o?->style_no ?: $o?->job_no, 'order_no' => $o?->order_no, 'colour' => $card->colour_name, 'fabric' => $po->fabric_desc,
                'finish_dia' => $card->finish_dia ?: $po->finish_dia, 'gsm' => $po->gsm, 'machine' => $m?->machine_no, 'rpm' => $m?->rpm,
                'machine_dia' => $card->machine_dia ?: ($m ? ($refs[$m->dia_id] ?? null) : null), 'shift' => $shiftId ? ($shifts[$shiftId] ?? null) : null,
                'all_shift' => round($byDay[$day.'|'.$cardId]->sum($net), 3), 'target' => $this->targetFor($machineId ?: null, $shiftId ?: null, $day),
                'total' => round($group->sum($net), 3), 'balance' => round((float) $card->assigned_qty - $toDate, 3), 'kc_no' => $card->kc_no,
            ];
        }

        return ApiResponse::ok(array_reverse($out));
    }

    /* ---------------- helpers ---------------- */

    /** The machine's planned kg for the shift that day; none without a plan. */
    private function targetFor(?string $machineId, ?string $shiftId, string $day): ?float
    {
        return KnitProduction::targetFor($machineId, $shiftId, $day);
    }

    /** @return list<array<string, mixed>> */
    private function cardPanel(Collection $cards): array
    {
        return KnitProduction::cardPanel($cards);
    }

    /** @return list<array<string, mixed>> */
    private function presentRolls(Collection $rolls): array
    {
        $shifts = DB::table('hr_shifts')->whereIn('id', $rolls->pluck('shift_id')->filter())->pluck('name', 'id');
        $people = DB::table('hr_employees')->whereIn('id', $rolls->pluck('operator_id')->filter())->pluck('name', 'id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rolls->pluck('sc_party_id')->filter())->pluck('name', 'id');
        $machines = DB::table('knit_machines')->whereIn('id', $rolls->pluck('machine_id')->filter())->pluck('machine_no', 'id');
        $cards = $this->cardPanel(KnitCard::query()->whereIn('id', $rolls->pluck('card_id'))->get());
        $byCard = collect($cards)->keyBy('kc_no');
        $kc = KnitCard::query()->whereIn('id', $rolls->pluck('card_id'))->pluck('kc_no', 'id');
        $inspected = KnitRollInspection::query()->whereIn('roll_id', $rolls->pluck('id'))->pluck('qci_no', 'roll_id');

        return $rolls->map(fn (KnitRoll $r) => [
            'roll_code' => $r->roll_code, 'roll_no' => $r->roll_no, 'kc_no' => $kc[$r->card_id] ?? null, 'label_info' => $r->label_info,
            'roll_at' => $r->roll_at->format('Y-m-d H:i'), 'shift' => $shifts[$r->shift_id] ?? null,
            'operator' => $people[$r->operator_id] ?? ($parties[$r->sc_party_id] ?? null), 'machine' => $machines[$r->machine_id] ?? null,
            'weight' => (float) $r->weight, 'deducted' => (float) $r->deducted, 'net' => round((float) $r->weight - (float) $r->deducted, 3),
            'movement' => $r->movement, 'quality' => $r->quality, 'comments' => $r->comments, 'inspection' => $inspected[$r->id] ?? null,
            'card' => $byCard[$kc[$r->card_id] ?? ''] ?? null,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentSheet(KnitRollSheet $row): array
    {
        $card = KnitCard::query()->findOrFail($row->card_id);
        $inspected = KnitRollInspection::query()->whereIn('roll_id', KnitRoll::query()->where('sheet_id', $row->id)->select('id'))->pluck('roll_id')->all();

        return [
            'sheet_no' => $row->sheet_no, 'kc_no' => $card->kc_no, 'card' => $this->cardPanel(collect([$card]))[0],
            'rolls' => KnitRoll::query()->where('sheet_id', $row->id)->orderBy('roll_no')->get()->map(fn (KnitRoll $r) => [
                'roll_no' => $r->roll_no, 'roll_code' => $r->roll_code, 'roll_date' => $r->roll_at->toDateString(), 'shift_id' => $r->shift_id, 'operator_id' => $r->operator_id,
                'sc_party_id' => $r->sc_party_id, 'machine_id' => $r->machine_id, 'weight' => (float) $r->weight, 'deducted' => (float) $r->deducted,
                'movement' => $r->movement, 'quality' => $r->quality, 'comments' => $r->comments, 'inspected' => in_array($r->id, $inspected, true),
            ])->values()->all(),
        ];
    }
}
