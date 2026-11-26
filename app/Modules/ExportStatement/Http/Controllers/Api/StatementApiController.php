<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Http\Controllers\Api;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\ExportStatement\Models\EsColumn;
use App\Modules\ExportStatement\Models\EsEditRequest;
use App\Modules\ExportStatement\Models\ExportStatement;
use App\Modules\ExportStatement\Support\EsApproval;
use App\Modules\ExportStatement\Support\EsExcel;
use App\Modules\ExportStatement\Support\EsOptions;
use App\Modules\ExportStatement\Support\EsSheet;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaSet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Sheet\ListCells;
use App\Shared\JobNo\JobNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Statement (AnnotechERP 35, from Norban's "MD Sir Export Status",
 * user 2026-11-21): Order Summary's sheet on the export invoices. The sheet's
 * make-up for this user, the statements (filtered by any column like an Excel
 * header filter) with the totals row, new statements (saved at once), the
 * changed ones — written at once for whoever holds `exportstatement.approve`,
 * else held for the Approval Center (EsApproval) —, Delete, the Excel book
 * both ways, Norban's KPI cards on the Dashboard, and the edits waiting for
 * approval. One Company Name + Invoice No. is one statement.
 */
class StatementApiController extends ApiController
{
    public const PER_PAGE = [25, 50, 100, 200, 500];

    /* ── Meta ───────────────────────────────────────────────────────── */

    /**
     * The Job No dialog (Export Statement): `?factory=NCL` gives this year's prefix
     * (26NCL), the next serial over every module and the serials this sheet
     * already has; no factory gives every factory at once (loaded as the sheet opens).
     */
    public function jobNumbers(Request $request): JsonResponse
    {
        $this->allow('exportstatement.view');

        return ApiResponse::ok(JobNumber::answer('es_statements', $this->caller()->companyId, EsSheet::today(), $request->query('factory')));
    }

    public function meta(): JsonResponse
    {
        $this->allowEither('exportstatement.view', 'exportstatement.create');
        $sheet = $this->sheet();
        $visible = $sheet->visible();
        $sources = [];
        foreach ($visible as $c) {
            if ($c->source && ! isset($sources[$c->source])) {
                $sources[$c->source] = $sheet->options($c->source);
            }
        }

        return ApiResponse::ok([
            'today' => EsSheet::today(),
            'groups' => $sheet->groups->filter(fn ($g) => $g->active && $visible->contains('group_id', $g->id))
                ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'tone' => $g->tone])->values()->all(),
            'columns' => $visible->map(fn (EsColumn $c) => self::columnBody($c, $sheet) + ['access' => $sheet->access($c)])->values()->all(),
            'sources' => $sources,
            'per_page' => self::PER_PAGE,
            'can' => [
                'create' => $this->caller()->can('exportstatement.create'), 'update' => $this->caller()->can('exportstatement.update'),
                'delete' => $this->caller()->can('exportstatement.delete'), 'columns' => $this->caller()->can('exportstatement.columns.manage'),
                'import' => $this->caller()->can('exportstatement.import'), 'export' => $this->caller()->can('exportstatement.export'),
                // Saves an edit at once (else it waits for approval).
                'approve' => $this->caller()->can('exportstatement.approve'),
            ],
        ]);
    }

    /** A column as the sheet uses it; formulas in their stored form (the browser works them out live). */
    public static function columnBody(EsColumn $c, EsSheet $sheet): array
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
        $this->allow('exportstatement.view');
        $sheet = $this->sheet();
        $request->validate([
            'per_page' => ['nullable', Rule::in([...array_map('strval', self::PER_PAGE), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:120'],
        ]);
        $query = $this->filtered($request, $sheet);
        $total = (clone $query)->count();
        $all = $request->input('per_page') === 'all';
        $perPage = $all ? max($total, 1) : (int) $request->input('per_page', 50);
        // Norban's order: by SL No.
        $page = (clone $query)->with(['creator:id,name', 'updater:id,name'])->orderBy('serial_no')
            ->paginate($perPage, ['*'], 'page', (int) $request->input('page', 1));

        return ApiResponse::ok([
            'rows' => collect($page->items())->map(fn (ExportStatement $s) => $this->row($s, $sheet))->all(),
            'page' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'per_page' => $all ? 'all' : $perPage, 'total' => $total, 'all_total' => ExportStatement::query()->count()],
            'totals' => $this->totals(clone $query, $sheet),
        ]);
    }

    /** Distinct values of one column under the other filters — a header filter's list. */
    public function distinct(Request $request): JsonResponse
    {
        $this->allow('exportstatement.view');
        $sheet = $this->sheet();
        $key = (string) $request->input('column');
        $c = $sheet->column($key);
        if (! $c || $sheet->access($c) === 'none' || $sheet->formulas->isVolatile($key) || $c->type === 'image') {
            return ApiResponse::ok([]);
        }
        $query = $this->filtered($request, $sheet, except: $key);
        if ($key === 'serial_no') {
            $rows = $query->selectRaw('serial_no::text as v, count(*) as n')->groupBy('serial_no')->orderBy('serial_no')->limit(2000)->get();
        } elseif ($key === 'status') {
            $rows = $query->selectRaw('status as v, count(*) as n')->groupBy('status')->orderBy('status')->get()
                ->map(fn ($r) => (object) ['v' => EsSheet::STATUS_LABELS[$r->v] ?? $r->v, 'n' => $r->n]);
        } elseif ($c->type === 'multi') {
            $rows = ListCells::distinct($query, $key);
        } else {
            $rows = $query->selectRaw('coalesce("values"->>?, \'\') as v, count(*) as n', [$key])->groupByRaw('1')->orderByRaw('1')->limit(2000)->get();
        }

        return ApiResponse::ok($rows->map(fn ($r) => ['value' => (string) $r->v, 'count' => (int) $r->n])->all());
    }

    /* ── Write ──────────────────────────────────────────────────────── */

    /** New statements (Add Export Statement), saved at once. Body: rows[] of {values}. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('exportstatement.create');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:200'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $created = DB::transaction(function () use ($request, $sheet, $batch, $by): array {
            $rows = [];
            foreach ($request->input('rows') as $i => $row) {
                [$values] = $sheet->put([], (array) $row['values'], "rows.{$i}.values");
                $this->checkRequired($sheet, $values, "rows.{$i}.values", $i + 1);
                $rows[$i] = $values;
            }
            $this->refuseDuplicates(array_map(fn ($v) => [$v, null], $rows), fn (int $i) => "rows.{$i}.values.invoice_no");

            return array_map(fn (array $values) => $this->create($sheet, $values, $batch, $by), array_values($rows));
        });

        return ApiResponse::created(['rows' => array_map(fn (ExportStatement $s) => $this->row($s->fresh(['creator:id,name', 'updater:id,name']), $sheet), $created)]);
    }

    /**
     * The changed statements. Body: rows[] of {id, version, values} (changed
     * cells only). A statement someone else saved since it was read comes back
     * under `conflicts`. Saved at once with `exportstatement.approve`; else
     * each changed statement's edit waits for approval and the statement keeps
     * its values (`pending` counts them; `notice` says so).
     */
    public function update(Request $request): JsonResponse
    {
        $this->allow('exportstatement.update');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*.id' => ['required', 'string', 'size:26'], 'rows.*.version' => ['required', 'integer'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $direct = $this->caller()->can('exportstatement.approve');
        // The chain is made before the save, so an edit refused for want of a signer leaves it in place to be set up.
        $direct || EsApproval::workflow($sheet->companyId);
        [$saved, $conflicts, $pending] = DB::transaction(function () use ($request, $sheet, $batch, $by, $direct): array {
            $saved = [];
            $conflicts = [];
            $pending = 0;
            foreach ($request->input('rows') as $i => $row) {
                $s = ExportStatement::query()->whereKey($row['id'])->lockForUpdate()->first();
                if (! $s) {
                    throw ValidationException::withMessages(["rows.{$i}.id" => __('exportstatement.v.row_gone')]);
                }
                if ((int) $row['version'] !== $s->version) {
                    $conflicts[] = $this->row($s->load(['creator:id,name', 'updater:id,name']), $sheet);

                    continue;
                }
                [$values, $diff] = $sheet->put($s->values ?? [], (array) $row['values'], "rows.{$i}.values");
                if ($diff !== []) {
                    $this->checkRequired($sheet, $values, "rows.{$i}.values", $i + 1, $s->serial_no);
                    $this->refuseDuplicates([[$values, $s->id]], fn () => "rows.{$i}.values.invoice_no");
                }
                $done = EsApproval::edit($sheet, $s, $values, $diff, $by, $direct, $batch, "rows.{$i}.values");
                $pending += $done === EsApproval::PENDING ? 1 : 0;
                $saved[] = $s;
            }

            return [$saved, $conflicts, $pending];
        });

        return ApiResponse::ok([
            'rows' => array_map(fn (ExportStatement $s) => $this->row($s->fresh(['creator:id,name', 'updater:id,name']), $sheet), $saved),
            'conflicts' => $conflicts, 'pending' => $pending,
            'notice' => $pending > 0 ? __('exportstatement.approval.sent', ['n' => $pending]) : null,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->allow('exportstatement.delete');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $n = DB::transaction(function () use ($request, $sheet, $batch, $by): int {
            $n = 0;
            foreach (ExportStatement::query()->whereKey($request->input('ids'))->get() as $s) {
                // An edit waiting for approval goes with it.
                if ($e = $s->pendingEdit()) {
                    EsApproval::withdraw($e, $by, __('exportstatement.approval.deleted'));
                }
                $sheet->log($s, 'DELETE', [], $batch, $by);
                $s->forceFill(['updated_by' => $by])->save();
                $s->delete();
                $n++;
            }

            return $n;
        });

        return ApiResponse::ok(['deleted' => $n]);
    }

    /* ── Excel ──────────────────────────────────────────────────────── */

    /** View All → Export Excel: the filtered (or ticked) statements, the shown columns, as Norban's book. */
    public function export(Request $request): StreamedResponse
    {
        $this->allow('exportstatement.export');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['string', 'size:26'], 'cols' => ['nullable', 'string', 'max:4000']]);
        $query = $this->filtered($request, $sheet)->orderBy('serial_no');
        if ($request->filled('ids')) {
            $query->whereKey($request->input('ids'));
        }
        $wanted = array_filter(explode(',', (string) $request->input('cols', '')));
        $columns = $sheet->visible()->filter(fn (EsColumn $c) => $c->type !== 'image' && ($wanted === [] || in_array($c->key, $wanted, true)))->values();
        $rows = $query->get()->map(fn (ExportStatement $s) => $sheet->read($s))->all();
        $book = EsExcel::book($columns, $rows);
        $name = 'Export-Statement-'.now(EsSheet::TIMEZONE)->format('Y-m-d').'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Import Excel (Norban's book or our own export): a new Company Name +
     * Invoice No. is added; an existing one is an edit — saved at once or sent
     * for approval like any other edit; an unchanged one is left alone.
     */
    public function import(Request $request): JsonResponse
    {
        $this->allow('exportstatement.import');
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);
        $sheet = $this->sheet();
        $read = EsExcel::read($request->file('file')->getRealPath(), $sheet->columns);
        $by = $this->caller()->auditUserId();
        $direct = $this->caller()->can('exportstatement.approve');
        $canCreate = $this->caller()->can('exportstatement.create');
        $canEdit = $this->caller()->can('exportstatement.update');
        $direct || EsApproval::workflow($sheet->companyId);
        $batch = (string) Str::ulid();
        $out = ['created' => 0, 'updated' => 0, 'sent_for_approval' => 0, 'unchanged' => 0, 'skipped' => [], 'unknown_columns' => $read['unknown']];
        foreach ($read['rows'] as $row) {
            $line = $row['line'];
            $input = $row['values'];
            unset($input['serial_no'], $input['status']);
            try {
                DB::transaction(function () use ($sheet, $input, $by, $direct, $canCreate, $canEdit, $batch, $line, &$out): void {
                    $company = trim((string) ($input['company_name'] ?? ''));
                    $invoice = trim((string) ($input['invoice_no'] ?? ''));
                    $existing = $company !== '' && $invoice !== ''
                        ? ExportStatement::query()->whereRaw('lower("values"->>\'company_name\') = ?', [mb_strtolower($company)])->whereRaw('lower("values"->>\'invoice_no\') = ?', [mb_strtolower($invoice)])->lockForUpdate()->first()
                        : null;
                    if ($existing) {
                        if (! $canEdit) {
                            throw ValidationException::withMessages(['row' => __('exportstatement.import.no_edit')]);
                        }
                        [$values, $diff] = $sheet->put($existing->values ?? [], $input, 'row');
                        $done = EsApproval::edit($sheet, $existing, $values, $diff, $by, $direct, $batch, 'row', 'IMPORT');
                        $out[match ($done) { EsApproval::PENDING => 'sent_for_approval', EsApproval::SAVED => 'updated', default => 'unchanged' }]++;

                        return;
                    }
                    if (! $canCreate) {
                        throw ValidationException::withMessages(['row' => __('exportstatement.import.no_create')]);
                    }
                    [$values] = $sheet->put([], $input, 'row');
                    $this->checkRequired($sheet, $values, 'row', $line);
                    $this->create($sheet, $values, $batch, $by);
                    $out['created']++;
                });
            } catch (ValidationException $e) {
                $out['skipped'][] = __('exportstatement.import.line', ['line' => $line, 'error' => collect($e->errors())->flatten()->first()]);
            }
        }

        return ApiResponse::ok($out);
    }

    /* ── Dashboard ──────────────────────────────────────────────────── */

    /**
     * Norban's KPI cards over every statement, in its four groups: Payment &
     * Collection, Cash Incentive & Govt. Documents, Documents & Shipment,
     * Quantity & Time — and the counts above them.
     */
    public function dashboard(): JsonResponse
    {
        $this->allow('exportstatement.view');
        $sheet = $this->sheet();
        $rows = ExportStatement::query()->get()->map(fn (ExportStatement $s) => ['status' => $s->status, 'v' => $sheet->read($s)]);
        $today = Carbon::parse(EsSheet::today());
        $day = fn (array $v, string $k) => isset($v[$k]) && Formula::day($v[$k]) !== null ? Carbon::parse(substr((string) $v[$k], 0, 10)) : null;
        $num = fn (array $v, string $k) => (float) (Formula::num($v[$k] ?? null) ?? 0);
        $sum = fn (Collection $c, string $k) => round($c->sum(fn ($r) => $num($r['v'], $k)), 2);
        $count = fn (callable $f) => $rows->filter(fn ($r) => $f($r['v']))->count();
        $deadlineFrom = $today->copy()->subDays(EsOptions::CLAIM_DAYS);
        $lastMonthTo = $today->copy()->subDays(EsOptions::CLAIM_DAYS - 30);
        $unclaimed = $rows->filter(fn ($r) => $day($r['v'], 'realized_date') && Formula::blank($r['v']['cash_incentive_claim_date'] ?? null));
        $open = $unclaimed->filter(fn ($r) => $day($r['v'], 'realized_date')->gte($deadlineFrom));
        $realized = $rows->filter(fn ($r) => $num($r['v'], 'realized_value') > 0);
        $due = $rows->filter(fn ($r) => ($r['v']['due_payment'] ?? null) === 'DUE');
        $dueAmount = fn (Collection $c) => round($c->sum(fn ($r) => $num($r['v'], 'invoice_value_net') - $num($r['v'], 'realized_value')), 2);
        $age = fn (array $v) => $day($v, 'payment_acceptance_date') ?? $day($v, 'docs_submission_date') ?? $day($v, 'bl_date') ?? $day($v, 'invoice_date');
        $dueOld = $due->filter(fn ($r) => ($a = $age($r['v'])) && $a->lt($today->copy()->subDays(90)));
        $totalNet = $sum($rows, 'invoice_value_net');
        $totalRealized = $sum($rows, 'realized_value');
        $pct = fn (float $part, float $whole) => $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
        $realizationDays = $rows->filter(fn ($r) => $day($r['v'], 'realized_date') && $day($r['v'], 'invoice_date'))
            ->map(fn ($r) => $day($r['v'], 'invoice_date')->diffInDays($day($r['v'], 'realized_date'), false));
        $late = $rows->map(fn ($r) => Formula::num($r['v']['docs_submission_delay_days'] ?? null))->filter(fn ($d) => $d !== null && $d > 0);
        $byMode = $rows->groupBy(fn ($r) => (string) ($r['v']['ship_mode'] ?? ''))->map(fn ($g) => $sum($g, 'invoice_value_net'));
        $monthRows = $rows->filter(fn ($r) => ($d = $day($r['v'], 'ex_factory_date')) && $d->isSameMonth($today));
        $blank = fn (array $v, string $k) => Formula::blank($v[$k] ?? null);
        $claimed = $rows->filter(fn ($r) => ! $blank($r['v'], 'cash_incentive_claim_date'));

        return ApiResponse::ok([
            'total' => $rows->count(),
            'approved' => $rows->where('status', ExportStatement::APPROVED)->count(),
            'pending_approval' => EsEditRequest::query()->where('status', EsEditRequest::PENDING)->count(),
            'month' => $today->format('M Y'),
            'payment' => [
                'total_net' => $totalNet, 'net_count' => $rows->count(),
                'total_realized' => $totalRealized, 'realized_count' => $realized->count(),
                'due_amount' => $dueAmount($due), 'due_count' => $due->count(),
                'due_90' => $dueAmount($dueOld), 'due_90_count' => $dueOld->count(),
                'realization_pct' => $pct($totalRealized, $totalNet),
                'shortage' => round($realized->sum(fn ($r) => $num($r['v'], 'shortage_over_payment')), 2),
                'avg_realization_days' => round((float) $realizationDays->avg(), 1), 'avg_realization_count' => $realizationDays->count(),
                'commission' => $sum($rows, 'commission'), 'freight' => $sum($rows, 'freight_charge'),
            ],
            'incentive' => [
                'claimed' => $sum($claimed, 'cash_incentive_claim_amount'), 'claimed_count' => $claimed->count(),
                'potential' => round($sum($open, 'invoice_value_net') * EsOptions::INCENTIVE_RATE / 100, 2), 'rate' => EsOptions::INCENTIVE_RATE,
                'open' => $open->count(), 'claim_days' => EsOptions::CLAIM_DAYS,
                'due_soon' => $unclaimed->filter(fn ($r) => ($d = $day($r['v'], 'realized_date')) && $d->gte($deadlineFrom) && $d->lte($lastMonthTo))->count(),
                'expired' => $unclaimed->filter(fn ($r) => $day($r['v'], 'realized_date')->lt($deadlineFrom))->count(),
                'prc_pending' => $realized->filter(fn ($r) => $blank($r['v'], 'prc_status') || str_contains(mb_strtolower((string) $r['v']['prc_status']), 'pending'))->count(),
                'exp_duplicate_pending' => $count(fn ($v) => ! $blank($v, 'exp_no') && ($blank($v, 'exp_duplicate_status') || mb_strtolower((string) $v['exp_duplicate_status']) === 'no')),
                'rex_pending' => $count(fn ($v) => ! $blank($v, 'bl_date') && $blank($v, 'rex_submission_date')),
            ],
            'documents' => [
                'docs_overdue' => $count(fn ($v) => ($m = $day($v, 'max_docs_submission_date')) && $m->lt($today) && $blank($v, 'docs_submission_date')),
                'late_docs' => $late->count(), 'late_docs_avg' => round((float) $late->avg(), 1), 'docs_days' => EsOptions::DOCS_SUBMISSION_DAYS,
                'bl_surrender_pending' => $count(fn ($v) => ($v['ship_mode'] ?? null) === 'Sea' && ! $blank($v, 'bl_date') && $blank($v, 'bl_surrender_date')),
                'cargo_arriving' => $count(fn ($v) => ($e = $day($v, 'eta')) && $e->gte($today) && $e->lte($today->copy()->addDays(7))),
            ],
            'quantity' => [
                'export_qty' => $sum($rows, 'export_qty'), 'export_qty_count' => $rows->count(),
                'month_shipped' => $sum($monthRows, 'invoice_value_net'), 'month_shipped_count' => $monthRows->count(),
                'sea_pct' => $pct((float) ($byMode['Sea'] ?? 0), $totalNet), 'air_pct' => $pct((float) ($byMode['Air'] ?? 0), $totalNet),
                'sea_air_pct' => $pct((float) ($byMode['Sea-Air'] ?? 0), $totalNet), 'road_pct' => $pct((float) ($byMode['Road'] ?? 0), $totalNet),
            ],
        ]);
    }

    /* ── Approvals ──────────────────────────────────────────────────── */

    /** Pending Approvals: every edit waiting, its changed cells, the step it is on and whether this user may decide or withdraw it. */
    public function approvals(): JsonResponse
    {
        $this->allowEither('exportstatement.view', 'approval.ticket.create');
        $sheet = $this->sheet();
        $labels = $sheet->columns->pluck('label', 'key');
        $user = $this->caller()->user;
        $me = $this->caller()->auditUserId();
        $edits = EsEditRequest::query()->where('status', EsEditRequest::PENDING)->with(['statement', 'requester:id,name'])->latest('created_at')->limit(300)->get();
        $recent = EsEditRequest::query()->where('status', '!=', EsEditRequest::PENDING)->with(['statement', 'requester:id,name'])->latest('decided_at')->limit(20)->get();
        $names = EsApproval::names($recent->pluck('decided_by')->all());
        $body = function (EsEditRequest $e) use ($sheet, $labels): array {
            $v = $e->statement?->values ?? [];

            return [
                'id' => $e->id, 'serial_no' => $e->statement?->serial_no, 'invoice_no' => $v['invoice_no'] ?? null, 'company' => $v['company_name'] ?? null,
                'buyer' => $v['buyer_name'] ?? null, 'source' => $e->source, 'requested_by' => $e->requester?->name, 'requested_at' => $e->created_at?->toIso8601String(),
                'changes' => collect((array) $e->diff)->map(fn ($pair, $key) => [
                    'key' => $key, 'label' => $labels[$key] ?? $key, 'old' => $this->shown($sheet, (string) $key, $pair[0] ?? null), 'new' => $this->shown($sheet, (string) $key, $pair[1] ?? null),
                ])->values()->all(),
            ];
        };

        return ApiResponse::ok([
            'pending' => $edits->map(fn (EsEditRequest $e) => $body($e) + EsApproval::info($e, $user) + ['can_withdraw' => $e->requested_by === $me])->values()->all(),
            'recent' => $recent->map(fn (EsEditRequest $e) => $body($e) + ['status' => $e->status, 'decided_by' => $names[$e->decided_by] ?? null, 'decided_at' => $e->decided_at?->toIso8601String(), 'note' => $e->decision_note])->values()->all(),
        ]);
    }

    /** Approve or reject an edit's current step (the same decision as in the Approval Center). Body: action, comment. */
    public function decide(Request $request, string $edit): JsonResponse
    {
        // Whoever reads the statements or signs in the Approval Center; whether it is their turn is checked below.
        $this->allowEither('exportstatement.view', 'approval.ticket.create');
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'comment' => ['nullable', 'string', 'max:1000']]);
        $e = EsEditRequest::query()->whereKey($edit)->where('status', EsEditRequest::PENDING)->firstOrFail();
        $approval = $e->approval_request_id ? ApprovalRequest::query()->find($e->approval_request_id) : null;
        $user = $this->caller()->user;
        if (! $approval || ! $user || ! app(DecideApproval::class)->canAct($approval, $user)) {
            throw ValidationException::withMessages(['edit' => __('exportstatement.v.not_your_turn')]);
        }
        $decisions = app(DecideApproval::class);
        $data['action'] === 'approve' ? $decisions->approve($approval, $user, $data['comment'] ?? null) : $decisions->reject($approval, $user, (string) ($data['comment'] ?? ''));

        return ApiResponse::ok(['status' => $e->fresh()->status]);
    }

    /** The editor takes their waiting edit back. */
    public function withdraw(string $edit): JsonResponse
    {
        $this->allow('exportstatement.update');
        $e = EsEditRequest::query()->whereKey($edit)->where('status', EsEditRequest::PENDING)->firstOrFail();
        if ($e->requested_by !== $this->caller()->auditUserId()) {
            throw ValidationException::withMessages(['edit' => __('exportstatement.v.not_yours')]);
        }
        EsApproval::withdraw($e, $this->caller()->auditUserId(), __('exportstatement.approval.withdrawn'));

        return ApiResponse::ok(['status' => $e->fresh()->status]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private function sheet(): EsSheet
    {
        return new EsSheet($this->caller()->companyId, $this->caller()->user);
    }

    /** One statement, the company's next SL No., saved at once. */
    private function create(EsSheet $sheet, array $values, string $batch, ?string $by): ExportStatement
    {
        DB::table('companies')->where('id', $sheet->companyId)->lockForUpdate()->value('id');
        $next = (int) ExportStatement::withTrashed()->where('company_id', $sheet->companyId)->max('serial_no') + 1;
        $s = ExportStatement::query()->create(['serial_no' => $next, 'status' => ExportStatement::CREATED, 'buyer_id' => $sheet->buyerId($values), 'values' => $values, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
        $sheet->log($s, 'CREATE', $sheet->diff([], $values), $batch, $by);

        return $s;
    }

    /**
     * One Company Name + Invoice No. is one statement: refuse a pair given
     * twice, or one already on file (other than the statement being edited).
     *
     * @param  array<int, array{0: array<string, mixed>, 1: ?string}>  $rows  [values, own id]
     * @param  callable(int): string  $field
     */
    private function refuseDuplicates(array $rows, callable $field): void
    {
        $seen = [];
        foreach ($rows as $i => [$values, $own]) {
            $company = trim((string) ($values['company_name'] ?? ''));
            $invoice = trim((string) ($values['invoice_no'] ?? ''));
            if ($company === '' || $invoice === '') {
                continue;
            }
            $key = mb_strtolower("{$company}|{$invoice}");
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([$field($i) => __('exportstatement.v.invoice_twice', ['invoice' => $invoice, 'company' => $company])]);
            }
            $seen[$key] = true;
            $exists = ExportStatement::query()->whereRaw('lower("values"->>\'company_name\') = ?', [mb_strtolower($company)])->whereRaw('lower("values"->>\'invoice_no\') = ?', [mb_strtolower($invoice)])
                ->when($own, fn ($q) => $q->whereKeyNot($own))->exists();
            if ($exists) {
                throw ValidationException::withMessages([$field($i) => __('exportstatement.v.invoice_exists', ['invoice' => $invoice, 'company' => $company])]);
            }
        }
    }

    private function checkRequired(EsSheet $sheet, array $values, string $field, int $n, ?int $serial = null): void
    {
        $missing = $sheet->missing($values);
        if ($missing !== []) {
            throw ValidationException::withMessages([$field => __('exportstatement.v.missing', ['row' => $n, 'serial' => $serial ? "#{$serial}" : '—', 'columns' => implode(', ', $missing)])]);
        }
    }

    /** A value as the approval list shows it (dates d-m-Y, amounts with their unit). */
    private function shown(EsSheet $sheet, string $key, mixed $v): ?string
    {
        if (Formula::blank($v)) {
            return null;
        }
        $c = $sheet->column($key);
        if ($c?->type === 'date' && Formula::day($v) !== null) {
            return Carbon::parse(substr((string) $v, 0, 10))->format('d-m-Y');
        }
        if ($c && in_array($c->type, FormulaSet::NUMBER_TYPES, true) && is_numeric($v)) {
            $places = $c->type === 'days' ? 0 : (int) ($c->decimals ?? 2);

            return trim(($c->prefix ?? '').number_format((float) $v, $places).' '.($c->suffix ?? ''));
        }

        return is_array($v) ? implode(', ', $v) : (string) $v;
    }

    /** @return array<string, mixed> */
    private function row(ExportStatement $s, EsSheet $sheet): array
    {
        return [
            'id' => $s->id, 'serial_no' => $s->serial_no, 'version' => $s->version, 'values' => $sheet->read($s), 'status' => $s->status,
            'created_by' => $s->creator?->name, 'updated_by' => $s->updater?->name ?? $s->creator?->name, 'updated_at' => $s->updated_at?->toIso8601String(),
            'artwork_url' => null,
        ];
    }

    /** The statements narrowed by `q`, `f[key][]` header filters and `r[key][from|to]` ranges; `$except` leaves one column's filter out. */
    private function filtered(Request $request, EsSheet $sheet, ?string $except = null): Builder
    {
        $query = ExportStatement::query();
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereRaw('LOWER("values"::text) LIKE ?', ['%'.mb_strtolower(addcslashes($search, '\\%_')).'%']);
        }
        $statusCode = array_flip(EsSheet::STATUS_LABELS);
        foreach ((array) $request->input('f', []) as $key => $list) {
            $key = (string) $key;
            $c = $sheet->column($key);
            if ($key === $except || ! $c || $sheet->formulas->isVolatile($key)) {
                continue;
            }
            $list = array_map('strval', (array) $list);
            $blank = in_array('', $list, true);
            $vals = array_values(array_filter($list, fn ($v) => $v !== ''));
            $query->where(function ($q) use ($key, $c, $vals, $blank, $statusCode): void {
                if ($c->type === 'multi') {
                    ListCells::filter($q, $key, $vals, $blank);

                    return;
                }
                if ($key === 'serial_no') {
                    $q->whereIn('serial_no', array_map('intval', $vals));

                    return;
                }
                if ($key === 'status') {
                    $q->whereIn('status', array_map(fn ($v) => $statusCode[$v] ?? $v, $vals));

                    return;
                }
                $quoted = '{'.implode(',', array_map(fn ($v) => '"'.addcslashes($v, '"\\').'"', $vals)).'}';
                if ($vals !== []) {
                    $q->orWhereRaw('"values"->>? = any(?::text[])', [$key, $quoted]);
                }
                if ($blank) {
                    $q->orWhereRaw('coalesce("values"->>?, \'\') = \'\'', [$key]);
                }
            });
        }
        foreach ((array) $request->input('r', []) as $key => $range) {
            $c = $sheet->column((string) $key);
            if (! $c || $key === $except || $sheet->formulas->isVolatile((string) $key)) {
                continue;
            }
            $numeric = in_array($c->type, FormulaSet::NUMBER_TYPES, true);
            foreach (['from' => '>=', 'to' => '<='] as $side => $op) {
                $v = $range[$side] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                $numeric
                    ? $query->whereRaw("nullif(\"values\"->>?, '')::numeric {$op} ?", [(string) $key, (float) $v])
                    : $query->whereRaw("\"values\"->>? {$op} ?", [(string) $key, (string) $v]);
            }
        }

        return $query;
    }

    /** @return array<string, float|null> SUM / AVG of each total column over every filtered statement */
    private function totals(Builder $query, EsSheet $sheet): array
    {
        $cols = $sheet->columns->filter(fn (EsColumn $c) => in_array($c->kpi, ['SUM', 'AVG'], true) && in_array($c->type, FormulaSet::NUMBER_TYPES, true)
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
}
