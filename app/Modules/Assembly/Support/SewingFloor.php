<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Support;

use App\Modules\Assembly\Models\AsmBundleIn;
use App\Modules\Assembly\Models\AsmBundleOut;
use App\Modules\Cutting\Models\CutBundle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembly Floor → Sewing (AnnotechERP 20/01), the rules both desks share:
 * the days a desk works on (today and the six before it), a scanned card
 * found by its number or its barcode, the plan a line is running on a day
 * (Work Study's target sheet — style, SMV, manpower, target), and where a
 * bundle stands on the sewing floor.
 */
final class SewingFloor
{
    /** How many days back a desk may receive or send out (today included). */
    public const WINDOW_DAYS = 7;

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Dhaka')->startOfDay();
    }

    /** The desk's days, newest first: what its Receive Date offers. */
    public static function days(): array
    {
        $today = self::today();

        return collect(range(0, self::WINDOW_DAYS - 1))->map(fn ($n) => $today->subDays($n)->toDateString())->all();
    }

    /** A day the desk is still open on. */
    public static function isOpen(string $date): bool
    {
        return in_array(CarbonImmutable::parse($date)->toDateString(), self::days(), true);
    }

    /** The card a scan names: its number (BND-20261106-0001) or its barcode (BND202611060001). */
    public static function card(string $code): ?CutBundle
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return CutBundle::query()->where(fn ($w) => $w->where('bundle_no', $code)->orWhereRaw("replace(bundle_no, '-', '') = ?", [str_replace('-', '', $code)]))->first();
    }

    /**
     * The plan a line runs on a day — Work Study's target sheet (style, item,
     * SMV, Used OP + ASO as manpower, general + OT target, working hours);
     * without a sheet, the line's open allocation and its standard manpower.
     *
     * @return array{style: ?string, item: ?string, smv: ?float, manpower: ?int, target: ?int, hours: ?float, source: string}
     */
    public static function plan(string $lineId, string $date): array
    {
        $t = DB::table('ws_sewing_targets')->where('planning_line_id', $lineId)->whereDate('target_date', $date)->where('is_off', false)->orderBy('sort_order')->orderBy('created_at')->first();
        if ($t) {
            return ['style' => $t->order_style, 'item' => $t->gmt_item, 'smv' => (float) $t->smv, 'manpower' => (int) $t->used_op + (int) $t->used_aso,
                'target' => (int) $t->target_gen + (int) ($t->target_ot ?? 0), 'hours' => (float) $t->wh, 'source' => 'TARGET'];
        }
        $line = DB::table('planning_lines')->where('id', $lineId)->first(['std_manpower', 'std_hours']);
        $a = DB::table('ws_line_allocations')->where('planning_line_id', $lineId)->where('status', 'OPEN')->orderByDesc('input_date')->orderByDesc('id')->first();

        return ['style' => $a?->style ?: $a?->order_style, 'item' => $a?->item, 'smv' => $a && $a->smv !== null ? (float) $a->smv : null,
            'manpower' => $line && $line->std_manpower ? (int) $line->std_manpower : null, 'target' => null, 'hours' => $line ? (float) $line->std_hours : null, 'source' => $a ? 'ALLOCATION' : 'NONE'];
    }

    /** Lines by id: their code and name and floor. */
    public static function lines(string $companyId): Collection
    {
        return DB::table('planning_lines as l')->join('floors as f', 'f.id', '=', 'l.floor_id')->where('l.company_id', $companyId)
            ->orderBy('f.name')->orderBy('l.display_order')->orderBy('l.code')
            ->get(['l.id', 'l.code', 'l.name', 'l.floor_id', 'l.active', 'f.name as floor'])->keyBy('id');
    }

    public static function lineLabel(?object $line): ?string
    {
        return $line ? trim($line->code.' · '.$line->name) : null;
    }

    /** Where a bundle stands on the sewing floor: on the line, or off it and waiting for end-line QC. */
    public static function bundleStatus(AsmBundleIn $in, ?AsmBundleOut $out): string
    {
        return $out ? 'QC_PENDING' : 'IN_SEWING';
    }
}
