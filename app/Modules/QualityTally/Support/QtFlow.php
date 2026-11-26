<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Support;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\QualityTally\Models\QtJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who works in which section, and a job's way through the floor (user,
 * 2026-11-25): cutting, sewing and quality each have their own manager, QCs
 * and menu. A job starts at cutting and ends at quality. Cutting's QCs check
 * the body parts panel by panel; a PIECE leaves cutting only when every panel
 * of its garment has passed, so a size's complete pieces are the smallest
 * panel pass of that size — a paired part (Sleeve) counts twice a piece. A
 * running job starts from its OPENING pieces (passed before this system). Sewing and quality have no input of their own —
 * the manager's LINE TARGET puts a job on a line; what their QC may check is
 * what the section before has passed and they have not checked yet.
 * Pass is annotech-rmg's: RFT + rectify, never above checked (RFT + defect).
 */
final class QtFlow
{
    /** Sees a section's screens: every section's viewer, or that section's own viewer or manager. */
    public static function sees(ApiCaller $caller, string $section): bool
    {
        return $caller->can('qtally.view') || $caller->can('qtally.'.strtolower($section).'.view') || self::manages($caller, $section);
    }

    /** The section's manager: their own section's permission, or the all-section target permission. */
    public static function manages(ApiCaller $caller, string $section): bool
    {
        return $caller->can('qtally.target.manage') || $caller->can('qtally.'.strtolower($section).'.manage');
    }

    /** Taps in a section (on the lines they are assigned to). */
    public static function taps(ApiCaller $caller, string $section): bool
    {
        return $caller->can('qtally.tally.create') || $caller->can('qtally.'.strtolower($section).'.tally');
    }

    /** @return list<string> the sections this caller manages */
    public static function managedSections(ApiCaller $caller): array
    {
        return array_values(array_filter(QtBook::SECTIONS, fn (string $s): bool => self::manages($caller, $s)));
    }

    /** @return list<string> the sections this caller works in at all */
    public static function sections(ApiCaller $caller): array
    {
        return array_values(array_filter(QtBook::SECTIONS, fn (string $s): bool => self::sees($caller, $s) || self::taps($caller, $s)));
    }

    /**
     * Where each job stands: each section's metrics, the complete pieces out
     * of cutting (size by size), the bundles given and the garment's panels.
     *
     * @param  list<string>  $jobIds
     * @return array<string, array{CUTTING: array<string, mixed>, SEWING: array<string, mixed>, QUALITY: array<string, mixed>, opening: array<string, int>, pieces: int, pieces_by_size: array<string, int>, bundles: int, bundle_qty: int, panels: int}>
     */
    public static function progress(string $companyId, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }
        $taps = DB::table('qt_entries')->where('company_id', $companyId)->whereIn('job_id', $jobIds)
            ->select('job_id', 'section', 'action', DB::raw('count(*) as n'))->groupBy('job_id', 'section', 'action')->get()->groupBy(fn ($r) => $r->job_id.'|'.$r->section);
        $bundles = DB::table('qt_cut_bundles')->where('company_id', $companyId)->whereIn('job_id', $jobIds)->get(['job_id', 'garment_type', 'size', 'qty'])->groupBy('job_id');
        $panels = DB::table('qt_panels')->where('company_id', $companyId)->where('active', true)->get(['id', 'garment_type', 'per_piece'])->groupBy('garment_type');
        $openings = DB::table('qt_jobs')->where('company_id', $companyId)->whereIn('id', $jobIds)->get(['id', 'opening_cut', 'opening_sewing', 'opening_quality'])->keyBy('id');
        $cut = $bundles->isEmpty() ? collect() : DB::table('qt_entries as e')->join('qt_cut_bundles as b', 'b.id', '=', 'e.cut_bundle_id')
            ->where('e.company_id', $companyId)->whereIn('b.job_id', $jobIds)->where('e.section', 'CUTTING')
            ->select('b.job_id', 'b.size', 'e.panel_id', 'e.action', DB::raw('count(*) as n'))->groupBy('b.job_id', 'b.size', 'e.panel_id', 'e.action')->get()
            ->groupBy(fn ($r) => $r->job_id.'|'.$r->size.'|'.$r->panel_id)->map(fn ($g) => self::count($g)['pass']);

        $out = [];
        foreach ($jobIds as $id) {
            $mine = $bundles->get($id, collect());
            $parts = $panels->get($mine->first()?->garment_type, collect());
            $bySize = [];
            foreach ($mine->pluck('size')->unique() as $size) {
                $bySize[$size] = $parts->isEmpty() ? 0 : (int) $parts->map(fn ($p) => intdiv($cut[$id.'|'.$size.'|'.$p->id] ?? 0, max(1, (int) $p->per_piece)))->min();
            }
            $o = $openings->get($id);
            $opening = ['CUTTING' => (int) $o?->opening_cut, 'SEWING' => (int) $o?->opening_sewing, 'QUALITY' => (int) $o?->opening_quality];
            // 'panels' = panels to check for one piece (a pair counts two).
            $row = ['opening' => $opening, 'pieces' => array_sum($bySize) + $opening['CUTTING'], 'pieces_by_size' => $bySize, 'bundles' => $mine->count(), 'bundle_qty' => (int) $mine->sum('qty'), 'panels' => (int) $parts->sum('per_piece')];
            foreach (QtBook::SECTIONS as $s) {
                $row[$s] = self::count($taps->get($id.'|'.$s, collect()));
            }
            $out[$id] = $row;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $p  a job's progress — its opening pieces included */
    public static function passed(array $p, string $section): int
    {
        return $section === 'CUTTING' ? $p['pieces'] : $p['opening'][$section] + $p[$section]['pass'];
    }

    /** Pieces the section before has passed and this one has not checked yet (null at cutting, where a job starts). */
    public static function waiting(array $p, string $section): ?int
    {
        return match ($section) {
            'SEWING' => max(0, $p['pieces'] - $p['opening']['SEWING'] - $p['SEWING']['checked']),
            'QUALITY' => max(0, self::passed($p, 'SEWING') - $p['opening']['QUALITY'] - $p['QUALITY']['checked']),
            default => null,
        };
    }

    public static function ready(string $companyId, string $jobId, string $section): ?int
    {
        return $section === 'CUTTING' ? null : self::waiting(self::progress($companyId, [$jobId])[$jobId], $section);
    }

    /**
     * A job from cutting to quality, and what waits between.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function flow(QtJob $job, array $p): array
    {
        return [
            'job_id' => $job->id, 'job_no' => $job->job_no, 'buyer' => $job->buyer_name, 'style' => $job->style, 'color' => $job->color, 'size' => $job->size,
            'order_qty' => $job->order_qty, 'due_date' => $job->due_date?->toDateString(), 'status' => $job->status, 'opening' => $p['opening'],
            'cutting' => [
                'bundles' => $p['bundles'], 'bundle_qty' => $p['bundle_qty'], 'panels_checked' => $p['CUTTING']['checked'], 'panels_pass' => $p['CUTTING']['pass'], 'panels_reject' => $p['CUTTING']['reject'],
                'pieces' => $p['pieces'], 'pieces_by_size' => $p['pieces_by_size'],
            ],
            'sewing' => ['checked' => $p['SEWING']['checked'], 'pass' => self::passed($p, 'SEWING'), 'reject' => $p['SEWING']['reject'], 'waiting' => self::waiting($p, 'SEWING')],
            'quality' => ['checked' => $p['QUALITY']['checked'], 'pass' => self::passed($p, 'QUALITY'), 'reject' => $p['QUALITY']['reject'], 'waiting' => self::waiting($p, 'QUALITY')],
            'done_pct' => $job->order_qty ? round(self::passed($p, 'QUALITY') / $job->order_qty * 100, 2) : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function count(Collection $g): array
    {
        return QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0);
    }
}
