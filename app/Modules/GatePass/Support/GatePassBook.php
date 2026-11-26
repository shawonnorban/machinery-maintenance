<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Support;

use App\Modules\GatePass\Models\GatePass;
use App\Modules\GatePass\Models\GatePassEvent;
use App\Modules\GatePass\Models\GatePassIn;
use App\Modules\GatePass\Models\GatePassInLine;
use App\Modules\GatePass\Models\GatePassLine;
use App\Modules\GatePass\Models\GatePassLocation;
use App\Modules\GatePass\Models\GatePassPurpose;
use App\Modules\HrPayroll\Models\HrEmployee;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The gate pass books (AnnotechERP 11/02–11/04): the number a pass takes
 * (GP-2026-000001) and a Gate IN takes (GPI-2026-000001), each pass's OUT,
 * returned, short and balance worked out from its lines and its Gate INs,
 * how far it has come back, and its audit trail.
 */
final class GatePassBook
{
    /** The next gate pass number, under a lock so two saves never share one. */
    public static function nextNumber(string $date): string
    {
        return self::take('GP', 'gate_passes', 'gp_no', $date);
    }

    /** What the next gate pass number would be, before the save. */
    public static function previewNumber(string $date): string
    {
        $head = 'GP-'.CarbonImmutable::parse($date)->format('Y').'-';

        return $head.str_pad((string) (1 + self::last($head, 'gate_passes', 'gp_no')), 6, '0', STR_PAD_LEFT);
    }

    /** The next Gate IN number. */
    public static function nextInNumber(string $date): string
    {
        return self::take('GPI', 'gate_pass_ins', 'gpi_no', $date);
    }

    /** A step on the pass's audit trail. */
    public static function event(GatePass $pass, string $action, ?string $note = null, ?string $userId = null): void
    {
        GatePassEvent::query()->create(['gate_pass_id' => $pass->id, 'action' => $action, 'to_status' => $pass->status, 'note' => $note, 'user_id' => $userId]);
    }

    /**
     * What has come back of each line, over every Gate IN.
     *
     * @return array<string, float> line id => quantity
     */
    public static function returned(Collection $passIds): array
    {
        return GatePassInLine::query()->whereIn('gate_pass_in_id', GatePassIn::query()->whereIn('gate_pass_id', $passIds->all())->select('id'))
            ->groupBy('gate_pass_line_id')->select('gate_pass_line_id', DB::raw('sum(quantity) as q'))->pluck('q', 'gate_pass_line_id')
            ->map(fn ($q) => round((float) $q, 3))->all();
    }

    /**
     * Each pass's figures: OUT, returned, short and balance (OUT − returned − short, none on an OUT ONLY pass),
     * the unit when every line shares one, and how far it has come back.
     *
     * @param  Collection<int, GatePass>  $passes
     * @return array<string, array{out: float, returned: float, short: float, balance: float, unit: ?string, items: list<string>, return: ?string}>
     */
    public static function figures(Collection $passes): array
    {
        $lines = GatePassLine::query()->whereIn('gate_pass_id', $passes->pluck('id'))->orderBy('sort_order')->get()->groupBy('gate_pass_id');
        $returned = self::returned($passes->pluck('id'));
        $items = DB::table('gate_pass_items')->whereIn('id', $lines->flatten()->pluck('item_id')->unique()->all())->pluck('name', 'id');
        $out = [];
        foreach ($passes as $p) {
            $set = $lines[$p->id] ?? collect();
            $o = round((float) $set->sum('quantity'), 3);
            $r = round((float) $set->sum(fn (GatePassLine $l) => $returned[$l->id] ?? 0), 3);
            $s = round((float) $set->sum('short_qty'), 3);
            $units = $set->pluck('unit')->unique();
            // Nothing is owed back on an OUT ONLY pass.
            $out[$p->id] = [
                'out' => $o, 'returned' => $r, 'short' => $s, 'balance' => $p->returnable() ? round(max(0, $o - $r - $s), 3) : 0.0, 'unit' => $units->count() === 1 ? $units->first() : null,
                'items' => $set->map(fn (GatePassLine $l) => (string) ($items[$l->item_id] ?? ''))->values()->all(),
                'return' => self::returnState($p, $o, $r, $s),
            ];
        }

        return $out;
    }

    /**
     * The gate pass lists' rows, as Brickland lays them out — `mine` for the passes the user prepared.
     *
     * @param  Collection<int, GatePass>  $passes
     * @return list<array<string, mixed>>
     */
    public static function rows(Collection $passes, ?string $userId, bool $withIn = false): array
    {
        $figures = self::figures($passes);
        $purposes = GatePassPurpose::query()->pluck('name', 'id');
        $locations = GatePassLocation::query()->whereIn('id', $passes->pluck('location_id')->filter()->unique())->pluck('name', 'id');
        $employees = HrEmployee::query()->whereIn('id', $passes->pluck('employee_id')->filter()->unique())->get(['id', 'name', 'card_no'])->keyBy('id');
        $users = DB::table('users')->whereIn('id', $passes->pluck('prepared_by')->filter()->unique())->pluck('name', 'id');
        $lastIn = $withIn ? GatePassIn::query()->whereIn('gate_pass_id', $passes->pluck('id'))->groupBy('gate_pass_id')->selectRaw('gate_pass_id, max(in_at) as at')->pluck('at', 'gate_pass_id') : collect();

        return $passes->map(function (GatePass $p) use ($figures, $purposes, $locations, $employees, $users, $lastIn, $userId): array {
            $fig = $figures[$p->id];

            return [
                'gp_no' => $p->gp_no, 'date' => $p->gp_date->toDateString(), 'kind' => $p->kind, 'pass_type' => $p->pass_type, 'purpose' => $purposes[$p->purpose_id] ?? null,
                'destination' => $locations[$p->location_id] ?? $p->destination_text, 'taken_by' => $p->employee_id ? ($employees[$p->employee_id]->name ?? null) : $p->carrier_name,
                'items' => $fig['items'], 'out' => $fig['out'], 'returned' => $fig['returned'], 'short' => $fig['short'], 'balance' => $fig['balance'], 'unit' => $fig['unit'],
                'status' => $p->status, 'return' => $fig['return'], 'prepared_by' => $users[$p->prepared_by] ?? null, 'expected_return' => $p->expected_return?->toDateString(),
                'out_at' => $p->out_at?->toIso8601String(), 'in_at' => isset($lastIn[$p->id]) ? CarbonImmutable::parse($lastIn[$p->id])->toIso8601String() : null,
                'mine' => $userId !== null && $p->prepared_by === $userId,
            ];
        })->values()->all();
    }

    /** How far a returnable pass has come back: PENDING, PARTIAL, FULL or SHORT — none before it goes out, or for OUT ONLY. */
    public static function returnState(GatePass $p, float $out, float $returned, float $short): ?string
    {
        if (! $p->returnable() || ! in_array($p->status, GatePass::WENT_OUT, true)) {
            return null;
        }
        if ($p->kind === 'MAIN') {
            return $p->status === 'CLOSED' ? 'FULL' : 'PENDING';
        }

        return match (true) {
            $short > 0.0005 => 'SHORT',
            $returned + 0.0005 >= $out && $out > 0 => 'FULL',
            $returned > 0.0005 => 'PARTIAL',
            default => 'PENDING',
        };
    }

    /** 40, 2.5, 0.125 — never 40.000. */
    public static function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    /** "3 Pcs" in the language asked for; a bare number when the lines' units differ. */
    public static function withUnit(float $n, ?string $unit, ?string $locale = null): string
    {
        return $unit ? self::qty($n).' '.__("gatepass.master.opt.unit.{$unit}", [], $locale) : self::qty($n);
    }

    private static function take(string $prefix, string $table, string $column, string $date): string
    {
        $head = $prefix.'-'.CarbonImmutable::parse($date)->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$head}:".app(TenantContext::class)->companyId()]);

        return $head.str_pad((string) (1 + self::last($head, $table, $column)), 6, '0', STR_PAD_LEFT);
    }

    private static function last(string $head, string $table, string $column): int
    {
        return (int) DB::table($table)->where('company_id', app(TenantContext::class)->companyId())->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();
    }
}
