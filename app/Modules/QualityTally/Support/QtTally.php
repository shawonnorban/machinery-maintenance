<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Support;

use App\Modules\QualityTally\Models\QtDefectType;
use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Models\QtEntryDefect;
use App\Modules\QualityTally\Models\QtInspector;
use App\Modules\QualityTally\Models\QtInspectorLine;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtLineTarget;
use App\Modules\QualityTally\Models\QtCutBundle;
use App\Modules\QualityTally\Models\QtPanel;
use App\Modules\QualityTally\Models\QtRefusedTap;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Recording, undoing and summing taps (Quality Tally, AnnotechERP 36). The
 * floor is the hardest place this system runs: tablets lose the wifi
 * mid-shift and an inspector taps hundreds of times an hour, so —
 *
 *  - every tap carries a client-made id; a replayed batch is idempotent because
 *    the database refuses the repeat, not because the tablet is careful;
 *  - `occurred_at` is when the tap happened, not when it arrived, and its
 *    production date and hour are fixed from it at write time;
 *  - defect tags are written with the tap in one transaction (a DEFECT tap with
 *    no tag would silently lower DHU);
 *  - only a QC given that line may tap on it — the owner included.
 */
final class QtTally
{
    /** A tap older than this when it arrives was made with no network. */
    public const LATE_MINUTES = 2;

    /**
     * Records one tap. Returns the entry and whether it was a repeat of one
     * already recorded (same client id).
     *
     * @param  array{section: string, line_id: string, job_id: string, action: string, occurred_at?: ?string, client_uuid?: ?string, defects?: list<string>}  $d
     * @return array{entry: QtEntry, duplicate: bool}
     */
    public static function record(string $companyId, string $userId, array $d): array
    {
        $uuid = $d['client_uuid'] ?? null;
        if ($uuid !== null && ($seen = QtEntry::query()->where('client_uuid', $uuid)->first()) !== null) {
            return ['entry' => $seen, 'duplicate' => true];
        }

        $inspector = self::assertAssigned($userId, $d['section'], $d['line_id']);
        $job = QtJob::query()->find($d['job_id']);
        if ($job === null || $job->status !== 'OPEN') {
            throw ValidationException::withMessages(['job_id' => __('qtally.v.job_open')]);
        }
        if (! in_array($d['action'], QtBook::ACTIONS, true)) {
            throw ValidationException::withMessages(['action' => __('qtally.v.action')]);
        }

        // Cutting (user, 2026-11-25): the QC scans a bundle card the cutting manager gave as input and taps
        // its panels — RFT, DEFECT, RECTIFY or REJECT. The bundle carries the job, lot, size and quantity.
        $cut = [];
        if ($d['section'] === 'CUTTING') {
            $bundle = empty($d['cut_bundle_id']) ? null : QtCutBundle::query()->find($d['cut_bundle_id']);
            if ($bundle === null) {
                throw ValidationException::withMessages(['cut_bundle_id' => __('qtally.v.bundle_scan')]);
            }
            // No table yet: the first to check the bundle takes it (below, with the tap).
            if ($bundle->line_id !== null && $bundle->line_id !== $d['line_id']) {
                throw ValidationException::withMessages(['cut_bundle_id' => __('qtally.v.bundle_other_table')]);
            }
            if ($bundle->job_id !== $d['job_id']) {
                throw ValidationException::withMessages(['job_id' => __('qtally.v.bundle_other_job')]);
            }
            $panelId = $bundle->panel_id ?: ($d['panel_id'] ?? null);
            $panel = $panelId ? QtPanel::query()->whereKey($panelId)->where('garment_type', $bundle->garment_type)->first() : null;
            if ($panel === null) {
                throw ValidationException::withMessages(['panel_id' => __('qtally.v.panel_needed_tap')]);
            }
            // No more new panels than the bundle holds — a paired part twice a piece (RFT and defect are first checks; rectify and reject are not).
            if (in_array($d['action'], ['RFT', 'DEFECT'], true)) {
                $done = QtEntry::query()->where('cut_bundle_id', $bundle->id)->where('panel_id', $panel->id)->whereIn('action', ['RFT', 'DEFECT'])->count();
                $holds = $bundle->qty * max(1, $panel->per_piece);
                if ($done >= $holds) {
                    throw ValidationException::withMessages(['panel_id' => __('qtally.v.bundle_done', ['n' => $holds])]);
                }
            }
            $cut = [
                'cut_bundle_id' => $bundle->id, 'panel_id' => $panel->id, 'garment_type' => $bundle->garment_type,
                'cutting_no' => $bundle->cutting_no !== '' ? $bundle->cutting_no : null, 'batch_no' => $bundle->batch_no !== '' ? $bundle->batch_no : null,
                'bundle_no' => $bundle->bundle_no, 'bundle_serial' => $bundle->serial_from !== null ? $bundle->serial_from.'-'.$bundle->serial_to : null,
                'bundle_qty' => $bundle->qty, 'size' => $bundle->size !== '' ? $bundle->size : null,
            ];
        }

        $defects = array_values(array_unique($d['defects'] ?? []));
        if ($d['action'] === 'DEFECT') {
            if ($defects === []) {
                throw ValidationException::withMessages(['defects' => __('qtally.v.defects_needed')]);
            }
            $found = QtDefectType::query()->whereIn('id', $defects)->where('section', $d['section'])->where('active', true)->count();
            if ($found !== count($defects)) {
                throw ValidationException::withMessages(['defects' => __('qtally.v.defects_section')]);
            }
        } elseif ($defects !== []) {
            throw ValidationException::withMessages(['defects' => __('qtally.v.defects_only_defect')]);
        }

        $now = QtBook::now();
        $at = isset($d['occurred_at']) && $d['occurred_at'] ? CarbonImmutable::parse($d['occurred_at'])->setTimezone(QtBook::TZ) : $now;

        // The shift HR gave this QC (roster, else standing shift) at the moment of the tap — a batch replayed next
        // morning still lands on the night that made it; without one, the shift named with the tap or the clock's.
        $shifts = QtBook::shifts($companyId);
        $shift = null;
        if (! empty($d['shift_id'])) {
            $shift = $shifts->get($d['shift_id']);
            if ($shift === null) {
                throw ValidationException::withMessages(['shift_id' => __('qtally.v.shift')]);
            }
        } elseif ($shifts->isNotEmpty()) {
            $shift = QtBook::shiftOf($inspector->hr_employee_id, $at, $shifts);
        }
        if ($at->greaterThan($now->addMinutes(5))) {
            throw ValidationException::withMessages(['occurred_at' => __('qtally.v.future')]);
        }
        if ($at->lessThan($now->subDays(QtBook::LATE_DAYS)->startOfDay())) {
            throw ValidationException::withMessages(['occurred_at' => __('qtally.v.too_old', ['days' => QtBook::LATE_DAYS])]);
        }

        // Sewing and quality have no input of their own: the line target the manager set for that day puts the job
        // on the line, and a garment is first checked only once the section before has passed it.
        // A tap made with no network (AnnotechERP 37 §৫.৬) may arrive after the target was changed or before
        // the section before's taps: the work was done on the floor, so it is kept and marked for the manager.
        $late = $at->lessThan($now->subMinutes(self::LATE_MINUTES));
        $review = null;
        if ($d['section'] !== 'CUTTING' && in_array($d['action'], ['RFT', 'DEFECT'], true)) {
            $day = QtBook::productionDate($at, $shift);
            if (! QtLineTarget::query()->whereDate('target_date', $day)->where('section', $d['section'])->where('line_id', $d['line_id'])->where('job_id', $d['job_id'])->exists()) {
                $late ? $review = 'NO_TARGET' : throw ValidationException::withMessages(['job_id' => __('qtally.v.no_target')]);
            } elseif (QtFlow::ready($companyId, $d['job_id'], $d['section']) <= 0) {
                $late ? $review = 'NONE_READY' : throw ValidationException::withMessages(['job_id' => __('qtally.v.none_ready', ['from' => __('qtally.section.'.($d['section'] === 'SEWING' ? 'CUTTING' : 'SEWING'))])]);
            }
        }

        try {
            $entry = DB::transaction(function () use ($companyId, $userId, $d, $inspector, $at, $late, $review, $uuid, $defects, $shift, $cut) {
                if (isset($cut['cut_bundle_id'])) {
                    QtCutBundle::query()->whereKey($cut['cut_bundle_id'])->whereNull('line_id')->update(['line_id' => $d['line_id']]);
                    // Two tables opening one bundle at the same moment: the first keeps it.
                    if (QtCutBundle::query()->whereKey($cut['cut_bundle_id'])->value('line_id') !== $d['line_id']) {
                        throw ValidationException::withMessages(['cut_bundle_id' => __('qtally.v.bundle_other_table')]);
                    }
                }
                $entry = QtEntry::query()->create($cut + [
                    'company_id' => $companyId, 'client_uuid' => $uuid, 'section' => $d['section'], 'line_id' => $d['line_id'], 'job_id' => $d['job_id'],
                    'inspector_id' => $inspector->id, 'user_id' => $userId, 'shift_id' => $shift?->id, 'action' => $d['action'],
                    // The column keeps the app's clock; the production day and hour are the factory's.
                    'occurred_at' => $at->setTimezone((string) config('app.timezone')),
                    'production_date' => QtBook::productionDate($at, $shift), 'hour' => (int) $at->format('G'),
                    'synced_late' => $late, 'review' => $review,
                    'phone_id' => $d['phone_id'] ?? null, 'session_id' => $d['session_id'] ?? null,
                ]);
                foreach ($defects as $defect) {
                    QtEntryDefect::query()->create(['company_id' => $companyId, 'entry_id' => $entry->id, 'defect_type_id' => $defect]);
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            // Two copies of one offline tap raced each other; the first one counts.
            return ['entry' => QtEntry::query()->where('client_uuid', $uuid)->firstOrFail(), 'duplicate' => true];
        }

        return ['entry' => $entry, 'duplicate' => false];
    }

    /**
     * A tap refused for good, kept with its reason (AnnotechERP 37 §৩ 3a) — once
     * per tap however often it is sent. One without its own id cannot be told
     * from the next, so it is not kept.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function refused(string $companyId, array $raw, string $message, ?string $userId, ?string $phoneId = null, ?string $sessionId = null): void
    {
        $uuid = $raw['client_uuid'] ?? null;
        if (! is_string($uuid) || ! Str::isUuid($uuid) || QtEntry::query()->where('client_uuid', $uuid)->exists()) {
            return;
        }
        $ulid = fn ($v) => is_string($v) && strlen($v) === 26 ? $v : null;
        $at = null;
        try {
            $at = is_string($raw['occurred_at'] ?? null) ? CarbonImmutable::parse($raw['occurred_at'])->setTimezone((string) config('app.timezone')) : null;
        } catch (\Throwable) {
            // Not a time: kept without one.
        }
        QtRefusedTap::query()->updateOrCreate(['company_id' => $companyId, 'client_uuid' => $uuid], [
            'phone_id' => $phoneId, 'session_id' => $sessionId, 'user_id' => $userId,
            'section' => in_array($raw['section'] ?? null, QtBook::SECTIONS, true) ? $raw['section'] : null,
            'line_id' => $ulid($raw['line_id'] ?? null), 'job_id' => $ulid($raw['job_id'] ?? null),
            'action' => in_array($raw['action'] ?? null, QtBook::ACTIONS, true) ? $raw['action'] : null,
            'occurred_at' => $at, 'message' => mb_substr($message, 0, 2000),
            'payload' => array_intersect_key($raw, array_flip(['section', 'line_id', 'job_id', 'action', 'occurred_at', 'client_uuid', 'session', 'cut_bundle_id', 'panel_id', 'defects'])),
        ]);
    }

    /** Removes the user's own latest tap on a line and job — today's only. */
    public static function undo(string $companyId, string $userId, string $section, string $lineId, string $jobId): ?QtEntry
    {
        $today = self::currentDate($companyId, $userId);

        return DB::transaction(function () use ($userId, $section, $lineId, $jobId, $today) {
            $entry = QtEntry::query()->where('user_id', $userId)->where('section', $section)->where('line_id', $lineId)->where('job_id', $jobId)
                ->whereDate('production_date', $today)
                ->orderByDesc('occurred_at')->orderByDesc('id')->lockForUpdate()->first();
            $entry?->delete();
            // The last tap on a bundle taken back: it is any table's again.
            if ($entry?->cut_bundle_id && ! QtEntry::query()->where('cut_bundle_id', $entry->cut_bundle_id)->exists()) {
                QtCutBundle::query()->whereKey($entry->cut_bundle_id)->update(['line_id' => null]);
            }

            return $entry;
        });
    }

    /** Today's production date for a QC (their HR shift): last night's day while its shift and overtime last, else today. */
    public static function currentDate(string $companyId, ?string $userId = null): string
    {
        $shifts = QtBook::shifts($companyId);
        $now = QtBook::now();
        $employee = $userId === null ? null : QtInspector::query()->where('user_id', $userId)->value('hr_employee_id');
        $shift = QtBook::shiftOf($employee, $now, $shifts);

        return QtBook::productionDate($now, $shift);
    }

    /**
     * @param  callable(): \Illuminate\Database\Eloquent\Builder  $base
     * @param  array{cutting_no?: ?string, bundle_no?: ?string}  $bundle
     * @return array<string, mixed>
     */
    private static function cuttingCounts(callable $base, array $bundle): array
    {
        $lot = fn () => $base()->whereNotNull('panel_id')->when(($bundle['cutting_no'] ?? '') !== '', fn ($q) => $q->where('cutting_no', $bundle['cutting_no']), fn ($q) => $q->whereNull('cutting_no'));
        $lotCounts = $lot()->select('action', DB::raw('count(*) as n'))->groupBy('action')->pluck('n', 'action');
        // A bundle's own counts are of every day it was checked on (a bundle may wait over a night): the
        // limit of first checks the server keeps is of every day too, so the screen must not show less.
        $panels = ($bundle['cut_bundle_id'] ?? '') !== '' ? QtEntry::query()->where('section', 'CUTTING')->where('cut_bundle_id', $bundle['cut_bundle_id'])
            ->select('panel_id', 'action', DB::raw('count(*) as n'))->groupBy('panel_id', 'action')->get()->groupBy('panel_id')
            ->map(fn ($g) => self::panelCount($g)) : (($bundle['bundle_no'] ?? '') === '' ? collect() : $lot()->where('bundle_no', $bundle['bundle_no'])
            ->select('panel_id', 'action', DB::raw('count(*) as n'))->groupBy('panel_id', 'action')->get()->groupBy('panel_id')
            ->map(fn ($g) => self::panelCount($g)));

        return [
            'lot_checked' => (int) (($lotCounts['RFT'] ?? 0) + ($lotCounts['DEFECT'] ?? 0)), 'lot_reject' => (int) ($lotCounts['REJECT'] ?? 0),
            'lot_pass' => QtBook::metrics($lotCounts->map(fn ($n) => (int) $n)->all(), 0)['pass'],
            'bundles' => (int) $lot()->distinct()->count('bundle_no'),
            'panels' => $panels->all(),
        ];
    }

    /** A panel's counts on a bundle: first checks (RFT + defect), pass (capped), rectified and rejected. */
    private static function panelCount($g): array
    {
        $m = QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0);

        return ['checked' => $m['checked'], 'pass' => $m['pass'], 'defect' => $m['defect'], 'rectify' => $m['rectify'], 'reject' => $m['reject']];
    }

    public static function assertAssigned(string $userId, string $section, string $lineId): QtInspector
    {
        $inspector = QtInspector::query()->where('user_id', $userId)->where('active', true)->first();
        if ($inspector === null) {
            throw ValidationException::withMessages(['line_id' => __('qtally.v.not_inspector')]);
        }
        $given = QtInspectorLine::query()->where('inspector_id', $inspector->id)->where('section', $section)->where('line_id', $lineId)->exists();
        if (! $given) {
            throw ValidationException::withMessages(['line_id' => __('qtally.v.not_your_line')]);
        }

        return $inspector;
    }

    /**
     * One line and job on one day: the metrics, the target, each hour, the
     * top defects and (when a user is named) that user's own taps.
     *
     * @return array<string, mixed>
     */
    public static function summary(string $section, string $lineId, string $jobId, string $date, ?string $userId = null, ?array $bundle = null): array
    {
        $base = fn () => QtEntry::query()->where('section', $section)->where('line_id', $lineId)->where('job_id', $jobId)->whereDate('production_date', $date);
        $counts = $base()->select('action', DB::raw('count(*) as n'))->groupBy('action')->pluck('n', 'action')->map(fn ($n) => (int) $n)->all();
        $tagRows = QtEntryDefect::query()->join('qt_entries as e', 'e.id', '=', 'qt_entry_defects.entry_id')->join('qt_defect_types as t', 't.id', '=', 'qt_entry_defects.defect_type_id')
            ->where('e.section', $section)->where('e.line_id', $lineId)->where('e.job_id', $jobId)->whereDate('e.production_date', $date)
            ->select('t.id', 't.code', 't.name', 't.name_bn', DB::raw('count(*) as n'))->groupBy('t.id', 't.code', 't.name', 't.name_bn')->orderByDesc('n')->get();
        $target = QtLineTarget::query()->whereDate('target_date', $date)->where('section', $section)->where('line_id', $lineId)->where('job_id', $jobId)->first();
        $m = QtBook::metrics($counts, (int) $tagRows->sum('n'), $target?->target_qty);

        $hours = $base()->select('hour', 'action', DB::raw('count(*) as n'))->groupBy('hour', 'action')->get()->groupBy('hour');
        $tagsByHour = QtEntryDefect::query()->join('qt_entries as e', 'e.id', '=', 'qt_entry_defects.entry_id')
            ->where('e.section', $section)->where('e.line_id', $lineId)->where('e.job_id', $jobId)->whereDate('e.production_date', $date)
            ->select('e.hour', DB::raw('count(*) as n'))->groupBy('e.hour')->pluck('n', 'e.hour');
        $hourly = $hours->map(fn ($g, $h) => ['hour' => (int) $h] + QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), (int) ($tagsByHour[$h] ?? 0)))->sortKeys()->values()->all();

        $job = QtJob::query()->find($jobId);
        $manMinutes = $job ? QtBook::manMinutes($job->company_id, $date, $section, $lineId, $target?->manpower, $target ? (float) $target->working_hours : null) : null;

        return $m + [
            'date' => $date,
            'efficiency' => QtBook::efficiency($m['pass'], $job?->smv === null ? null : (float) $job->smv, $manMinutes),
            'hourly_target' => $target && (float) $target->working_hours > 0 ? (int) round($target->target_qty / (float) $target->working_hours) : null,
            'working_hours' => $target ? (float) $target->working_hours : null,
            'hourly' => $hourly,
            'top_defects' => $tagRows->take(5)->map(fn ($t) => ['id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'name_bn' => $t->name_bn, 'count' => (int) $t->n])->values()->all(),
            'mine' => $userId ? $base()->where('user_id', $userId)->count() : null,
            // Cutting: the lot's panels checked and rejected, and each panel of the bundle being checked.
            'cutting' => $section === 'CUTTING' && $bundle !== null ? self::cuttingCounts($base, $bundle) : null,
            // Sewing / quality: what the section before has passed and nobody has checked here yet.
            'ready' => $section !== 'CUTTING' && $job ? QtFlow::ready($job->company_id, $jobId, $section) : null,
        ];
    }
}
