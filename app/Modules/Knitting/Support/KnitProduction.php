<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\LookupValue;
use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitProgram;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Support\Clock;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What Knit Production → Production (AnnotechERP 15/04) shares: the
 * pickers, Brickland's grey-roll defects and their four-point scores, the
 * grade a sheet's points per 100 kg come to, and the figures a production
 * order's register adds up — KP Made, KP Balance, KC Done, KC Balance and
 * what has been rolled.
 */
final class KnitProduction
{
    /**
     * The defects Brickland's inspection sheet counts: score and group.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    public const DEFECTS = [
        'HOLE' => [4, 'STRUCTURAL'], 'SLUB' => [1, 'YARN'], 'NEEDLE_LINE' => [3, 'MACHINE'], 'BARRE' => [3, 'YARN'], 'SET_OFF' => [2, 'MACHINE'],
        'STARS' => [2, 'MACHINE'], 'LYCRA_OUT' => [3, 'YARN'], 'LYCRA_DROP' => [3, 'YARN'], 'SINKER_MARK' => [2, 'MACHINE'], 'OIL_SPOT' => [2, 'STAIN'],
        'FLYING_DUST' => [1, 'YARN'], 'MISSING_YARN' => [4, 'STRUCTURAL'], 'YARN_CONTRA' => [2, 'YARN'], 'AIR_SPRAY_SPOT' => [1, 'STAIN'],
        'NEEDLE_BREAKAGE' => [4, 'STRUCTURAL'], 'PATTA' => [3, 'STRUCTURAL'], 'CITA_TARA' => [2, 'MACHINE'], 'WHEEL_FREE' => [2, 'MACHINE'],
        'LOOP' => [2, 'MACHINE'], 'LINE_STAR' => [3, 'MACHINE'], 'NEEDLE_DROP' => [3, 'STRUCTURAL'], 'NEEDLE_MARK' => [2, 'MACHINE'],
        'OIL_MARK' => [2, 'STAIN'], 'DIA_MARK' => [2, 'MACHINE'], 'THICK_THIN' => [2, 'YARN'],
    ];

    public const GRADES = ['A', 'B', 'C', 'D'];

    /** The grade points per 100 kg come to: A to 20, B to 40, C to 60, D beyond. */
    public static function gradeFor(float $gradePoint): string
    {
        return match (true) {
            $gradePoint <= 20 => 'A',
            $gradePoint <= 40 => 'B',
            $gradePoint <= 60 => 'C',
            default => 'D',
        };
    }

    /** @return array<string, mixed> */
    public static function options(): array
    {
        $company = app(TenantContext::class)->companyId();
        $buyers = Buyer::query()->orderBy('short_name')->get(['id', 'name', 'short_name'])->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $orders = Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('created_at')->limit(500)
            ->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no', 'buyer_id', 'order_type', 'ship_date']);
        $refs = DB::table('knit_ref_entries')->where('company_id', $company)->pluck('name', 'id');

        return [
            'company' => ['value' => $company, 'label' => DB::table('companies')->where('id', $company)->value('name')],
            'buyers' => $buyers->map(fn ($n, $id) => ['value' => $id, 'label' => $n])->values()->all(),
            // An order as Brickland's Job#/W.Style/Order No names it: 770MR · LPP-2026-000060.
            'orders' => $orders->map(fn (Order $o) => [
                'value' => $o->id, 'label' => self::orderLabel($o), 'style' => $o->work_style_no ?: $o->style_no, 'buyer_id' => $o->buyer_id,
                'buyer' => $buyers[$o->buyer_id] ?? null, 'order_type' => $o->order_type, 'ship_month' => $o->ship_date?->format('Y-m'),
            ])->values()->all(),
            'categories' => self::categories(),
            'fabric_types' => DB::table('fabric_types')->where('company_id', $company)->where('active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'code', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => "{$f->code} — {$f->name}"])->values()->all(),
            'colours' => DB::table('colours')->where('company_id', $company)->where('active', true)->orderBy('name')
                ->get(['id', 'code', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name, 'name' => $c->name])->values()->all(),
            'floors' => DB::table('floors')->where('company_id', $company)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => $f->code ? "{$f->name} ({$f->code})" : $f->name])->values()->all(),
            'machines' => DB::table('knit_machines')->where('company_id', $company)->where('active', true)->orderBy('machine_no')
                ->get(['id', 'machine_no', 'dia_id', 'gauge_id', 'rpm', 'floor_id'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no, 'dia' => $refs[$m->dia_id] ?? null, 'gauge' => $refs[$m->gauge_id] ?? null, 'rpm' => $m->rpm, 'floor_id' => $m->floor_id])->values()->all(),
            'shifts' => DB::table('hr_shifts')->where('company_id', $company)->orderBy('start_time')->get(['id', 'name', 'code', 'start_time', 'end_time'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code}) · ".Clock::range(substr((string) $s->start_time, 0, 5), substr((string) $s->end_time, 0, 5))])->values()->all(),
            'employees' => DB::table('hr_employees')->where('company_id', $company)->orderBy('employee_code')->get(['id', 'employee_code', 'name'])
                ->map(fn ($e) => ['value' => $e->id, 'label' => "{$e->employee_code} : {$e->name}"])->values()->all(),
            'parties' => DB::table('mp_suppliers')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'kinds' => KnitProdOrder::KINDS, 'order_statuses' => KnitProdOrder::STATUSES,
            'line_statuses' => KnitProgram::STATUSES, 'movements' => KnitRoll::MOVEMENTS, 'qualities' => KnitRoll::QUALITIES, 'grades' => self::GRADES,
            'defects' => collect(self::DEFECTS)->map(fn ($d, $code) => ['value' => $code, 'points' => $d[0], 'group' => $d[1]])->values()->all(),
        ];
    }

    /** Brickland's fabric booking categories (FP01 …) from the shared lookup table. */
    public static function categories(): array
    {
        $table = LookupTable::query()->visible()->where('code', 'FABRIC_BOOKING_CATEGORY')->orderByRaw('company_id is null')->first();
        if (! $table) {
            return [];
        }

        return $table->values()->visible()->where('active', true)->orderBy('name')->get()
            ->map(fn (LookupValue $v) => ['value' => $v->code, 'label' => "{$v->name} ({$v->code})"])->values()->all();
    }

    public static function orderLabel(?Order $o): ?string
    {
        return $o ? trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' · '.$o->order_no, ' ·') : null;
    }

    /**
     * Per production order: KP Made (live programmes), KC Done (live cards),
     * Rolled (net kg of its rolls).
     *
     * @param  Collection<int, string>|array<int, string>  $ids
     * @return array<string, array{kp: float, kc: float, rolled: float}>
     */
    public static function figures($ids): array
    {
        $ids = collect($ids)->values();
        $kp = KnitProgram::query()->whereIn('prod_order_id', $ids)->where('status', '!=', 'CANCELLED')->groupBy('prod_order_id')->selectRaw('prod_order_id, sum(qty) as q')->pluck('q', 'prod_order_id');
        $kc = KnitCard::query()->whereIn('prod_order_id', $ids)->where('status', '!=', 'CANCELLED')->groupBy('prod_order_id')->selectRaw('prod_order_id, sum(assigned_qty) as q')->pluck('q', 'prod_order_id');
        $rolled = KnitRoll::query()->join('knit_cards', 'knit_cards.id', '=', 'knit_rolls.card_id')->whereIn('knit_cards.prod_order_id', $ids)
            ->groupBy('knit_cards.prod_order_id')->selectRaw('knit_cards.prod_order_id as po, sum(knit_rolls.weight - knit_rolls.deducted) as q')->pluck('q', 'po');
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['kp' => round((float) ($kp[$id] ?? 0), 3), 'kc' => round((float) ($kc[$id] ?? 0), 3), 'rolled' => round((float) ($rolled[$id] ?? 0), 3)];
        }

        return $out;
    }

    /** A card's net kg rolled so far. */
    public static function rolledOn(string $cardId): float
    {
        return round((float) KnitRoll::query()->where('card_id', $cardId)->sum(DB::raw('weight - deducted')), 3);
    }

    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        return YarnProcurement::nextNumber($prefix, $table, $column, $date);
    }

    /** A machine's planned kilograms for a shift on a day: capacity per hour × planned hours, from its capacity plan. */
    public static function targetFor(?string $machineId, ?string $shiftId, string $day): ?float
    {
        if (! $machineId) {
            return null;
        }
        $plan = DB::table('knit_capacity_plans')->where('machine_id', $machineId)->where('active', true)->whereDate('effective_from', '<=', $day)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day))
            ->where(fn ($q) => $q->where('shift_id', $shiftId)->orWhereNull('shift_id'))
            ->orderByRaw('shift_id is null')->orderByDesc('effective_from')->first();

        return $plan ? round((float) $plan->capacity_per_hour * (float) $plan->plan_hours, 3) : null;
    }

    /**
     * A knit card as the floor's panels show it: buyer / style / order, fabric, colour, construction, assigned, rolled, left.
     *
     * @return list<array<string, mixed>>
     */
    public static function cardPanel(Collection $cards): array
    {
        $orders = KnitProdOrder::query()->whereIn('id', $cards->pluck('prod_order_id'))->get()->keyBy('id');
        $merch = Order::query()->whereIn('id', $orders->pluck('order_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $rolled = KnitRoll::query()->whereIn('card_id', $cards->pluck('id'))->groupBy('card_id')->selectRaw('card_id, sum(weight - deducted) as q')->pluck('q', 'card_id');

        return $cards->map(function (KnitCard $c) use ($orders, $merch, $buyers, $rolled) {
            $po = $orders[$c->prod_order_id];
            $o = $merch[$po->order_id] ?? null;
            $buyer = $buyers[$po->buyer_id] ?? null;

            return [
                'kc_no' => $c->kc_no, 'label' => $c->kc_no.' · '.($o?->work_style_no ?: $o?->style_no ?: $o?->job_no), 'buyer' => $buyer, 'fabric' => $po->fabric_desc,
                'colour' => $c->colour_name, 'machine_id' => $c->machine_id, 'status' => $c->status, 'category_code' => $po->category_code, 'floor_id' => $c->floor_id,
                'buyer_style_order' => implode(' / ', array_filter([$buyer, $o?->work_style_no ?: $o?->style_no, $o?->order_no])),
                'yarn_details' => $c->yarn_details, 'machine_dia' => $c->machine_dia, 'finish_dia' => $c->finish_dia, 'gauge' => $c->gauge,
                'assigned' => (float) $c->assigned_qty, 'rolled' => round((float) ($rolled[$c->id] ?? 0), 3), 'left' => round((float) $c->assigned_qty - (float) ($rolled[$c->id] ?? 0), 3),
            ];
        })->values()->all();
    }
}
