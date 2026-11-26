<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Http\Controllers\Api;

use App\Modules\DevSample\Models\DevSample;
use App\Modules\DevSample\Models\DsChange;
use App\Modules\DevSample\Models\DsColumn;
use App\Modules\DevSample\Support\DevSampleOptions;
use App\Modules\DevSample\Support\DevSampleReport;
use App\Modules\DevSample\Support\DsSheet;
use App\Modules\Merchandise\Models\Buyer;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Formula\FormulaSet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\JobNo\JobNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dev Sample Status (AnnotechERP 31, from Norban; Column Setup 2026-11-21):
 * Order Summary's sheet on the development samples. The sheet's make-up for
 * this user, the samples (filtered by any column like an Excel header
 * filter) with Norban's summary cards and the totals row, every write — new
 * samples, the changed ones saved together with the version each was read
 * at, Copy and Delete —, the image, and the Pivot Report.
 *
 * Whoever holds `devsample.view_all` reads every sample; anyone else only
 * the samples they entered (Norban's team scope).
 */
class DevSampleApiController extends ApiController
{
    public const PER_PAGE = [25, 50, 100, 200, 500];

    /* ── Meta ───────────────────────────────────────────────────────── */

    /** What the Pivot Report's filters pick from. */
    public function lookups(): JsonResponse
    {
        $this->allow('devsample.view');

        return ApiResponse::ok([
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->orderBy('name')->get(['id', 'code', 'short_name', 'name'])
                ->map(fn (Buyer $b): array => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->values()->all(),
            'seasons' => DevSampleOptions::lookupOptions('SEASON'),
            'lists' => collect(DevSampleOptions::lists())->map(fn ($codes, $list) => DevSampleOptions::labelled($list))->all(),
        ]);
    }

    /**
     * The Job No dialog (Dev Sample Status): `?factory=NCL` gives this year's prefix
     * (26NCL), the next serial over every module and the serials this sheet
     * already has; no factory gives every factory at once (loaded as the sheet opens).
     */
    public function jobNumbers(Request $request): JsonResponse
    {
        $this->allow('devsample.view');

        return ApiResponse::ok(JobNumber::answer('ds_samples', $this->caller()->companyId, DsSheet::today(), $request->query('factory')));
    }

    public function meta(): JsonResponse
    {
        $this->allowEither('devsample.view', 'devsample.create');
        $sheet = $this->sheet();
        $visible = $sheet->visible();
        $sources = [];
        foreach ($visible as $c) {
            if ($c->source && ! isset($sources[$c->source])) {
                $sources[$c->source] = $sheet->options($c->source);
            }
        }

        return ApiResponse::ok([
            'today' => DsSheet::today(),
            'groups' => $sheet->groups->filter(fn ($g) => $g->active && $visible->contains('group_id', $g->id))
                ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'tone' => $g->tone])->values()->all(),
            'columns' => $visible->map(fn (DsColumn $c) => self::columnBody($c, $sheet) + ['access' => $sheet->access($c)])->values()->all(),
            'sources' => $sources,
            'per_page' => self::PER_PAGE,
            'can' => [
                'create' => $this->caller()->can('devsample.create'), 'update' => $this->caller()->can('devsample.update'),
                'delete' => $this->caller()->can('devsample.delete'), 'columns' => $this->caller()->can('devsample.columns.manage'),
            ],
        ]);
    }

    /** A column as the sheet uses it; formulas in their stored form (the browser works them out live). */
    public static function columnBody(DsColumn $c, DsSheet $sheet): array
    {
        return [
            'key' => $c->key, 'label' => $c->label, 'group_id' => $c->group_id, 'type' => $c->type, 'source' => $c->source, 'multiple' => $c->multiple,
            'prefix' => $c->prefix, 'suffix' => $c->suffix, 'decimals' => $c->decimals, 'width' => $c->width, 'vertical' => $c->vertical, 'frozen' => $c->frozen,
            'required' => $c->required, 'formula' => $c->formula, 'volatile' => $sheet->formulas->isVolatile($c->key),
            'color_rules' => $c->color_rules ?? [], 'text_tone' => $c->text_tone, 'bold' => (bool) $c->bold, 'kpi' => $c->kpi, 'pair_reduce' => $c->pair_reduce, 'is_system' => $c->is_system, 'hidden' => $c->hidden,
        ];
    }

    /* ── List ───────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $this->allow('devsample.view');
        $sheet = $this->sheet();
        $request->validate([
            'per_page' => ['nullable', Rule::in([...array_map('strval', self::PER_PAGE), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:120'],
        ]);
        $query = $this->filtered($request, $sheet);
        $total = (clone $query)->count();
        $all = $request->input('per_page') === 'all';
        $perPage = $all ? max($total, 1) : (int) $request->input('per_page', 50);
        $page = (clone $query)->with(['creator:id,name', 'updater:id,name'])->orderByDesc('serial_no')
            ->paginate($perPage, ['*'], 'page', (int) $request->input('page', 1));

        return ApiResponse::ok([
            'rows' => collect($page->items())->map(fn (DevSample $s) => $this->row($s, $sheet))->all(),
            'page' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'per_page' => $all ? 'all' : $perPage, 'total' => $total, 'all_total' => $this->visible()->count()],
            'totals' => $this->totals(clone $query, $sheet),
            'stats' => $this->stats(clone $query),
        ]);
    }

    /**
     * Dev Sample Status → Dashboard (user, 2026-11-21, laid out like Order
     * Summary's): Norban's summary cards over every sample this user reads,
     * the samples still to submit by days left to the Submission Deadline,
     * the samples by status, buyers and requested months, and the latest saves.
     */
    public function dashboard(): JsonResponse
    {
        $this->allow('devsample.view');
        $label = fn (string $list, string $code) => DevSampleOptions::label($list, $code);
        $pendingLabel = $label('sample_status', 'SUBMISSION_PENDING');
        $today = Carbon::parse(DsSheet::today());
        $rows = $this->visible()->get(['id', 'values'])->map(fn (DevSample $s) => $s->values ?? []);
        $num = fn (array $v, string $k, string $reduce = 'FIRST') => is_array($v[$k] ?? null) ? FormulaSet::pairsNumber($v[$k], $reduce) : (is_numeric($v[$k] ?? null) ? (float) $v[$k] : null);
        $value = fn (array $v) => ($num($v, 'possible_qty') ?? 0) * ($num($v, 'fob_price') ?? 0);
        $bucket = function (array $v) use ($today): string {
            $d = $v['submission_deadline'] ?? null;
            if (! is_string($d) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
                return 'none';
            }
            $days = (int) $today->diffInDays(Carbon::parse(substr($d, 0, 10)), false);

            return $days < 0 ? 'overdue' : ($days <= 7 ? 'week' : ($days <= 30 ? 'month' : 'later'));
        };
        $pending = $rows->filter(fn ($v) => ($v['sample_status'] ?? null) === $pendingLabel);
        $statuses = array_map(fn ($c) => $label('sample_status', $c), DevSampleOptions::SAMPLE_STATUSES);
        $byStatus = $rows->groupBy(fn ($v) => (string) ($v['sample_status'] ?? ''))->map->count();
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok([
            'stats' => $this->stats($this->visible()),
            'deadline' => collect(['overdue', 'week', 'month', 'later', 'none'])->mapWithKeys(fn ($b) => [$b => $pending->filter(fn ($v) => $bucket($v) === $b)->count()]),
            'by_status' => collect($statuses)->merge($byStatus->keys()->reject(fn ($s) => in_array($s, $statuses, true)))
                ->map(fn ($s) => ['status' => $s, 'samples' => (int) ($byStatus[$s] ?? 0)])->filter(fn ($r) => $r['samples'] > 0 || $r['status'] !== '')->values(),
            'by_buyer' => $rows->groupBy(fn ($v) => (string) ($v['buyer'] ?? ''))->map(fn ($g, $b) => [
                'buyer' => $b, 'samples' => $g->count(), 'qty' => (int) round($g->sum(fn ($v) => $num($v, 'sample_qty') ?? 0)),
                'value' => round($g->sum($value), 2), 'pending' => $g->filter(fn ($v) => ($v['sample_status'] ?? null) === $pendingLabel)->count(),
            ])->sortByDesc('samples')->values(),
            'by_month' => $rows->groupBy(fn ($v) => substr((string) ($v['buyer_requested_date'] ?? ''), 0, 7))->map(fn ($g, $m) => [
                'month' => $m, 'samples' => $g->count(), 'qty' => (int) round($g->sum(fn ($v) => $num($v, 'sample_qty') ?? 0)),
            ])->sortKeys()->values(),
            'recent' => DsChange::query()->whereIn('sample_id', $this->visible()->withTrashed()->select('id'))->where('kind', '!=', 'RECALC')
                ->orderByDesc('changed_at')->limit(300)->get()->groupBy('batch')->take(8)->map(fn ($g) => [
                    'at' => $g->first()->changed_at?->toIso8601String(), 'kind' => $g->first()->kind, 'by' => $users[$g->first()->changed_by] ?? null,
                    'samples' => $g->pluck('sample_id')->unique()->count(), 'cells' => $g->whereNotNull('column_key')->count(),
                ])->values(),
        ]);
    }

    /** Distinct values of one column under the other filters — a header filter's list. */
    public function distinct(Request $request): JsonResponse
    {
        $this->allow('devsample.view');
        $sheet = $this->sheet();
        $key = (string) $request->input('column');
        $c = $sheet->column($key);
        if (! $c || $sheet->access($c) === 'none' || $sheet->formulas->isVolatile($key) || $c->type === 'image') {
            return ApiResponse::ok([]);
        }
        $query = $this->filtered($request, $sheet, except: $key);
        if ($key === 'serial_no') {
            $rows = $query->selectRaw('serial_no::text as v, count(*) as n')->groupBy('serial_no')->orderBy('serial_no')->limit(2000)->get();
        } elseif (in_array($c->type, ['multi', 'pairs'], true)) {
            // Each value of a list column (each line's name of a pairs column) counts for its row; a row with none is a blank.
            $counts = [];
            foreach ($query->toBase()->selectRaw('"values"->? as v', [$key])->limit(50000)->pluck('v') as $json) {
                $list = json_decode((string) $json, true);
                if ($c->type === 'pairs' && is_array($list)) {
                    $list = array_values(array_filter(array_map(fn ($l) => is_array($l) ? trim((string) ($l['label'] ?? '')) : '', $list), fn ($v) => $v !== ''));
                }
                foreach (is_array($list) && $list !== [] ? array_unique(array_map('strval', $list)) : [''] as $v) {
                    $counts[$v] = ($counts[$v] ?? 0) + 1;
                }
            }
            ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
            $rows = collect($counts)->map(fn ($n, $v) => (object) ['v' => $v, 'n' => $n])->values();
        } else {
            [$expr, $bind] = $this->cellText($key);
            $rows = $query->selectRaw("coalesce({$expr}, '') as v, count(*) as n", $bind)->groupByRaw('1')->orderByRaw('1')->limit(2000)->get();
        }

        return ApiResponse::ok($rows->map(fn ($r) => ['value' => (string) $r->v, 'count' => (int) $r->n])->all());
    }

    /* ── Write ──────────────────────────────────────────────────────── */

    /** New samples (Add Dev Sample). Body: rows[] of {values}; each gets the company's next serial number. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('devsample.create');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:200'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $created = DB::transaction(function () use ($request, $sheet, $batch, $by): array {
            // The company's running number, taken with the company row locked so two saves never share one.
            DB::table('companies')->where('id', $sheet->companyId)->lockForUpdate()->value('id');
            $next = (int) DevSample::withTrashed()->where('company_id', $sheet->companyId)->max('serial_no');
            $out = [];
            foreach ($request->input('rows') as $i => $row) {
                [$values, $diff] = $sheet->put([], (array) $row['values'], "rows.{$i}.values");
                $this->checkRequired($sheet, $values, "rows.{$i}.values", $i + 1);
                $s = DevSample::query()->create(['serial_no' => ++$next, 'buyer_id' => $sheet->buyerId($values), 'values' => $values, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
                $sheet->log($s, 'CREATE', $diff, $batch, $by);
                $out[] = $s;
            }

            return $out;
        });

        return ApiResponse::created(['rows' => array_map(fn (DevSample $s) => $this->row($s->fresh(['creator:id,name', 'updater:id,name']), $sheet), $created)]);
    }

    /**
     * The changed samples, saved together. Body: rows[] of {id, version, values}
     * (changed cells only). A sample someone else saved since it was read comes
     * back under `conflicts`; the others are saved.
     */
    public function update(Request $request): JsonResponse
    {
        $this->allow('devsample.update');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*.id' => ['required', 'string', 'size:26'], 'rows.*.version' => ['required', 'integer'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        [$saved, $conflicts] = DB::transaction(function () use ($request, $sheet, $batch, $by): array {
            $saved = [];
            $conflicts = [];
            foreach ($request->input('rows') as $i => $row) {
                $s = $this->visible()->whereKey($row['id'])->lockForUpdate()->first();
                if (! $s) {
                    throw ValidationException::withMessages(["rows.{$i}.id" => __('devsample.v.row_gone')]);
                }
                if ((int) $row['version'] !== $s->version) {
                    $conflicts[] = $this->row($s->load(['creator:id,name', 'updater:id,name']), $sheet);

                    continue;
                }
                [$values, $diff] = $sheet->put($s->values ?? [], (array) $row['values'], "rows.{$i}.values");
                if ($diff === []) {
                    $saved[] = $s;

                    continue;
                }
                $this->checkRequired($sheet, $values, "rows.{$i}.values", $i + 1, $s->serial_no);
                $s->forceFill(['values' => $values, 'buyer_id' => $sheet->buyerId($values), 'version' => $s->version + 1, 'updated_by' => $by])->save();
                $sheet->log($s, 'EDIT', $diff, $batch, $by);
                $saved[] = $s;
            }

            return [$saved, $conflicts];
        });

        return ApiResponse::ok(['rows' => array_map(fn (DevSample $s) => $this->row($s->fresh(['creator:id,name', 'updater:id,name']), $sheet), $saved), 'conflicts' => $conflicts]);
    }

    /** Copy: each ticked sample again, under the next serial numbers. */
    public function copy(Request $request): JsonResponse
    {
        $this->allow('devsample.create');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $copies = DB::transaction(function () use ($request, $sheet, $batch, $by): array {
            DB::table('companies')->where('id', $sheet->companyId)->lockForUpdate()->value('id');
            $next = (int) DevSample::withTrashed()->where('company_id', $sheet->companyId)->max('serial_no');
            $out = [];
            foreach ($this->visible()->whereKey($request->input('ids'))->orderBy('serial_no')->get() as $src) {
                $values = $sheet->formulas->apply($src->values ?? [], DsSheet::today());
                $s = DevSample::query()->create(['serial_no' => ++$next, 'buyer_id' => $src->buyer_id, 'values' => $values, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
                $sheet->log($s, 'CREATE', $sheet->diff([], $values), $batch, $by);
                $out[] = $s;
            }

            return $out;
        });

        return ApiResponse::created(['rows' => array_map(fn (DevSample $s) => $this->row($s->fresh(['creator:id,name', 'updater:id,name']), $sheet), $copies)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->allow('devsample.delete');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $n = DB::transaction(function () use ($request, $sheet, $batch, $by): int {
            $n = 0;
            foreach ($this->visible()->whereKey($request->input('ids'))->get() as $s) {
                $sheet->log($s, 'DELETE', [], $batch, $by);
                $s->forceFill(['updated_by' => $by])->save();
                $s->delete();
                $n++;
            }

            return $n;
        });

        return ApiResponse::ok(['deleted' => $n]);
    }

    public function image(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allowEither('devsample.create', 'devsample.update');
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096']], [], ['file' => __('devsample.f.image')]);
        $file = $files->handle($request->file('file'), 'ds_sample_image', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'url' => "/api/files/{$file->id}/download"]);
    }

    /* ── Pivot Report ───────────────────────────────────────────────── */

    public function report(Request $request): JsonResponse
    {
        $this->allow('devsample.report');
        $f = $request->validate([
            'season' => ['nullable', 'string', 'max:64'],
            'buyer' => ['nullable', 'string', 'size:26'],
            'levels' => ['nullable', 'array'],
            'levels.*' => [Rule::in(DevSampleReport::LEVELS)],
        ]);
        $query = $this->visible();
        if (! empty($f['season'])) {
            $query->whereRaw('"values"->>\'season\' = ?', [$f['season']]);
        }
        if (! empty($f['buyer'])) {
            $query->where('buyer_id', $f['buyer']);
        }
        // The report counts by Norban's codes; the sheet keeps the labels.
        $code = fn (string $list, mixed $label) => $label === null ? null : (array_column(DevSampleOptions::labelled($list), 'value', 'label')[$label] ?? $label);
        $rows = $query->get(['id', 'values'])->map(function (DevSample $s) use ($code): array {
            $v = $s->values ?? [];

            return [
                'buyer' => $v['buyer'] ?? null,
                'order_confirmation' => $code('order_confirmation', $v['order_confirmation'] ?? null),
                'sample_status' => $code('sample_status', $v['sample_status'] ?? null),
                'sample_type' => $v['sample_type'] ?? null,
                'job_no' => is_array($v['job_no'] ?? null) ? (implode(', ', $v['job_no']) ?: null) : ($v['job_no'] ?? null),
                'styling' => $v['styling'] ?? null,
                'items' => (array) ($v['items'] ?? []),
                // Each colour line's name.
                'colours' => array_values(array_filter(array_map(fn ($l) => ['name' => is_array($l) ? trim((string) ($l['label'] ?? '')) : ''], (array) ($v['colours'] ?? [])), fn ($c) => $c['name'] !== '')),
                'fabrication' => $v['fabrication'] ?? null,
            ];
        });

        return ApiResponse::ok([
            'summary' => DevSampleReport::summary($rows),
            'detail' => DevSampleReport::detail($rows, $f['levels'] ?? []),
            'levels' => array_values(array_intersect(DevSampleReport::LEVELS, $f['levels'] ?? [])),
        ]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private function sheet(): DsSheet
    {
        return new DsSheet($this->caller()->companyId, $this->caller()->user);
    }

    /** The samples this caller may read: everyone's with view_all, else their own. */
    private function visible(): Builder
    {
        $query = DevSample::query();
        if (! $this->caller()->can('devsample.view_all')) {
            $query->where('created_by', $this->caller()->auditUserId());
        }

        return $query;
    }

    /** A sample lacking a required column is not saved: "Row 2 (#12): Buyer … are needed". */
    private function checkRequired(DsSheet $sheet, array $values, string $field, int $n, ?int $serial = null): void
    {
        $missing = $sheet->missing($values);
        if ($missing !== []) {
            throw ValidationException::withMessages([$field => __('devsample.v.missing', ['row' => $n, 'serial' => $serial ? "#{$serial}" : '—', 'columns' => implode(', ', $missing)])]);
        }
    }

    /** @return array<string, mixed> */
    private function row(DevSample $s, DsSheet $sheet): array
    {
        return [
            'id' => $s->id, 'serial_no' => $s->serial_no, 'version' => $s->version, 'values' => $sheet->read($s),
            'created_by' => $s->creator?->name, 'updated_by' => $s->updater?->name ?? $s->creator?->name, 'updated_at' => $s->updated_at?->toIso8601String(),
            'artwork_url' => isset($s->values['artwork']) ? "/api/files/{$s->values['artwork']}/download" : null,
        ];
    }

    /**
     * A cell as SQL text for the header filters: a stored cell is its "values" entry;
     * Last Update Date is the row's own updated_at (stored UTC) as its Dhaka day.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function cellText(string $key): array
    {
        return $key === 'last_update'
            ? ["to_char((updated_at at time zone 'UTC') at time zone 'Asia/Dhaka', 'YYYY-MM-DD')", []]
            : ['"values"->>?', [$key]];
    }

    /** The visible samples narrowed by `q`, `f[key][]` header filters and `r[key][from|to]` ranges; `$except` leaves one column's filter out. */
    private function filtered(Request $request, DsSheet $sheet, ?string $except = null): Builder
    {
        $query = $this->visible();
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereRaw('LOWER("values"::text) LIKE ?', ['%'.mb_strtolower(addcslashes($search, '\\%_')).'%']);
        }
        foreach ((array) $request->input('f', []) as $key => $list) {
            $key = (string) $key;
            $c = $sheet->column($key);
            if ($key === $except || (! $c && $key !== 'serial_no') || ($c && $sheet->formulas->isVolatile($key))) {
                continue;
            }
            $list = array_map('strval', (array) $list);
            $blank = in_array('', $list, true);
            $vals = array_values(array_filter($list, fn ($v) => $v !== ''));
            $query->where(function ($q) use ($key, $c, $vals, $blank): void {
                if ($key === 'serial_no') {
                    $q->whereIn('serial_no', array_map('intval', $vals));

                    return;
                }
                $quoted = '{'.implode(',', array_map(fn ($v) => '"'.addcslashes($v, '"\\').'"', $vals)).'}';
                [$expr, $bind] = $this->cellText($key);
                if ($vals !== []) {
                    match ($c->type) {
                        'multi' => $q->orWhereRaw('jsonb_exists_any(case when jsonb_typeof("values"->?) = \'array\' then "values"->? else \'[]\'::jsonb end, ?::text[])', [$key, $key, $quoted]),
                        // A pairs cell holding a line of that name.
                        'pairs' => $q->orWhereRaw('exists (select 1 from jsonb_array_elements(case when jsonb_typeof("values"->?) = \'array\' then "values"->? else \'[]\'::jsonb end) l where l->>\'label\' = any(?::text[]))', [$key, $key, $quoted]),
                        default => $q->orWhereRaw("{$expr} = any(?::text[])", [...$bind, $quoted]),
                    };
                }
                if ($blank) {
                    in_array($c->type, ['multi', 'pairs'], true)
                        ? $q->orWhereRaw('coalesce(jsonb_array_length(case when jsonb_typeof("values"->?) = \'array\' then "values"->? end), 0) = 0', [$key, $key])
                        : $q->orWhereRaw("coalesce({$expr}, '') = ''", $bind);
                }
            });
        }
        foreach ((array) $request->input('r', []) as $key => $range) {
            $c = $sheet->column((string) $key);
            if (! $c || $key === $except || $sheet->formulas->isVolatile((string) $key)) {
                continue;
            }
            $numeric = in_array($c->type, FormulaSet::NUMBER_TYPES, true);
            [$expr, $bind] = $this->cellText((string) $key);
            foreach (['from' => '>=', 'to' => '<='] as $side => $op) {
                $v = $range[$side] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                $numeric
                    ? $query->whereRaw("nullif({$expr}, '')::numeric {$op} ?", [...$bind, (float) $v])
                    : $query->whereRaw("{$expr} {$op} ?", [...$bind, (string) $v]);
            }
        }

        return $query;
    }

    /** @return array<string, float|null> SUM / AVG of each total column over every filtered sample */
    private function totals(Builder $query, DsSheet $sheet): array
    {
        $cols = $sheet->columns->filter(fn (DsColumn $c) => in_array($c->kpi, ['SUM', 'AVG'], true) && in_array($c->type, FormulaSet::NUMBER_TYPES, true)
            && $sheet->access($c) !== 'none' && ! $sheet->formulas->isVolatile($c->key))->values();
        if ($cols->isEmpty()) {
            return [];
        }
        $select = [];
        $bindings = [];
        foreach ($cols as $i => $c) {
            $select[] = ($c->kpi === 'AVG' ? 'avg' : 'sum')."(case when jsonb_typeof(\"values\"->?) = 'number' then (\"values\"->>?)::numeric end) as t{$i}";
            array_push($bindings, $c->key, $c->key);
        }
        $row = (array) $query->toBase()->selectRaw(implode(', ', $select), $bindings)->reorder()->first();
        $out = [];
        foreach ($cols as $i => $c) {
            $v = $row["t{$i}"] ?? null;
            $out[$c->key] = $v === null ? null : round((float) $v, 6);
        }

        return $out;
    }

    /**
     * Norban's summary cards over the filtered samples. Average prices are
     * weighted by possible quantity (a plain average of the priced samples
     * when none has a quantity); FOB value = Σ possible qty × FOB.
     *
     * @return array<string, int|float>
     */
    private function stats(Builder $query): array
    {
        // A number cell, or a pairs cell's first line (a price's summary level).
        $num = fn (string $k) => "(case jsonb_typeof(\"values\"->'{$k}') when 'number' then (\"values\"->>'{$k}')::numeric"
            ." when 'array' then (case when jsonb_typeof(\"values\"->'{$k}'->0->'value') = 'number' then (\"values\"->'{$k}'->0->>'value')::numeric end) end)";
        $label = fn (string $list, string $code) => DevSampleOptions::label($list, $code);
        $in = fn (array $labels) => implode(',', array_map(fn ($l) => DB::getPdo()->quote($l), $labels));
        $status = '"values"->>\'sample_status\'';
        $confirm = '"values"->>\'order_confirmation\'';
        [$qty, $target, $fob] = [$num('possible_qty'), $num('target_price'), $num('fob_price')];
        $a = (array) $query->toBase()->reorder()->selectRaw(implode(', ', [
            'count(*) as total_records',
            "coalesce(sum({$num('sample_qty')}), 0) as total_sample_qty",
            "coalesce(sum({$qty}), 0) as total_possible_qty",
            "sum(case when {$status} = {$in([$label('sample_status', 'SUBMISSION_PENDING')])} then 1 else 0 end) as submission_pending",
            "sum(case when {$status} = {$in([$label('sample_status', 'SUBMITTED')])} then 1 else 0 end) as submitted",
            "sum(case when {$confirm} in ({$in(array_map(fn ($c) => $label('order_confirmation', $c), DevSampleOptions::DROPPED))}) then 1 else 0 end) as order_dropped",
            "sum(case when {$confirm} = {$in([$label('order_confirmation', 'ORDER_CONFIRMED')])} then 1 else 0 end) as order_confirmed",
            "sum(case when {$target} > 0 and {$qty} > 0 then {$qty} * {$target} end) as target_value",
            "sum(case when {$target} > 0 and {$qty} > 0 then {$qty} end) as target_qty",
            "avg(case when {$target} > 0 then {$target} end) as target_avg",
            "sum(case when {$fob} > 0 and {$qty} > 0 then {$qty} * {$fob} end) as fob_value",
            "sum(case when {$fob} > 0 and {$qty} > 0 then {$qty} end) as fob_qty",
            "avg(case when {$fob} > 0 then {$fob} end) as fob_avg",
        ]))->first();
        $avg = fn (string $k) => (float) ($a["{$k}_qty"] ?? 0) > 0 ? (float) $a["{$k}_value"] / (float) $a["{$k}_qty"] : (float) ($a["{$k}_avg"] ?? 0);

        return [
            'total_records' => (int) ($a['total_records'] ?? 0),
            'submission_pending' => (int) ($a['submission_pending'] ?? 0),
            'submitted' => (int) ($a['submitted'] ?? 0),
            'order_dropped' => (int) ($a['order_dropped'] ?? 0),
            'order_confirmed' => (int) ($a['order_confirmed'] ?? 0),
            'total_sample_qty' => (int) round((float) ($a['total_sample_qty'] ?? 0)),
            'total_possible_qty' => (int) round((float) ($a['total_possible_qty'] ?? 0)),
            'avg_target_price' => round($avg('target'), 4),
            'avg_fob_price' => round($avg('fob'), 4),
            'total_fob_value' => round((float) ($a['fob_value'] ?? 0), 2),
        ];
    }
}
