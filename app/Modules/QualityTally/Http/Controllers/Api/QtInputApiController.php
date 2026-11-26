<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\QualityTally\Http\Controllers\Concerns\GuardsSections;
use App\Modules\QualityTally\Models\QtCutBundle;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtPanel;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtFlow;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cutting Input — the bundle cards the cutting department made after cutting,
 * scanned or typed by the cutting manager; the table that first checks one
 * takes it (user, 2026-11-25: no table is picked here) —
 * and Job Flow, a job from cutting to quality. Sewing and quality have no
 * input of their own: their manager's line target is it (user, 2026-11-25).
 */
class QtInputApiController extends ApiController
{
    use GuardsSections;

    /* ── Cutting Input ──────────────────────────────────────────────── */

    public function bundles(Request $request): JsonResponse
    {
        $this->allowViewOrTap('CUTTING');
        $q = $request->validate(['date' => ['nullable', 'date'], 'line_id' => ['nullable', 'string', 'size:26'], 'job_id' => ['nullable', 'string', 'size:26'], 'search' => ['nullable', 'string', 'max:40']]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? QtBook::today())->toDateString();
        $rows = QtCutBundle::query()
            ->when($q['search'] ?? null, fn ($w, $v) => $w->where(fn ($x) => $x->where('barcode', 'ilike', "%{$v}%")->orWhere('bundle_no', 'ilike', "%{$v}%")), fn ($w) => ($q['line_id'] ?? null) && ! ($q['date'] ?? null)
                // The tablet's own list: the table's bundles of the last week (a bundle may wait over a night).
                ? $w->whereDate('input_date', '>=', QtBook::now()->subDays(6)->toDateString())
                : $w->whereDate('input_date', $date))
            // A table's list: its own bundles, and those no table has opened yet.
            ->when($q['line_id'] ?? null, fn ($w, $v) => $w->where(fn ($x) => $x->where('line_id', $v)->orWhereNull('line_id')))->when($q['job_id'] ?? null, fn ($w, $v) => $w->where('job_id', $v))
            ->orderByDesc('created_at')->limit(500)->get();
        $taps = DB::table('qt_entries')->where('company_id', $c)->whereIn('cut_bundle_id', $rows->pluck('id'))
            ->select('cut_bundle_id', 'action', DB::raw('count(*) as n'))->groupBy('cut_bundle_id', 'action')->get()->groupBy('cut_bundle_id');
        $jobs = QtJob::query()->whereIn('id', $rows->pluck('job_id'))->get()->keyBy('id');
        $panels = QtPanel::query()->whereIn('id', $rows->pluck('panel_id')->filter())->pluck('name', 'id');
        $lines = QtBook::lines($c, 'CUTTING');

        return ApiResponse::ok([
            'date' => $date,
            'rows' => $rows->map(function (QtCutBundle $b) use ($taps, $jobs, $panels, $lines) {
                $m = QtBook::metrics(($taps[$b->id] ?? collect())->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0);
                $job = $jobs->get($b->job_id);

                return $this->bundle($b) + [
                    'job_no' => $job?->job_no, 'style' => $job?->style, 'color' => $job?->color, 'line' => $b->line_id ? (QtBook::label($lines->get($b->line_id)) ?? '—') : null,
                    'panel' => $b->panel_id ? ($panels[$b->panel_id] ?? '—') : null,
                    'checked' => $m['checked'], 'pass' => $m['pass'], 'reject' => $m['reject'], 'tapped' => $taps->has($b->id),
                ];
            })->all(),
        ]);
    }

    /**
     * A scanned bundle card: already given as input here, or a card of the
     * cutting department (Cutting Floor), with the job it belongs to
     * (matchJob) and the panel it names.
     */
    public function scan(Request $request): JsonResponse
    {
        $this->allowViewOrTap('CUTTING');
        $code = strtoupper(trim((string) $request->validate(['code' => ['required', 'string', 'max:40']])['code']));
        $plain = str_replace('-', '', $code);
        $c = $this->caller()->companyId;

        $mine = QtCutBundle::query()->where(fn ($w) => $w->where('barcode', $plain)->orWhere('bundle_no', $code))->first();
        if ($mine) {
            $job = QtJob::query()->find($mine->job_id);

            return ApiResponse::ok(['found' => 'INPUT', 'bundle' => $this->bundle($mine) + ['job_no' => $job?->job_no, 'style' => $job?->style, 'color' => $job?->color,
                'panel' => $mine->panel_id ? QtPanel::query()->whereKey($mine->panel_id)->value('name') : null, 'line' => $mine->line_id ? QtBook::label(QtBook::lines($c, 'CUTTING')->get($mine->line_id)) : null]]);
        }
        if (Schema::hasTable('cut_bundles')) {
            $card = DB::table('cut_bundles')->where('company_id', $c)->where('status', '<>', 'CANCELLED')
                ->where(fn ($w) => $w->whereRaw("replace(bundle_no, '-', '') = ?", [$plain])->orWhere('bundle_no', $code))->first();
            if ($card) {
                [$job, $by, $choices] = $this->matchJob($card);
                // Cutting Floor says "Front Panel" where the panel list says "Front Part" or "Front"; the same
                // name sits in several garments, so the job's garment decides where it is known.
                $part = fn (string $name): string => trim((string) preg_replace('/\b(panel|part|parts)\b/u', '', mb_strtolower($name)));
                $garment = $job ? QtCutBundle::query()->where('job_id', $job->id)->value('garment_type') : null;
                $buyer = $card->buyer_id ? DB::table('buyers')->where('id', $card->buyer_id)->value('name') : null;
                $matches = QtPanel::query()->where('active', true)->when($garment, fn ($w, $g) => $w->where('garment_type', $g))->get()
                    ->filter(fn (QtPanel $x) => $part($x->name) === $part((string) $card->parts_name));
                $panel = $matches->pluck('garment_type')->unique()->count() === 1 ? $matches->first() : null;

                return ApiResponse::ok(['found' => 'CARD', 'card' => [
                    'barcode' => $plain, 'bundle_no' => $card->bundle_no, 'buyer' => $buyer, 'garment' => $card->garment, 'style' => $card->style, 'color' => $card->colour, 'po_no' => $card->po_no, 'size' => $card->size,
                    'serial_from' => (int) $card->serial_from, 'serial_to' => (int) $card->serial_to, 'qty' => (int) $card->qty, 'cutting_no' => (string) $card->cutting_no,
                    'batch_no' => $card->batch_no, 'part' => $card->parts_name, 'source_id' => $card->id,
                    'job_id' => $job?->id, 'match' => $by, 'job_choices' => $choices, 'garment_type' => $garment ?? $panel?->garment_type, 'panel_id' => $panel?->id,
                ]]);
            }
        }

        return ApiResponse::ok(['found' => null]);
    }

    /**
     * Which open job a bundle card belongs to. The card carries no Job No, so
     * the surest sign first: a card of the same order and colour was already
     * given to a job; else the job of its style + colour + PO; else the only
     * job of its style + colour (when their POs do not contradict). Upper and
     * lower case and spaces do not matter. Two equally good jobs are not
     * guessed between: the manager picks, and the next card of that order and
     * colour follows.
     *
     * @return array{0: ?QtJob, 1: ?string, 2: list<string>} the job, how it was found, the jobs of the style to pick from
     */
    private function matchJob(object $card): array
    {
        $norm = fn (?string $v): string => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $v)));
        $style = QtJob::query()->where('status', 'OPEN')->orderBy('job_no')->get()->filter(fn (QtJob $j) => $norm($j->style) === $norm($card->style))->values();
        $choices = $style->pluck('id')->all();

        $given = DB::table('qt_cut_bundles as q')->join('cut_bundles as c', 'c.id', '=', 'q.source_id')->join('qt_jobs as j', 'j.id', '=', 'q.job_id')
            ->where('q.company_id', $card->company_id)->where('c.order_id', $card->order_id)->where('c.colour', $card->colour)->where('j.status', 'OPEN')->orderByDesc('q.created_at');
        $known = (clone $given)->where('c.size', $card->size)->value('q.job_id') ?? $given->value('q.job_id');
        if ($known && ($job = QtJob::query()->find($known))) {
            return [$job, 'LOT', $choices];
        }

        // A job of one size is that size's only; a job of no size, or of several ("S-XL"), takes every size.
        $colour = $style->filter(fn (QtJob $j) => $norm($j->color) === $norm($card->colour));
        $only = function ($jobs) use ($norm, $card): ?QtJob {
            $size = $jobs->filter(fn (QtJob $j) => $norm($j->size) === $norm($card->size));
            $jobs = $size->isNotEmpty() ? $size : $jobs;

            return $jobs->count() === 1 ? $jobs->first() : null;
        };
        $samePo = $colour->filter(fn (QtJob $j) => $norm($j->po_no) !== '' && $norm($j->po_no) === $norm($card->po_no));
        if ($samePo->isNotEmpty()) {
            $job = $only($samePo);

            return [$job, $job ? 'PO' : null, $choices];
        }
        if ($job = $only($colour->filter(fn (QtJob $j) => $norm($j->po_no) === '' || $norm($card->po_no) === ''))) {
            return [$job, 'STYLE', $choices];
        }

        return [null, null, $choices];
    }

    public function saveBundle(Request $request): JsonResponse
    {
        $c = $this->caller()->companyId;
        $this->allowManage('CUTTING');
        $request->merge(['barcode' => str_replace('-', '', strtoupper(trim((string) $request->input('barcode', $request->input('bundle_no', '')))))]);
        $d = $request->validate([
            'barcode' => ['required', 'string', 'max:40', Rule::unique('qt_cut_bundles', 'barcode')->where('company_id', $c)],
            'bundle_no' => ['required', 'string', 'max:40'],
            'job_id' => ['required', 'string', 'size:26'],
            'line_id' => ['nullable', 'string', 'size:26'],
            'garment_type' => ['required', 'string', 'max:40'],
            'panel_id' => ['nullable', 'string', 'size:26'],
            'size' => ['nullable', 'string', 'max:32'],
            'serial_from' => ['nullable', 'integer', 'min:0'],
            'serial_to' => ['nullable', 'integer', 'min:0', 'gte:serial_from'],
            'qty' => ['required', 'integer', 'min:1', 'max:99999'],
            'cutting_no' => ['nullable', 'string', 'max:40'],
            'batch_no' => ['nullable', 'string', 'max:40'],
            'input_date' => ['nullable', 'date'],
            'source_id' => ['nullable', 'string', 'size:26'],
        ], ['barcode.unique' => __('qtally.v.bundle_given')], __('qtally.attrs'));
        if (($d['line_id'] ?? null) && ! QtBook::lines($c, 'CUTTING')->has($d['line_id'])) {
            throw ValidationException::withMessages(['line_id' => __('qtally.v.line')]);
        }
        $job = QtJob::query()->find($d['job_id']);
        if ($job === null || $job->status !== 'OPEN') {
            throw ValidationException::withMessages(['job_id' => __('qtally.v.job_open')]);
        }
        if (($d['panel_id'] ?? null) && ! QtPanel::query()->whereKey($d['panel_id'])->where('garment_type', $d['garment_type'])->exists()) {
            throw ValidationException::withMessages(['panel_id' => __('qtally.v.panel_type', ['row' => 1])]);
        }
        if (! QtPanel::query()->where('garment_type', $d['garment_type'])->where('active', true)->exists()) {
            throw ValidationException::withMessages(['garment_type' => __('qtally.cpi.no_panels')]);
        }
        // One job is one garment: its complete pieces are counted across that garment's panels.
        $garment = QtCutBundle::query()->where('job_id', $d['job_id'])->value('garment_type');
        if ($garment !== null && $garment !== $d['garment_type']) {
            throw ValidationException::withMessages(['garment_type' => __('qtally.v.job_garment', ['type' => $garment])]);
        }
        $saved = QtCutBundle::query()->create([
            'barcode' => $d['barcode'], 'bundle_no' => trim($d['bundle_no']), 'job_id' => $d['job_id'], 'line_id' => $d['line_id'] ?? null, 'garment_type' => $d['garment_type'],
            'panel_id' => $d['panel_id'] ?? null, 'size' => trim((string) ($d['size'] ?? '')), 'serial_from' => $d['serial_from'] ?? null, 'serial_to' => $d['serial_to'] ?? null,
            'qty' => $d['qty'], 'cutting_no' => trim((string) ($d['cutting_no'] ?? '')), 'batch_no' => trim((string) ($d['batch_no'] ?? '')),
            'input_date' => $d['input_date'] ?? QtBook::today(), 'source' => ($d['source_id'] ?? null) ? 'SCAN' : 'MANUAL', 'source_id' => $d['source_id'] ?? null,
            'created_by' => $this->caller()->auditUserId(),
        ]);

        return ApiResponse::created(['id' => $saved->id, 'barcode' => $saved->barcode]);
    }

    public function deleteBundle(string $id): JsonResponse
    {
        $this->allowManage('CUTTING');
        $row = QtCutBundle::query()->findOrFail($id);
        if (DB::table('qt_entries')->where('cut_bundle_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['id' => __('qtally.v.bundle_tapped')]);
        }
        $row->delete();

        return ApiResponse::ok(['id' => $id]);
    }

    /* ── Job Flow ───────────────────────────────────────────────────── */

    public function flow(Request $request): JsonResponse
    {
        $this->allowView(null);
        $q = $request->validate(['status' => ['nullable', Rule::in(['OPEN', 'CLOSED'])], 'search' => ['nullable', 'string', 'max:60']]);
        $c = $this->caller()->companyId;
        $jobs = QtJob::query()->when($q['status'] ?? 'OPEN', fn ($w, $s) => $w->where('status', $s))
            ->when($q['search'] ?? null, fn ($w, $v) => $w->where(fn ($x) => $x->where('job_no', 'ilike', "%{$v}%")->orWhere('style', 'ilike', "%{$v}%")->orWhere('buyer_name', 'ilike', "%{$v}%")))
            ->orderBy('due_date')->orderBy('job_no')->limit(200)->get();

        $progress = QtFlow::progress($c, $jobs->pluck('id')->all());

        return ApiResponse::ok($jobs->map(fn (QtJob $j) => QtFlow::flow($j, $progress[$j->id]))->all());
    }

    /** @return array<string, mixed> */
    private function bundle(QtCutBundle $b): array
    {
        return [
            'id' => $b->id, 'barcode' => $b->barcode, 'bundle_no' => $b->bundle_no, 'job_id' => $b->job_id, 'line_id' => $b->line_id, 'garment_type' => $b->garment_type,
            'panel_id' => $b->panel_id, 'size' => $b->size, 'serial_from' => $b->serial_from, 'serial_to' => $b->serial_to, 'qty' => $b->qty,
            'serial' => $b->serial_from !== null ? $b->serial_from.'-'.$b->serial_to : null, 'cutting_no' => $b->cutting_no, 'batch_no' => $b->batch_no,
            'input_date' => $b->input_date->toDateString(), 'source' => $b->source,
        ];
    }
}
