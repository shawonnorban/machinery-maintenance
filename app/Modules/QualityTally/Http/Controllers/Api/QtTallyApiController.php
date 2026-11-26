<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\QualityTally\Http\Controllers\Concerns\GuardsSections;
use App\Modules\QualityTally\Models\QtDefectType;
use App\Modules\QualityTally\Models\QtInspector;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtLineTarget;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtFlow;
use App\Modules\QualityTally\Support\QtTally;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quality Tally → Tally Entry (AnnotechERP 36): the QC's tablet. What this QC
 * may tap on (their lines, today's jobs with targets, each section's
 * defects), one tap, a replayed offline batch, undo, and the running total.
 */
class QtTallyApiController extends ApiController
{
    use GuardsSections;

    private const TAP = 'qtally.tally.create';

    public function context(): JsonResponse
    {
        $tapped = array_values(array_filter(QtBook::SECTIONS, fn (string $x): bool => QtFlow::taps($this->caller(), $x)));
        if ($tapped === []) {
            $this->allow(self::TAP);
        }

        return ApiResponse::ok(self::contextFor($this->caller()->companyId, (string) $this->caller()->auditUserId(), $tapped));
    }

    /**
     * What a QC may tap on: their lines (of the sections they tap in; only
     * `$onlyLine` when the phone is tied to one), today's open jobs and
     * targets, each garment's panels and each section's defects.
     *
     * @param  list<string>  $tapped
     * @param  array{section: string, line_id: string}|null  $onlyLine
     * @return array<string, mixed>
     */
    public static function contextFor(string $companyId, string $userId, array $tapped, ?array $onlyLine = null): array
    {
        $inspector = QtInspector::query()->with('lines')->where('user_id', $userId)->first();
        $all = QtBook::allLines($companyId);

        $lines = $inspector && $inspector->active
            ? $inspector->lines->map(fn ($l) => ['section' => $l->section, 'line_id' => $l->line_id, 'label' => QtBook::label($all->get($l->section.':'.$l->line_id))])
                ->filter(fn ($l) => $l['label'] !== null && in_array($l['section'], $tapped, true))
                ->filter(fn ($l) => $onlyLine === null || ($l['section'] === $onlyLine['section'] && $l['line_id'] === $onlyLine['line_id']))
                ->sortBy(fn ($l) => $l['section'].'|'.$l['label'])->values()
            : collect();

        // The QC's own day: HR gives the shift, the tablet does not ask for one.
        $today = QtTally::currentDate($companyId, $userId);
        $targets = QtLineTarget::query()->whereDate('target_date', $today)->get();
        $jobs = QtJob::query()->where('status', 'OPEN')->orderBy('job_no')->get();

        return [
            'today' => $today,
            'inspector' => $inspector ? ['id' => $inspector->id, 'active' => $inspector->active] : null,
            'lines' => $lines->all(),
            'jobs' => $jobs->map(fn (QtJob $j) => [
                'id' => $j->id, 'job_no' => $j->job_no, 'buyer_name' => $j->buyer_name, 'style' => $j->style, 'color' => $j->color, 'po_no' => $j->po_no, 'order_qty' => $j->order_qty,
            ])->values()->all(),
            // "SECTION:line" => [{job_id, target_qty, hourly_target}] — sewing and quality are tapped on these jobs only: the target is their input.
            'targets' => $targets->groupBy(fn ($t) => $t->section.':'.$t->line_id)->map(fn ($g) => $g->map(fn (QtLineTarget $t) => [
                'job_id' => $t->job_id, 'target_qty' => $t->target_qty,
                'hourly_target' => (float) $t->working_hours > 0 ? (int) round($t->target_qty / (float) $t->working_hours) : null,
            ])->values()->all())->all(),
            'garment_types' => \App\Modules\QualityTally\Models\QtPanel::query()->where('active', true)->orderBy('garment_type')->orderBy('sort_order')->orderBy('name')->get()
                ->groupBy('garment_type')->map(fn ($g, $type) => ['name' => $type, 'panels' => $g->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'code' => $x->code, 'per_piece' => $x->per_piece])->values()->all()])->values()->all(),
            'defects' => QtDefectType::query()->where('active', true)->orderBy('sort_order')->orderBy('code')->get()
                ->groupBy('section')->map(fn ($g) => $g->map(fn (QtDefectType $d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name, 'name_bn' => $d->name_bn])->values()->all())->all(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $this->allowTap((string) $request->input('section'));
        $d = self::tap($request->all());
        $userId = (string) $this->caller()->auditUserId();
        $res = QtTally::record($this->caller()->companyId, $userId, $d);
        $e = $res['entry'];

        return ApiResponse::ok([
            'id' => $e->id, 'duplicate' => $res['duplicate'],
            'summary' => QtTally::summary($e->section, $e->line_id, $e->job_id, $e->production_date->toDateString(), $userId, $e->section === 'CUTTING' ? ['cutting_no' => $e->cutting_no, 'bundle_no' => $e->bundle_no, 'cut_bundle_id' => $e->cut_bundle_id] : null),
        ], status: $res['duplicate'] ? 200 : 201);
    }

    /**
     * A tablet's offline queue, replayed. Each tap stands alone: a repeat is
     * skipped, a refused one (job closed since, line taken away) is reported
     * and dropped — it would be refused again on every retry.
     */
    public function bulk(Request $request): JsonResponse
    {
        $tapped = array_values(array_filter(QtBook::SECTIONS, fn (string $x): bool => QtFlow::taps($this->caller(), $x)));
        if ($tapped === []) {
            $this->allow(self::TAP);
        }
        $request->validate(['entries' => ['required', 'array', 'max:500']]);
        $userId = (string) $this->caller()->auditUserId();
        $out = ['recorded' => 0, 'duplicate' => 0, 'rejected' => []];
        foreach ($request->input('entries') as $i => $raw) {
            try {
                $d = self::tap(is_array($raw) ? $raw : []);
                if (! in_array($d['section'], $tapped, true)) {
                    throw ValidationException::withMessages(['section' => __('qtally.v.not_your_line')]);
                }
                $res = QtTally::record($this->caller()->companyId, $userId, $d);
                $res['duplicate'] ? $out['duplicate']++ : $out['recorded']++;
            } catch (ValidationException $e) {
                $message = (string) collect($e->errors())->flatten()->first();
                $out['rejected'][] = ['index' => $i, 'client_uuid' => is_array($raw) ? ($raw['client_uuid'] ?? null) : null, 'message' => $message];
                QtTally::refused($this->caller()->companyId, is_array($raw) ? $raw : [], $message, $userId);
            }
        }

        return ApiResponse::ok($out);
    }

    public function undo(Request $request): JsonResponse
    {
        $this->allowTap((string) $request->input('section'));
        $d = $request->validate([
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'job_id' => ['required', 'string', 'size:26'],
        ]);
        $userId = (string) $this->caller()->auditUserId();
        $entry = QtTally::undo($this->caller()->companyId, $userId, $d['section'], $d['line_id'], $d['job_id']);
        if ($entry === null) {
            throw ValidationException::withMessages(['action' => __('qtally.v.nothing_to_undo')]);
        }

        return ApiResponse::ok([
            'undone' => ['id' => $entry->id, 'action' => $entry->action],
            'summary' => QtTally::summary($d['section'], $d['line_id'], $d['job_id'], QtTally::currentDate($this->caller()->companyId, $userId), $userId),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate(['section' => ['required', Rule::in(QtBook::SECTIONS)]]);
        $this->allowViewOrTap((string) $request->input('section'));
        $d = $request->validate([
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'job_id' => ['required', 'string', 'size:26'],
            'date' => ['nullable', 'date'],
        ]);
        $date = $d['date'] ?? QtTally::currentDate($this->caller()->companyId, (string) $this->caller()->auditUserId());

        $cb = $d['section'] === 'CUTTING' && $request->filled('cut_bundle_id') ? \App\Modules\QualityTally\Models\QtCutBundle::query()->find($request->input('cut_bundle_id')) : null;
        $bundle = $d['section'] === 'CUTTING' ? ($cb ? ['cutting_no' => $cb->cutting_no !== '' ? $cb->cutting_no : '', 'bundle_no' => $cb->bundle_no, 'cut_bundle_id' => $cb->id] : ['cutting_no' => trim((string) $request->input('cutting_no', '')), 'bundle_no' => trim((string) $request->input('bundle_no', ''))]) : null;

        return ApiResponse::ok(QtTally::summary($d['section'], $d['line_id'], $d['job_id'], $date, (string) $this->caller()->auditUserId(), $bundle));
    }

    /** One tap, from a browser or a phone. @return array<string, mixed> */
    public static function tap(array $input): array
    {
        return validator($input, [
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'job_id' => ['required', 'string', 'size:26'],
            'action' => ['required', Rule::in(QtBook::ACTIONS)],
            'occurred_at' => ['nullable', 'date'],
            'client_uuid' => ['nullable', 'uuid'],
            'shift_id' => ['nullable', 'string', 'size:26'],
            // A cutting tap's panel, lot and bundle.
            'panel_id' => ['nullable', 'string', 'size:26'],
            'cut_bundle_id' => ['nullable', 'string', 'size:26'],
            'garment_type' => ['nullable', 'string', 'max:40'],
            'cutting_no' => ['nullable', 'string', 'max:40'],
            'batch_no' => ['nullable', 'string', 'max:40'],
            'cut_qty' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'bundle_no' => ['nullable', 'string', 'max:20'],
            'bundle_serial' => ['nullable', 'string', 'max:30'],
            'bundle_qty' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'size' => ['nullable', 'string', 'max:20'],
            'defects' => ['sometimes', 'array', 'max:10'],
            'defects.*' => ['string', 'size:26'],
        ], [], __('qtally.attrs'))->validate();
    }
}
