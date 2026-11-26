<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Support;

use App\Modules\DevSample\Models\DevSample;
use App\Modules\DevSample\Models\DsChange;
use App\Modules\Erp\Support\SheetNotices;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Shared\Formula\Formula;
use Carbon\Carbon;

/**
 * Dev Sample Status's notifications (user, 2026-11-21).
 *
 *  - DS_SAMPLES_CHANGED: samples created, changed or deleted — told to the
 *    samples' authors and to the buyer's merchandisers and their heads who
 *    read every sample (`devsample.view_all`), one notification per save.
 *  - DS_DEADLINE_DUE: each morning, to each sample's author, the samples
 *    still to submit whose Submission Deadline has passed or comes within 2
 *    days (critical when one has passed).
 */
final class SampleNotices
{
    public const LIST = '/dev-sample-status/list';

    public static function batch(string $batch): void
    {
        $changes = DsChange::query()->where('batch', $batch)->where('kind', '!=', 'RECALC')->get(['sample_id', 'kind', 'column_key', 'changed_by']);
        if ($changes->isEmpty()) {
            return;
        }
        $actor = $changes->first()->changed_by;
        $kinds = $changes->pluck('kind')->unique();
        $kind = $kinds->contains('DELETE') ? 'DELETE' : ($kinds->contains('CREATE') ? 'CREATE' : 'EDIT');
        $samples = DevSample::withTrashed()->whereKey($changes->pluck('sample_id')->unique())->get(['id', 'serial_no', 'buyer_id', 'created_by', 'values']);
        $viewAll = SheetNotices::holders('devsample.view_all')->flip();
        $view = SheetNotices::holders('devsample.view')->flip();

        $mine = [];
        foreach ($samples as $s) {
            // The author reads their own; anyone else only with view_all.
            $people = collect($s->buyer_id ? OrderScope::recipientsFor((string) $s->buyer_id) : [])->filter(fn ($u) => isset($viewAll[$u]));
            if ($s->created_by && isset($view[$s->created_by])) {
                $people->push($s->created_by);
            }
            foreach ($people->unique() as $u) {
                $mine[$u][] = $s;
            }
        }
        $by = SheetNotices::name($actor);
        $cells = $changes->whereNotNull('column_key')->groupBy('sample_id')->map->count();
        SheetNotices::send(array_keys($mine), 'DS_SAMPLES_CHANGED', function (string $locale, string $u) use ($mine, $by, $kind, $cells) {
            $list = collect($mine[$u]);

            return [
                'by' => $by, 'action' => __("notification.sheet.kind.{$kind}", [], $locale), 'n' => $list->count(),
                'samples' => SheetNotices::list($list->map(fn (DevSample $s) => '#'.$s->serial_no.(($st = Formula::text($s->values['styling'] ?? '')) !== '' ? " {$st}" : ''))),
                'cells' => (int) $list->sum(fn (DevSample $s) => $cells[$s->id] ?? 0),
            ];
        }, self::LIST, 'INFO', $actor);
    }

    /** The morning's deadlines for one company (its context set). */
    public static function deadlines(): int
    {
        $pending = DevSampleOptions::label('sample_status', 'SUBMISSION_PENDING');
        $today = Carbon::parse(DsSheet::today());
        $rows = [];
        foreach (DevSample::query()->whereNotNull('created_by')->whereRaw('"values"->>\'sample_status\' = ?', [$pending])->get(['id', 'serial_no', 'created_by', 'values']) as $s) {
            $d = $s->values['submission_deadline'] ?? null;
            if (! is_string($d) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
                continue;
            }
            $days = (int) $today->diffInDays(Carbon::parse(substr($d, 0, 10)), false);
            if ($days > 2) {
                continue;
            }
            $rows[$s->created_by][$days < 0 ? 'overdue' : 'soon'][] = '#'.$s->serial_no;
        }
        $view = SheetNotices::holders('devsample.view')->flip();
        $sent = 0;
        foreach ($rows as $u => $r) {
            if (! isset($view[$u])) {
                continue;
            }
            $sent += SheetNotices::send([$u], 'DS_DEADLINE_DUE', fn () => [
                'overdue' => count($r['overdue'] ?? []), 'soon' => count($r['soon'] ?? []), 'samples' => SheetNotices::list(array_merge($r['overdue'] ?? [], $r['soon'] ?? [])),
            ], self::LIST.'?f[sample_status][]='.rawurlencode($pending), empty($r['overdue']) ? 'WARNING' : 'CRITICAL');
        }

        return $sent;
    }
}
