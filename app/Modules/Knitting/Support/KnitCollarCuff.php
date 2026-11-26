<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitCcChallan;
use App\Modules\Knitting\Models\KnitCcChallanLine;
use App\Modules\Knitting\Models\KnitCcProduction;
use App\Modules\Knitting\Models\KnitCcProgram;
use App\Modules\Knitting\Models\KnitCcProgramLine;
use App\Modules\Knitting\Models\KnitCcRequirement;
use App\Modules\Knitting\Models\KnitCcYarnIssue;
use App\Modules\Knitting\Models\KnitCcYarnReq;
use App\Modules\Knitting\Models\KnitCcYarnReqLine;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What Knit Production → Collar/Cuff (AnnotechERP 15/06) shares: the
 * pickers, and the figures nobody types — a requirement's produced pieces
 * per source and what is still open to move to the store, a programme
 * part's received pieces, and an indent line's issued yarn.
 */
final class KnitCollarCuff
{
    /** @return array<string, mixed> */
    public static function options(): array
    {
        $p = KnitProduction::options();
        $y = YarnProcurement::options();
        $company = app(TenantContext::class)->companyId();

        return collect($p)->only(['company', 'buyers', 'orders', 'categories', 'fabric_types', 'colours', 'floors', 'machines', 'shifts', 'employees', 'parties'])->all()
            + collect($y)->only(['stores', 'compositions', 'counts'])->all() + [
                'uoms' => array_map(fn (array $u) => ['value' => $u['code'], 'label' => $u['code']], UomCodes::options($company)),
                'types' => KnitCcRequirement::TYPES, 'items' => KnitCcRequirement::ITEMS, 'req_statuses' => KnitCcRequirement::STATUSES,
                'sources' => KnitCcProduction::SOURCES, 'program_statuses' => KnitCcProgram::STATUSES, 'yarn_statuses' => KnitCcYarnReq::STATUSES,
                'challan_types' => KnitCcChallan::TYPES, 'challan_statuses' => KnitCcChallan::STATUSES, 'req_types' => YarnStore::REQ_TYPES,
                'programs' => self::livePrograms(),
            ];
    }

    /**
     * Approved / in-production programmes with their parts and what each has
     * received: what a production line, a yarn indent or an S/C receive names.
     *
     * @return list<array<string, mixed>>
     */
    public static function livePrograms(): array
    {
        $programs = KnitCcProgram::query()->whereIn('status', KnitCcProgram::LIVE)->orderByDesc('program_no')->limit(300)->get();
        $lines = KnitCcProgramLine::query()->whereIn('program_id', $programs->pluck('id'))->orderBy('sort_order')->get();
        $received = self::received($lines->pluck('id'));
        $parties = DB::table('mp_suppliers')->whereIn('id', $programs->pluck('party_id'))->pluck('name', 'id');
        $byProgram = $lines->groupBy('program_id');

        return $programs->map(fn (KnitCcProgram $p) => [
            'value' => $p->program_no, 'label' => $p->program_no.' · '.($parties[$p->party_id] ?? ''), 'party_id' => $p->party_id, 'order_id' => $p->order_id,
            'lines' => ($byProgram[$p->id] ?? collect())->map(fn (KnitCcProgramLine $l) => [
                'value' => $l->id, 'label' => trim($p->program_no.' · '.__("knitting.cc.item.{$l->item}").' · '.($l->description ?? '').($l->size_desc ? " · {$l->size_desc}" : ''), ' ·'),
                'item' => $l->item, 'description' => $l->description, 'colour_id' => $l->colour_id, 'size_desc' => $l->size_desc,
                'qty' => round($l->qty, 3), 'received' => $received[$l->id] ?? 0.0,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Pieces produced per requirement and source, and the rejects.
     *
     * @return array<string, array{in: float, sc: float, reject: float}>
     */
    public static function produced($ids): array
    {
        $out = [];
        foreach (KnitCcProduction::query()->whereIn('requirement_id', collect($ids)->values())->groupBy('requirement_id', 'source')
            ->selectRaw('requirement_id, source, sum(qty) as q, sum(reject_qty) as r')->get() as $row) {
            $out[$row->requirement_id] ??= ['in' => 0.0, 'sc' => 0.0, 'reject' => 0.0];
            $out[$row->requirement_id][$row->source === 'IN_HOUSE' ? 'in' : 'sc'] += round((float) $row->q, 3);
            $out[$row->requirement_id]['reject'] += round((float) $row->r, 3);
        }

        return $out;
    }

    /**
     * Pieces put on live challans per requirement (a challan being edited aside).
     *
     * @return Collection<string, float>
     */
    public static function delivered($ids, ?string $exceptChallanId = null): Collection
    {
        $live = KnitCcChallan::query()->where('status', '!=', 'CANCELLED')->when($exceptChallanId, fn ($q) => $q->whereKeyNot($exceptChallanId))->select('id');

        return KnitCcChallanLine::query()->whereIn('requirement_id', collect($ids)->values())->whereIn('challan_id', $live)
            ->groupBy('requirement_id')->selectRaw('requirement_id, sum(qty) as q')->pluck('q', 'requirement_id')->map(fn ($q) => round((float) $q, 3));
    }

    /**
     * A programme part's received pieces: S/C receive challan lines the store has counted in.
     *
     * @return Collection<string, float>
     */
    public static function received($lineIds): Collection
    {
        $counted = KnitCcChallan::query()->where('challan_type', 'SC_RECEIVE')->where('status', 'RECEIVED')->select('id');

        return KnitCcChallanLine::query()->whereIn('program_line_id', collect($lineIds)->values())->whereIn('challan_id', $counted)
            ->groupBy('program_line_id')->selectRaw('program_line_id, sum(qty) as q')->pluck('q', 'program_line_id')->map(fn ($q) => round((float) $q, 3));
    }

    /**
     * Requirement figures for the register and the store's open panel.
     *
     * @param  Collection<int, KnitCcRequirement>  $rows
     * @return array<string, array<string, float>>
     */
    public static function figures(Collection $rows, ?string $exceptChallanId = null): array
    {
        $made = self::produced($rows->pluck('id'));
        $gone = self::delivered($rows->pluck('id'), $exceptChallanId);
        $out = [];
        foreach ($rows as $r) {
            $m = $made[$r->id] ?? ['in' => 0.0, 'sc' => 0.0, 'reject' => 0.0];
            $d = (float) ($gone[$r->id] ?? 0);
            $out[$r->id] = ['prod_in' => $m['in'], 'prod_sc' => $m['sc'], 'reject' => $m['reject'], 'delivered' => $d, 'open' => round(max(0, $m['in'] + $m['sc'] - $d), 3)];
        }

        return $out;
    }

    /** @return Collection<string, float> */
    public static function issued($lineIds): Collection
    {
        return KnitCcYarnIssue::query()->whereIn('req_line_id', collect($lineIds)->values())->groupBy('req_line_id')
            ->selectRaw('req_line_id, sum(qty) as q')->pluck('q', 'req_line_id')->map(fn ($q) => round((float) $q, 4));
    }

    /** An indent's standing follows what the store has released: Approved, Partly Issued, Issued. */
    public static function syncIssued(KnitCcYarnReq $req): void
    {
        if (! in_array($req->status, ['APPROVED', 'PARTLY_ISSUED', 'ISSUED'], true)) {
            return;
        }
        $lines = KnitCcYarnReqLine::query()->where('req_id', $req->id)->get();
        $asked = (float) $lines->sum('req_qty');
        $out = (float) self::issued($lines->pluck('id'))->sum();
        $req->update(['status' => $out <= 0 ? 'APPROVED' : ($out + 0.00005 >= $asked ? 'ISSUED' : 'PARTLY_ISSUED')]);
    }

    public static function orderLabel(?string $orderId): ?string
    {
        if (! $orderId) {
            return null;
        }
        $o = DB::table('merch_orders')->where('id', $orderId)->first(['order_no', 'job_no', 'style_no', 'work_style_no']);

        return $o ? trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' · '.$o->order_no, ' ·') : null;
    }

    /** @return Collection<string, string> */
    public static function orderLabels($ids): Collection
    {
        return DB::table('merch_orders')->whereIn('id', collect($ids)->filter()->unique()->values())->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])
            ->mapWithKeys(fn ($o) => [$o->id => trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' · '.$o->order_no, ' ·')]);
    }
}
