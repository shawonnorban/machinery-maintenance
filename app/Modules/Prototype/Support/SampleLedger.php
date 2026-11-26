<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Support;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Models\SampleDaySheet;
use App\Modules\Prototype\Models\SampleDaySheetLine;
use App\Modules\Prototype\Models\SampleEvent;
use Illuminate\Support\Facades\DB;

/**
 * The Prototype Desk's one place to write down what happened: a sample's
 * timeline event (with the lifecycle stage and status it left the sample
 * at, so a change reads as From → To in the audit trail), and the desk's
 * alerts — raised once while open, resolved when the work they ask for is
 * done. Titles and summaries are stored as an action and its parameters and
 * worded when read, in the reader's language.
 */
final class SampleLedger
{
    /** Who is acting: the signed-in user, or nobody for the desk's own sweeps. @return array{0: ?string, 1: ?string} id, name */
    private static function actor(): array
    {
        try {
            $user = app(ApiCaller::class)->user;
        } catch (\Throwable) {
            $user = null;
        }

        return [$user?->id, $user?->name];
    }

    /** A timeline event on a sample (or on the desk, with no sample). */
    public static function log(Sample|string|null $sample, string $action, array $params = [], ?string $docNo = null): void
    {
        $sample = is_string($sample) ? Sample::query()->find($sample) : $sample?->fresh();
        $life = $sample ? SampleLifecycle::one($sample) : null;
        [$by, $name] = self::actor();
        SampleEvent::query()->create([
            'sample_id' => $sample?->id, 'action' => $action, 'params' => $params, 'doc_no' => $docNo,
            'stage' => $life['stage'] ?? null, 'status' => $life['status'] ?? null, 'by_user' => $by, 'by_name' => $name, 'happened_at' => now(),
        ]);
    }

    /** The last thing written down against a document. */
    public static function lastAction(string $docNo): ?string
    {
        return SampleEvent::query()->where('doc_no', $docNo)->orderByDesc('happened_at')->orderByDesc('id')->value('action');
    }

    /** Raise an alert — once: an open one of the same kind for the same document is left as it is. */
    public static function raise(string $kind, string $severity, Sample|string|null $sample, string $desk, ?string $docNo, array $params = []): void
    {
        $sampleId = $sample instanceof Sample ? $sample->id : $sample;
        $open = SampleAlert::query()->where('kind', $kind)->whereNull('resolved_at')
            ->when($docNo, fn ($q) => $q->where('doc_no', $docNo), fn ($q) => $q->where('sample_id', $sampleId))->exists();
        if (! $open) {
            SampleAlert::query()->create(['kind' => $kind, 'severity' => $severity, 'sample_id' => $sampleId, 'for_desk' => $desk, 'doc_no' => $docNo, 'params' => $params, 'raised_at' => now()]);
        }
    }

    /** Resolve the open alerts of these kinds for a document — or, with no document, for the sample. */
    public static function resolve(array|string $kinds, ?string $docNo, Sample|string|null $sample = null): void
    {
        $sampleId = $sample instanceof Sample ? $sample->id : $sample;
        [$by] = self::actor();
        SampleAlert::query()->whereIn('kind', (array) $kinds)->whereNull('resolved_at')
            ->when($docNo, fn ($q) => $q->where('doc_no', $docNo), fn ($q) => $q->where('sample_id', $sampleId))
            ->update(['resolved_at' => now(), 'resolved_by' => $by]);
    }

    /**
     * The desk's own sweep, run when its alerts or dashboard are read and by
     * the scheduler: open samples past their required date are flagged
     * overdue (once), and a past day sheet short of its target raises Target
     * Missed. A completed or cancelled sample's overdue alert is resolved.
     */
    public static function sweep(): void
    {
        DB::transaction(function (): void {
            $samples = Sample::query()->where('state', '!=', 'CANCELLED')->get();
            foreach (SampleLifecycle::of($samples) as $id => $life) {
                $sample = $samples->firstWhere('id', $id);
                if ($life['overdue']) {
                    if (! SampleAlert::query()->where('kind', 'SAMPLE_OVERDUE')->where('doc_no', $sample->sample_no)->exists()) {
                        self::raise('SAMPLE_OVERDUE', 'CRITICAL', $sample, 'SAMPLE_ROOM', $sample->sample_no, ['sample' => $sample->sample_no, 'required' => $life['required_date'], 'days' => $life['days_late']]);
                        self::log($sample, 'sample_overdue', ['required' => $life['required_date']]);
                    }
                } elseif (! $life['open']) {
                    self::resolve('SAMPLE_OVERDUE', $sample->sample_no);
                }
            }
            $sheets = SampleDaySheet::query()->where('target_date', '<', now()->toDateString())->get();
            $lines = SampleDaySheetLine::query()->whereIn('day_sheet_id', $sheets->pluck('id'))->get()->groupBy('day_sheet_id');
            foreach ($sheets as $sheet) {
                $list = $lines[$sheet->id] ?? collect();
                $target = (int) $list->sum('target');
                $actual = (int) $list->sum(fn ($l) => (int) $l->actual);
                $doc = 'TGT-'.$sheet->target_date->toDateString();
                if ($target > 0 && $actual < $target && ! SampleAlert::query()->where('kind', 'TARGET_MISSED')->where('doc_no', $doc)->exists()) {
                    self::raise('TARGET_MISSED', 'CRITICAL', null, 'DESK', $doc, ['date' => $sheet->target_date->toDateString(), 'actual' => $actual, 'target' => $target, 'pct' => round($actual / $target * 100, 1)]);
                }
            }
        });
    }
}
