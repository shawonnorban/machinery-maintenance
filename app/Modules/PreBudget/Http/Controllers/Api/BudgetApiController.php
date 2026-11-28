<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbBudgetCost;
use App\Modules\PreBudget\Models\PbBudgetLine;
use App\Modules\PreBudget\Models\PbStockLot;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Modules\PreBudget\Support\RequisitionSheet;
use App\Modules\PreBudget\Support\BudgetOverview;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Modules\PreBudget\Http\Controllers\Api\Concerns\FindsBudgets;
use App\Modules\PreBudget\Models\PbBom;
use App\Modules\PreBudget\Models\PbBomStyle;
use App\Modules\PreBudget\Support\BomSheet;
use App\Modules\PreBudget\Support\BudgetApproval;
use App\Modules\PreBudget\Support\BudgetSheet;
use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\PreBudget\Models\PbBomLine;
use App\Shared\Formula\Formula;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pre-Budget → Create Budget (AnnotechERP 33/34, Norban's pivot): pick
 * completed requisitions of one buyer and make a budget — numbered at once
 * (PB-<buyer>-<MMYY>-<seq>) —, open one by its number, type on its lines
 * (Need to Purchase, PI Qty, the suppliers' prices, the price, remarks, what
 * to do with a shortage), Contract No and Discount/TPA, add or remove
 * supplier columns and styles, or delete a draft. A requisition is in one
 * budget at a time. A user reaches the budgets of their own buyers.
 */
class BudgetApiController extends ApiController
{
    /** A budget's states, in the order the Budget List shows them. */
    private const STATES = [PbBudget::DRAFT, PbBudget::PENDING, PbBudget::APPROVED, PbBudget::RETURNED, PbBudget::REVISED, PbBudget::CANCELLED];

    use FindsBudgets;

    /** The values a requisition picker filters on and lists. */
    private const PICK = ['buyer', 'brand', 'style', 'item', 'job_no'];

    public function index(): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.create');

        return ApiResponse::ok($this->scoped()->withCount('requisitions')->latest('updated_at')->limit(500)->get()->map(fn (PbBudget $b) => [
            'budget_no' => $b->label(), 'buyer' => $b->buyer_name, 'status' => $b->status, 'styles' => $b->requisitions_count,
            'updated_at' => $b->updated_at?->toIso8601String(),
        ])->all());
    }

    /** Completed requisitions in no budget (of the budget's buyer, with `budget`), filtered by buyer / brand / style / item / job no. */
    public function candidates(Request $request): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.create');
        $request->validate(['budget' => ['nullable', 'string', 'max:60']]);
        $query = $this->free();
        if ($request->filled('budget')) {
            $b = $this->find((string) $request->input('budget'));
            $query->where('buyer_id', $b->buyer_id);
        }
        $all = (clone $query)->orderByDesc('serial_no')->limit(3000)->get();
        $options = [];
        foreach (self::PICK as $k) {
            $options[$k] = $all->pluck("values.{$k}")->filter()->map(fn ($v) => Formula::text($v))->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        }
        $rows = $all->filter(function (PbRequisition $r) use ($request): bool {
            foreach (self::PICK as $k) {
                $want = array_filter((array) $request->input($k, []), fn ($v) => $v !== null && $v !== '');
                if ($want !== [] && ! in_array(Formula::text($r->values[$k] ?? ''), array_map('strval', $want), true)) {
                    return false;
                }
            }

            return true;
        });

        return ApiResponse::ok(['options' => $options, 'rows' => $rows->values()->map(fn (PbRequisition $r) => [
            'id' => $r->id, 'serial_no' => $r->serial_no, 'entry_type' => $r->entry_type,
            'buyer' => $r->values['buyer'] ?? null, 'brand' => $r->values['brand'] ?? null, 'style' => $r->values['style'] ?? null, 'item' => $r->values['item'] ?? null,
            'job_no' => Formula::text($r->values['job_no'] ?? null) ?: null, 'fabrication' => $r->values['fabrication'] ?? null, 'order_qty' => $r->values['order_qty'] ?? null, 'total_req' => $r->values['total_req'] ?? null,
        ])->all()]);
    }

    /** Create Budget: the ticked requisitions (one buyer) → a budget, numbered now. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('prebudget.create');
        $request->validate(['requisition_ids' => ['required', 'array', 'min:1', 'max:500'], 'requisition_ids.*' => ['string', 'size:26']]);
        $reqs = $this->free()->whereKey($request->input('requisition_ids'))->get();
        if ($reqs->count() !== count(array_unique($request->input('requisition_ids')))) {
            throw ValidationException::withMessages(['requisition_ids' => __('prebudget.budget.v.not_free')]);
        }
        if ($reqs->pluck('buyer_id')->unique()->count() > 1 || $reqs->first()->buyer_id === null) {
            throw ValidationException::withMessages(['requisition_ids' => __('prebudget.budget.v.one_buyer')]);
        }
        $by = $this->caller()->auditUserId();
        $budget = DB::transaction(function () use ($reqs, $by): PbBudget {
            $company = $this->caller()->companyId;
            DB::table('companies')->where('id', $company)->lockForUpdate()->value('id');
            $seq = (int) PbBudget::withTrashed()->max('budget_seq') + 1;
            $buyer = Buyer::query()->find($reqs->first()->buyer_id);
            $code = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($buyer?->code ?: $buyer?->short_name ?: 'XX'))) ?: 'XX';
            $no = sprintf('PB-%s-%s-%04d', $code, now('Asia/Dhaka')->format('my'), $seq);
            $b = PbBudget::query()->create([
                'budget_no' => $no, 'budget_seq' => $seq, 'revision_no' => 0, 'status' => PbBudget::DRAFT, 'buyer_id' => $buyer?->id,
                'buyer_name' => $buyer?->short_name ?: $buyer?->name, 'discount_percent' => 0, 'suppliers' => ['YARN' => [], 'WOVEN' => []],
                'created_by' => $by, 'updated_by' => $by,
            ]);
            $this->attach($b, $reqs->pluck('id')->all());
            $this->log($b, 'CREATE', ['styles' => [null, $reqs->pluck('values.style')->filter()->implode(', ')]], $by);

            return $b;
        });

        return ApiResponse::created(['budget_no' => $budget->label()]);
    }

    public function show(string $no): JsonResponse
    {
        $b = $this->readable($no, ['prebudget.view', 'prebudget.create']);

        return ApiResponse::ok($this->body($b));
    }

    /**
     * The budget's Order Overview (user, 2026-11-22): one line per style —
     * its colours and sizes all shown, quantities and values added up —
     * under "Order Overview of Buyer - Brand - Season - Factory" (BudgetOverview).
     */
    public function overview(string $no): JsonResponse
    {
        $b = $this->readable($no, ['prebudget.view', 'prebudget.create']);

        return ApiResponse::ok(BudgetOverview::data($b));
    }

    /** The Order Overview as an Excel book, the artwork in its cells. */
    public function overviewExport(string $no): StreamedResponse
    {
        $b = $this->readable($no, ['prebudget.view', 'prebudget.create']);
        $book = BudgetOverview::book(BudgetOverview::data($b));

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), "Order-Overview-{$b->label()}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Save what was typed. Body: version, contract_no, discount_percent,
     * suppliers {YARN: [names], WOVEN: [names]}, lines[] of {section, key,
     * need_to_purchase, pi_qty, price, supplier_prices {name: price},
     * shortage_action, remarks} — a blank Need / PI Qty / Price goes back to
     * the worked-out one.
     */
    public function update(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $this->editable($b);
        $num = ['nullable', 'numeric', 'min:0', 'max:1000000000'];
        $data = $request->validate([
            'version' => ['required', 'integer'], 'contract_no' => ['nullable', 'string', 'max:120'], 'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'suppliers' => ['nullable', 'array'], 'suppliers.YARN' => ['nullable', 'array', 'max:12'], 'suppliers.WOVEN' => ['nullable', 'array', 'max:12'],
            'suppliers.*.*' => ['string', 'max:255', Rule::exists('mp_suppliers', 'name')->where('company_id', $this->caller()->companyId)],
            'lines' => ['nullable', 'array', 'max:2000'], 'lines.*.section' => ['required', Rule::in(BudgetSheet::SECTIONS)], 'lines.*.key' => ['required', 'string', 'max:600'],
            'lines.*.need_to_purchase' => $num, 'lines.*.pi_qty' => $num, 'lines.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'lines.*.supplier_prices' => ['nullable', 'array'], 'lines.*.supplier_prices.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'lines.*.shortage_action' => ['nullable', Rule::in(['PURCHASE', 'NOT_PURCHASE'])], 'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], [], ['suppliers.*.*' => __('prebudget.budget.supplier')]);
        if ((int) $data['version'] !== $b->version) {
            return ApiResponse::error(\App\Shared\Http\Api\ErrorCode::CONFLICT, __('prebudget.budget.v.conflict'), status: 409);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($b, $data, $by): void {
            $changes = [];
            foreach (['contract_no', 'discount_percent'] as $f) {
                if (array_key_exists($f, $data) && (string) ($data[$f] ?? '') !== (string) ($b->{$f} ?? '')) {
                    $changes[$f] = [$b->{$f}, $data[$f]];
                }
            }
            $suppliers = ['YARN' => array_values(array_unique($data['suppliers']['YARN'] ?? $b->suppliers['YARN'] ?? [])), 'WOVEN' => array_values(array_unique($data['suppliers']['WOVEN'] ?? $b->suppliers['WOVEN'] ?? []))];
            if ($suppliers !== ['YARN' => $b->suppliers['YARN'] ?? [], 'WOVEN' => $b->suppliers['WOVEN'] ?? []]) {
                $changes['suppliers'] = [json_encode($b->suppliers), json_encode($suppliers)];
            }
            $b->fill(['contract_no' => $data['contract_no'] ?? $b->contract_no, 'discount_percent' => $data['discount_percent'] ?? $b->discount_percent, 'suppliers' => $suppliers]);
            $existing = $b->lines()->get()->keyBy(fn (PbBudgetLine $l) => $l->section.'|'.$l->line_key);
            foreach ($data['lines'] ?? [] as $line) {
                $fields = [
                    'need_to_purchase' => self::numOrNull($line['need_to_purchase'] ?? null), 'pi_qty' => self::numOrNull($line['pi_qty'] ?? null),
                    'price' => self::numOrNull($line['price'] ?? null), 'shortage_action' => $line['shortage_action'] ?? null, 'remarks' => trim((string) ($line['remarks'] ?? '')) ?: null,
                    'supplier_prices' => array_filter(array_map(fn ($p) => self::numOrNull($p), $line['supplier_prices'] ?? []), fn ($p) => $p !== null) ?: null,
                ];
                $row = $existing->get($line['section'].'|'.$line['key']);
                $before = $row ? $row->only(array_keys($fields)) : array_fill_keys(array_keys($fields), null);
                if ($before != $fields) {
                    $changes["{$line['section']}: {$line['key']}"] = [json_encode(array_filter($before, fn ($v) => $v !== null)), json_encode(array_filter($fields, fn ($v) => $v !== null))];
                }
                PbBudgetLine::query()->updateOrCreate(['budget_id' => $b->id, 'section' => $line['section'], 'line_key' => $line['key']], $fields);
            }
            $b->forceFill(['version' => $b->version + 1, 'updated_by' => $by])->save();
            if ($changes !== []) {
                $this->log($b, 'EDIT', $changes, $by);
            }
        });

        return ApiResponse::ok($this->body($b->fresh()));
    }

    /** Add Style: more of the buyer's free requisitions into a budget still being worked on. */
    public function addStyles(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $this->editable($b);
        $request->validate(['requisition_ids' => ['required', 'array', 'min:1', 'max:500'], 'requisition_ids.*' => ['string', 'size:26']]);
        $reqs = $this->free()->where('buyer_id', $b->buyer_id)->whereKey($request->input('requisition_ids'))->get();
        if ($reqs->isEmpty()) {
            throw ValidationException::withMessages(['requisition_ids' => __('prebudget.budget.v.not_free')]);
        }
        DB::transaction(function () use ($b, $reqs): void {
            $this->attach($b, $reqs->pluck('id')->all());
            $b->forceFill(['version' => $b->version + 1, 'updated_by' => $this->caller()->auditUserId()])->save();
            $this->log($b, 'ADD_STYLE', ['styles' => [null, $reqs->pluck('values.style')->filter()->implode(', ')]], $this->caller()->auditUserId());
        });

        return ApiResponse::ok($this->body($b->fresh()));
    }

    /** Remove Style: requisitions out of a budget still being worked on (free again); one at least stays. */
    public function removeStyles(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $this->editable($b);
        $request->validate(['requisition_ids' => ['required', 'array', 'min:1'], 'requisition_ids.*' => ['string', 'size:26']]);
        $ids = $b->requisitions()->whereIn('pb_requisitions.id', $request->input('requisition_ids'))->pluck('pb_requisitions.id');
        if ($ids->count() >= $b->requisitions()->count()) {
            throw ValidationException::withMessages(['requisition_ids' => __('prebudget.budget.v.keep_one')]);
        }
        DB::transaction(function () use ($b, $ids): void {
            $styles = PbRequisition::query()->whereKey($ids)->get()->pluck('values.style')->filter()->implode(', ');
            $b->requisitions()->detach($ids->all());
            // A style no longer in the budget leaves its BOM (an emptied draft BOM goes).
            $left = $b->requisitions()->get()->map(fn ($r) => BomSheet::styleOf($r))->unique()->all();
            PbBomStyle::query()->where('budget_id', $b->id)->whereNotIn('style', $left)->delete();
            PbBom::query()->where('budget_id', $b->id)->whereDoesntHave('styles')->delete();
            $b->forceFill(['version' => $b->version + 1, 'updated_by' => $this->caller()->auditUserId()])->save();
            $this->log($b, 'REMOVE_STYLE', ['styles' => [$styles, null]], $this->caller()->auditUserId());
        });

        return ApiResponse::ok($this->body($b->fresh()));
    }

    /** Delete a draft budget: its requisitions are free again. */
    public function destroy(string $no): JsonResponse
    {
        $this->allow('prebudget.delete');
        $b = $this->find($no);
        if ($b->status !== PbBudget::DRAFT) {
            throw ValidationException::withMessages(['budget' => __('prebudget.budget.v.draft_only')]);
        }
        DB::transaction(function () use ($b): void {
            $this->log($b, 'DELETE', [], $this->caller()->auditUserId());
            $b->requisitions()->detach();
            $b->forceFill(['updated_by' => $this->caller()->auditUserId()])->save();
            $b->delete();
        });

        return ApiResponse::ok(['deleted' => $b->label()]);
    }

    /* ── Knit/Dying Costing · Stock Yarn Use · Declaration (step 4) ─── */

    /**
     * The budgets a work screen lists: Knit/Dying Costing (`kind=costing`) or
     * Stock Yarn Use (`kind=stock`), each with whether that part is done; or
     * the budgets with stock lots (`kind=declaration`).
     */
    /**
     * Finalize: the checklist complete (Knit/Dying Costing, Stock Yarn Use,
     * every style in a final BOM), the budget goes to the Approval Center —
     * Merchandising Head → CFO → MD. Waiting, it cannot change. A company
     * that switched the chain off approves on finalizing.
     */
    public function finalize(string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $this->editable($b);
        $check = (new BudgetSheet)->view($b)['check'];
        $missing = array_values(array_filter(['costing', 'stock', 'bom'], fn ($k) => ! $check[$k]));
        if ($missing !== []) {
            throw ValidationException::withMessages(['budget' => __('prebudget.final.v.checklist', ['parts' => implode(', ', array_map(fn ($k) => __("prebudget.budget.check.{$k}"), $missing))])]);
        }
        $workflow = BudgetApproval::workflow($b->company_id);
        if ($workflow && ($nobody = BudgetApproval::unassigned($workflow, $b->company_id)) !== []) {
            throw ValidationException::withMessages(['budget' => __('prebudget.final.v.unassigned', ['steps' => implode(', ', $nobody)])]);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($b, $by): void {
            $b->forceFill(['status' => PbBudget::PENDING, 'submitted_at' => now(), 'submitted_by' => $by, 'approved_at' => null, 'approved_by' => null, 'version' => $b->version + 1, 'updated_by' => $by])->save();
            $this->log($b, 'FINALIZE', [], $by);
            app(RequestApproval::class)->forEntity($b->fresh(), $by);
        });

        return ApiResponse::ok($this->body($b->fresh()));
    }

    /** Take a waiting budget back (whoever sent it, or one who sees every budget): a draft again. */
    public function withdraw(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        if ($b->status !== PbBudget::PENDING || ! $this->mayWithdraw($b)) {
            throw ValidationException::withMessages(['budget' => __('prebudget.final.v.not_withdrawable')]);
        }
        $approval = ApprovalRequest::query()->findOrFail($b->approval_request_id);
        app(DecideApproval::class)->cancel($approval, $this->caller()->auditUserId(), $request->input('comment'));
        $this->log($b, 'WITHDRAW', [], $this->caller()->auditUserId());

        return ApiResponse::ok($this->body($b->fresh()));
    }

    /** Approve or reject the budget's current step (the same decision as in the Approval Center). Body: action, comment. */
    public function decide(Request $request, string $no): JsonResponse
    {
        $b = $this->readable($no, ['prebudget.view', 'prebudget.update']);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'comment' => ['nullable', 'string', 'max:2000']]);
        $approval = $b->approval_request_id ? ApprovalRequest::query()->find($b->approval_request_id) : null;
        $user = $this->caller()->user;
        if ($approval === null || $user === null) {
            abort(404);
        }
        $decisions = app(DecideApproval::class);
        $data['action'] === 'approve' ? $decisions->approve($approval, $user, $data['comment'] ?? null) : $decisions->reject($approval, $user, (string) ($data['comment'] ?? ''));

        return ApiResponse::ok($this->body($b->fresh()));
    }

    /**
     * Revise an approved budget: a copy numbered -R1, -R2 … (its requisitions,
     * what was typed on its lines, the costing, the stock lots and the BOMs)
     * takes its place as a draft; the approved one is kept as it was, REVISED.
     */
    public function revise(string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        if ($b->status !== PbBudget::APPROVED) {
            throw ValidationException::withMessages(['budget' => __('prebudget.final.v.not_revisable')]);
        }
        $by = $this->caller()->auditUserId();
        $new = DB::transaction(function () use ($b, $by): PbBudget {
            $copy = \App\Modules\PreBudget\Support\BudgetRevision::make($b, $by);
            $b->forceFill(['status' => PbBudget::REVISED, 'revised_at' => now(), 'updated_by' => $by])->save();
            $this->log($b, 'REVISE', ['revision' => [$b->label(), $copy->label()]], $by);

            return $copy;
        });

        return ApiResponse::created(['budget_no' => $new->label()]);
    }

    /**
     * Budget Finalized: the budgets sent for approval — waiting (with the
     * step they wait at), approved, sent back after sending, revised.
     */
    public function finalized(): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.update', 'approval.ticket.create');
        $sheet = new BudgetSheet;
        $user = $this->caller()->user;
        $approval = new BudgetApproval;

        return ApiResponse::ok($this->scoped()->whereNotNull('submitted_at')->withCount('requisitions')->latest('submitted_at')->limit(300)->get()->map(function (PbBudget $b) use ($sheet, $user, $approval) {
            $view = $sheet->view($b);
            $a = $approval->info($b, $user);

            return [
                'budget_no' => $b->label(), 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no, 'status' => $b->status, 'styles' => $b->requisitions_count,
                'net_fob' => $view['fob']['net'], 'total_cost' => round($view['costs']['total'] + $view['costs']['cm'], 2), 'accessories' => $view['costs']['accessories'],
                'step' => $a['step'], 'steps' => $a['steps'], 'current' => $a['current'], 'submitted_at' => $a['submitted_at'], 'submitted_by' => $a['submitted_by'],
                'approved_at' => $a['approved_at'], 'approved_by' => $a['approved_by'], 'can_act' => $a['can_act'],
            ];
        })->all());
    }

    /**
     * The phone's Budget List (user, 2026-11-27; the enterprise list the same
     * day): every budget this person reaches — drafts too for whoever makes
     * budgets, only the sent ones for an approver — a page at a time. Each
     * row: number, buyer, brands, how many styles and items, when and by whom
     * it was made, its state and the date of it, where it waits, its order
     * quantity and its own figures — Net FOB, Total Cost (+CM) and the
     * margin between them, as the budget sheet works them out — and what
     * this person may do with it here (finalize, withdraw, revise, delete —
     * the same rules as the budget's own actions, which check them again).
     *
     * Narrowed together: `status` (a state, or MINE = waiting at this
     * person's step), `q` (budget no, buyer, contract no, a requisition's
     * brand, style or job no), `factory`, `fy` ("2026-27", July–June by the
     * day it was made, in Dhaka), `dept`, `buyer`, `brand`. The counts per
     * state follow every filter but the state; the dropdowns' options are all
     * this person reaches.
     */
    public function cards(Request $request): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.update', 'approval.ticket.create');
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['MINE', ...self::STATES])],
            'q' => ['nullable', 'string', 'max:120'], 'factory' => ['nullable', 'string', 'max:60'], 'fy' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'dept' => ['nullable', 'string', 'max:120'], 'buyer' => ['nullable', 'string', 'max:120'], 'brand' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', Rule::in(['10', '20', '50'])],
        ]);
        $caller = $this->caller();
        $base = $this->scoped();
        if (! $caller->can('prebudget.view') && ! $caller->can('prebudget.update')) {
            $base->whereNotNull('submitted_at');
        }
        $value = fn (string $key) => "\"values\"->>'{$key}'";
        $byValue = fn (Builder $q, string $key, string $v) => $q->whereHas('requisitions', fn ($r) => $r->whereRaw($value($key).' = ?', [$v]));

        // Every filter but the state.
        $filtered = clone $base;
        foreach (['factory' => 'factory', 'dept' => 'dept', 'brand' => 'brand'] as $param => $key) {
            if (! empty($f[$param])) {
                $byValue($filtered, $key, $f[$param]);
            }
        }
        if (! empty($f['buyer'])) {
            $filtered->where('buyer_name', $f['buyer']);
        }
        if (! empty($f['fy'])) {
            $start = (int) substr($f['fy'], 0, 4);
            $from = CarbonImmutable::create($start, 7, 1, 0, 0, 0, 'Asia/Dhaka')->utc();
            $filtered->where('created_at', '>=', $from)->where('created_at', '<', $from->addYear());
        }
        $needle = trim((string) ($f['q'] ?? ''));
        if ($needle !== '') {
            $like = '%'.mb_strtolower(addcslashes(preg_replace('/-R\d+$/i', '', $needle), '\\%_')).'%';
            $filtered->where(fn ($q) => $q->whereRaw('LOWER(budget_no) LIKE ?', [$like])->orWhereRaw('LOWER(buyer_name) LIKE ?', [$like])->orWhereRaw('LOWER(COALESCE(contract_no, \'\')) LIKE ?', [$like])
                ->orWhereHas('requisitions', fn ($r) => $r->where(fn ($w) => $w->whereRaw('LOWER('.$value('brand').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$value('style').') LIKE ?', [$like])->orWhereRaw('LOWER('.$value('job_no').') LIKE ?', [$like]))));
        }

        $user = $caller->user;
        $decisions = app(DecideApproval::class);
        // Waiting at this person's step: the pending budgets whose current step they may sign.
        $pending = (clone $filtered)->where('status', PbBudget::PENDING)->whereNotNull('approval_request_id')->pluck('approval_request_id', 'id');
        $requests = ApprovalRequest::query()->whereIn('id', $pending->values())->get()->keyBy('id');
        $mine = $user === null ? [] : $pending->filter(fn ($r) => isset($requests[$r]) && $decisions->canAct($requests[$r], $user))->keys()->all();

        $byState = (clone $filtered)->toBase()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $counts = ['ALL' => (int) $byState->sum(), 'MINE' => count($mine)];
        foreach (self::STATES as $state) {
            $counts[$state] = (int) ($byState[$state] ?? 0);
        }

        $status = $f['status'] ?? null;
        $rows = clone $filtered;
        if ($status === 'MINE') {
            $rows->whereIn('id', $mine ?: ['-']);
        } elseif ($status) {
            $rows->where('status', $status);
        }
        $page = $rows->with('requisitions:id,entry_type,values')->latest('updated_at')->orderByDesc('id')
            ->paginate((int) ($f['per_page'] ?? 20), ['*'], 'page', (int) ($f['page'] ?? 1));
        $makers = DB::table('users')->whereIn('id', collect($page->items())->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $sheet = new BudgetSheet;
        $approval = new BudgetApproval;
        $update = $caller->can('prebudget.update');
        $text = fn ($v) => Formula::text($v);

        $list = collect($page->items())->map(function (PbBudget $b) use ($makers, $sheet, $approval, $user, $update, $mine, $text) {
            $reqs = $b->requisitions;
            $distinct = fn (string $k) => $reqs->map(fn ($r) => $text($r->values[$k] ?? ''))->filter()->unique()->values();
            $editable = $b->editable() && $update;
            // The budget's own figures (the card's foot, as the reference screen) — for this page's rows only.
            $view = $sheet->view($b);
            $check = $editable ? $view['check'] : null;
            $cost = round($view['costs']['total'] + $view['costs']['cm'], 2);
            $a = $b->status === PbBudget::PENDING ? $approval->info($b, $user) : null;
            [$event, $at] = match ($b->status) {
                PbBudget::PENDING => ['submitted', $b->submitted_at],
                PbBudget::APPROVED => ['approved', $b->approved_at],
                PbBudget::REVISED => ['revised', $b->revised_at],
                PbBudget::CANCELLED => ['cancelled', $b->cancelled_at],
                PbBudget::RETURNED => ['returned', $b->updated_at],
                default => ['created', $b->created_at],
            };

            return [
                'budget_no' => $b->label(), 'status' => $b->status, 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no,
                'brands' => $distinct('brand')->all(), 'styles' => $distinct('style')->count(), 'items' => $distinct('item')->count(),
                'item_names' => $distinct('item')->all(), 'kinds' => $reqs->pluck('entry_type')->filter()->unique()->sort()->values()->all(),
                'factories' => $distinct('factory')->all(),
                'created_at' => $b->created_at?->toIso8601String(), 'created_by' => $makers[$b->created_by] ?? null, 'updated_at' => $b->updated_at?->toIso8601String(),
                'event' => $event, 'event_at' => $at?->toIso8601String(),
                'order_qty' => $view['fob']['order_qty'], 'net_fob' => $view['fob']['net'], 'total_cost' => $cost, 'margin' => round($view['fob']['net'] - $cost, 2),
                'waiting_at' => $a ? ['step' => $a['step'], 'steps' => $a['steps'], 'current' => $a['current']] : null,
                'can_act' => in_array($b->id, $mine, true),
                'check' => $check === null ? null : ['costing' => $check['costing'], 'stock' => $check['stock'], 'bom' => $check['bom']],
                'actions' => [
                    'finalize' => $editable, 'withdraw' => $b->status === PbBudget::PENDING && $this->mayWithdraw($b),
                    'revise' => $b->status === PbBudget::APPROVED && $update, 'delete' => $b->status === PbBudget::DRAFT && $this->caller()->can('prebudget.delete'),
                ],
            ];
        });

        // The dropdowns: every factory, department, brand (from the requisitions) and buyer and year this person reaches.
        $ids = (clone $base)->select('pb_budgets.id');
        $values = DB::table('pb_budget_requisitions as br')->join('pb_requisitions as r', 'r.id', '=', 'br.requisition_id')->whereIn('br.budget_id', $ids)
            ->selectRaw("DISTINCT r.\"values\"->>'factory' as factory, r.\"values\"->>'dept' as dept, r.\"values\"->>'brand' as brand")->get();
        $sorted = fn ($c) => $c->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        $options = [
            'factories' => $sorted($values->pluck('factory')), 'departments' => $sorted($values->pluck('dept')), 'brands' => $sorted($values->pluck('brand')),
            'buyers' => $sorted((clone $base)->toBase()->distinct()->pluck('buyer_name')),
            'fys' => (clone $base)->toBase()->pluck('created_at')->map(function ($at) {
                $d = CarbonImmutable::parse($at, 'UTC')->setTimezone('Asia/Dhaka');
                $start = $d->month >= 7 ? $d->year : $d->year - 1;

                return sprintf('%d-%02d', $start, ($start + 1) % 100);
            })->unique()->sortDesc()->values()->all(),
        ];

        return ApiResponse::ok([
            'counts' => $counts, 'options' => $options, 'rows' => $list->all(),
            'page' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    private function mayWithdraw(PbBudget $b): bool
    {
        return $this->caller()->can('prebudget.update') && ($b->submitted_by === $this->caller()->auditUserId() || $this->caller()->can('prebudget.view_all'));
    }

    public function work(Request $request): JsonResponse
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(['costing', 'stock', 'declaration'])]])['kind'];
        $kind === 'costing' ? $this->allowEither('prebudget.view', 'prebudget.update') : $this->allowEither('prebudget.stock', 'prebudget.view');
        $query = $this->scoped($kind !== 'costing')->whereNotIn('status', PbBudget::INACTIVE)->withCount('requisitions')->latest('updated_at')->limit(300);
        if ($kind === 'declaration') {
            $query->whereHas('stockLots');
        }
        $sheet = new BudgetSheet;

        return ApiResponse::ok($query->get()->map(function (PbBudget $b) use ($sheet, $kind) {
            $view = $kind === 'declaration' ? null : $sheet->view($b);
            $lots = $kind === 'declaration' ? $b->stockLots()->get() : null;

            return [
                'budget_no' => $b->label(), 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no, 'status' => $b->status, 'styles' => $b->requisitions_count,
                'done' => $view ? $view['check'][$kind] : null, 'done_at' => $view ? $view['check']["{$kind}_at"] : null, 'editable' => $b->editable(),
                'cost' => $view ? ($kind === 'costing' ? round($view['costs']['knitting'] + $view['costs']['dyeing'] + $view['costs']['yarn_dyeing'] + $view['costs']['aop'] + $view['costs']['embellishment'] + $view['costs']['badge'] + $view['costs']['cm'], 2)
                    : round($view['sections']['YARN']['totals']['stock_value'] + $view['sections']['WOVEN']['totals']['stock_value'], 2)) : null,
                'stock_qty' => $lots ? round($lots->sum('qty'), 3) : null,
                'stock_value' => $lots ? round($lots->sum(fn ($l) => $l->qty * ($l->price ?? 0)), 2) : null,
                'updated_at' => $b->updated_at?->toIso8601String(),
            ];
        })->all());
    }

    public function costing(string $no): JsonResponse
    {
        $b = $this->readable($no, ['prebudget.view', 'prebudget.update']);

        return ApiResponse::ok($this->head($b) + [
            'editable' => $b->editable() && $this->caller()->can('prebudget.update'),
            'supplier_options' => DB::table('mp_suppliers')->where('company_id', $b->company_id)->where('active', true)->orderBy('name')->pluck('name')->all(),
        ] + (new BudgetSheet)->view($b));
    }

    /**
     * Save Knit/Dying Costing; `complete` also marks it done — with the
     * Dollar Rate, every fabrication's Knitting and Dyeing Finishing rate and
     * every style's CM filled (as Norban asks). Saving without it leaves the
     * costing open.
     */
    public function saveCosting(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $this->editable($b);
        $rate = ['nullable', 'numeric', 'min:0', 'max:1000000'];
        $data = $request->validate([
            'dollar_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'knit_note' => ['nullable', 'string', 'max:5000'], 'dye_note' => ['nullable', 'string', 'max:5000'],
            'fabrics' => ['nullable', 'array', 'max:500'], 'fabrics.*.fabrication' => ['present', 'nullable', 'string', 'max:600'],
            'fabrics.*.knit_rate' => $rate, 'fabrics.*.dye_rate' => $rate, 'fabrics.*.yarn_dye_rate' => $rate, 'fabrics.*.aop_rate' => $rate,
            'styles' => ['nullable', 'array', 'max:1000'], 'styles.*.style' => ['present', 'nullable', 'string', 'max:500'], 'styles.*.emb_per_dz' => $rate, 'styles.*.cm_per_dz' => $rate,
            'styles.*.badge_wastage' => ['nullable', 'numeric', 'min:0', 'max:10'], 'styles.*.badge_per_dz' => $rate,
            'styles.*.badge_supplier' => ['nullable', 'string', 'max:255', Rule::exists('mp_suppliers', 'name')->where('company_id', $this->caller()->companyId)],
            'styles.*.badge_remarks' => ['nullable', 'string', 'max:500'], 'complete' => ['sometimes', 'boolean'],
        ], [], ['styles.*.badge_supplier' => __('prebudget.budget.supplier')]);
        $by = $this->caller()->auditUserId();
        $mine = $b->requisitions()->get()->map(fn ($r) => trim(Formula::text($r->values['style'] ?? '')))->unique()->all();
        DB::transaction(function () use ($b, $data, $by, $mine): void {
            $b->fill(['dollar_rate' => self::numOrNull($data['dollar_rate'] ?? null), 'knit_note' => trim((string) ($data['knit_note'] ?? '')) ?: null, 'dye_note' => trim((string) ($data['dye_note'] ?? '')) ?: null]);
            foreach ($data['fabrics'] ?? [] as $f) {
                PbBudgetCost::query()->updateOrCreate(['budget_id' => $b->id, 'kind' => 'FABRIC', 'cost_key' => trim((string) $f['fabrication'])], [
                    'knit_rate' => self::numOrNull($f['knit_rate'] ?? null), 'dye_rate' => self::numOrNull($f['dye_rate'] ?? null),
                    'yarn_dye_rate' => self::numOrNull($f['yarn_dye_rate'] ?? null), 'aop_rate' => self::numOrNull($f['aop_rate'] ?? null),
                ]);
            }
            foreach ($data['styles'] ?? [] as $st) {
                $style = trim((string) ($st['style'] ?? ''));
                if (in_array($style, $mine, true)) {
                    PbBudgetCost::query()->updateOrCreate(['budget_id' => $b->id, 'kind' => 'STYLE', 'cost_key' => $style], [
                        'emb_per_dz' => self::numOrNull($st['emb_per_dz'] ?? null), 'cm_per_dz' => self::numOrNull($st['cm_per_dz'] ?? null),
                        'badge_wastage' => self::numOrNull($st['badge_wastage'] ?? null), 'badge_per_dz' => self::numOrNull($st['badge_per_dz'] ?? null),
                        'badge_supplier' => $st['badge_supplier'] ?? null, 'badge_remarks' => trim((string) ($st['badge_remarks'] ?? '')) ?: null,
                    ]);
                }
            }
            $b->forceFill(['costing_done_at' => null, 'costing_done_by' => null, 'costing_basis' => null, 'version' => $b->version + 1, 'updated_by' => $by])->save();
            if (! empty($data['complete'])) {
                $costing = (new BudgetSheet)->costingFor($b->fresh());
                $missing = [];
                if (! $b->dollar_rate) {
                    $missing[] = __('prebudget.costing.dollar_rate');
                }
                foreach ($costing['fabrics'] as $f) {
                    if ($f['knit_rate'] === null || $f['dye_rate'] === null) {
                        $missing[] = __('prebudget.costing.v.fabric', ['fabrication' => $f['fabrication'] ?: '—']);
                    }
                }
                foreach ($costing['styles'] as $st) {
                    if ($st['cm_per_dz'] === null) {
                        $missing[] = __('prebudget.costing.v.cm', ['style' => $st['style'] ?: '—']);
                    }
                }
                if ($missing !== []) {
                    throw ValidationException::withMessages(['complete' => __('prebudget.costing.v.missing', ['items' => implode(', ', array_slice($missing, 0, 8)).(count($missing) > 8 ? ' …' : '')])]);
                }
                $b->forceFill(['costing_done_at' => now(), 'costing_done_by' => $by, 'costing_basis' => $costing['basis']])->save();
            }
            $this->log($b, empty($data['complete']) ? 'COSTING' : 'COSTING_DONE', [], $by);
        });

        return $this->costing($no);
    }

    public function stock(string $no): JsonResponse
    {
        $this->allowEither('prebudget.stock', 'prebudget.view');
        $b = $this->find($no, true);
        $can = $this->caller()->can('prebudget.stock') || $this->caller()->can('prebudget.update');

        return ApiResponse::ok($this->head($b) + ['editable' => $b->editable() && $can, 'mills' => DB::table('mp_suppliers')->where('company_id', $b->company_id)->where('active', true)->orderBy('name')->pluck('name')->all()]
            + collect((new BudgetSheet)->view($b))->only(['sections', 'check'])->all());
    }

    /**
     * Save Stock Yarn Use: each line's stock lots, or No stock. `complete`
     * also marks it done — every line answered, each lot with a Qty and an
     * Origin, a line's lots no more than its Total Req.
     */
    public function saveStock(Request $request, string $no): JsonResponse
    {
        $this->allowEither('prebudget.stock', 'prebudget.update');
        $b = $this->find($no, true);
        $this->editable($b);
        $data = $request->validate([
            'lines' => ['present', 'array', 'max:2000'], 'lines.*.section' => ['required', Rule::in(BudgetSheet::SECTIONS)], 'lines.*.key' => ['required', 'string', 'max:600'],
            'lines.*.no_stock' => ['sometimes', 'boolean'], 'lines.*.lots' => ['present', 'array', 'max:50'],
            'lines.*.lots.*.purchase_contract_no' => ['nullable', 'string', 'max:120'], 'lines.*.lots.*.stock_contract_no' => ['nullable', 'string', 'max:120'],
            'lines.*.lots.*.mill' => ['nullable', 'string', 'max:255', Rule::exists('mp_suppliers', 'name')->where('company_id', $this->caller()->companyId)],
            'lines.*.lots.*.origin' => ['nullable', Rule::in(['LOCAL', 'IMPORT'])], 'lines.*.lots.*.lc_no' => ['nullable', 'string', 'max:120'], 'lines.*.lots.*.lot_no' => ['nullable', 'string', 'max:120'],
            'lines.*.lots.*.qty' => ['nullable', 'numeric', 'min:0', 'max:100000000'], 'lines.*.lots.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'complete' => ['sometimes', 'boolean'],
        ], [], ['lines.*.lots.*.mill' => __('prebudget.stock.mill')]);
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($b, $data, $by): void {
            $b->stockLots()->delete();
            foreach ($data['lines'] as $line) {
                PbBudgetLine::query()->updateOrCreate(['budget_id' => $b->id, 'section' => $line['section'], 'line_key' => $line['key']], ['no_stock' => (bool) ($line['no_stock'] ?? false)]);
                foreach (array_values($line['lots']) as $i => $lot) {
                    $filled = array_filter($lot, fn ($v) => $v !== null && $v !== '');
                    if ($filled === []) {
                        continue;
                    }
                    PbStockLot::query()->create([
                        'budget_id' => $b->id, 'section' => $line['section'], 'line_key' => $line['key'], 'sort_order' => $i,
                        'purchase_contract_no' => $lot['purchase_contract_no'] ?? null, 'stock_contract_no' => $lot['stock_contract_no'] ?? null, 'mill' => $lot['mill'] ?? null,
                        'origin' => $lot['origin'] ?? null, 'lc_no' => $lot['lc_no'] ?? null, 'lot_no' => $lot['lot_no'] ?? null,
                        'qty' => (float) ($lot['qty'] ?? 0), 'price' => self::numOrNull($lot['price'] ?? null),
                    ]);
                }
            }
            $b->forceFill(['stock_done_at' => null, 'stock_done_by' => null, 'stock_basis' => null, 'version' => $b->version + 1, 'updated_by' => $by])->save();
            if (! empty($data['complete'])) {
                $view = (new BudgetSheet)->view($b->fresh());
                $problems = [];
                foreach ($view['sections'] as $section) {
                    foreach ($section['lines'] as $l) {
                        $label = $l['yarn_count'] ? "{$l['yarn_count']} · {$l['composition']}" : "{$l['style']} · {$l['fabrication']}";
                        if (! $l['no_stock'] && $l['lots'] === []) {
                            $problems[] = __('prebudget.stock.v.unanswered', ['line' => $label]);
                        }
                        foreach ($l['lots'] as $lot) {
                            if ($lot['qty'] <= 0 || ! $lot['origin']) {
                                $problems[] = __('prebudget.stock.v.lot', ['line' => $label]);
                                break;
                            }
                        }
                        if ($l['stock_qty'] > $l['total_req'] + 0.005) {
                            $problems[] = __('prebudget.stock.v.over', ['line' => $label, 'req' => $l['total_req']]);
                        }
                    }
                }
                if ($problems !== []) {
                    throw ValidationException::withMessages(['complete' => implode(' · ', array_slice(array_unique($problems), 0, 6)).(count($problems) > 6 ? ' …' : '')]);
                }
                $b->forceFill(['stock_done_at' => now(), 'stock_done_by' => $by, 'stock_basis' => BudgetSheet::stockBasis($view['sections'])])->save();
            }
            $this->log($b, empty($data['complete']) ? 'STOCK' : 'STOCK_DONE', [], $by);
        });

        return $this->stock($no);
    }

    /** Stock Yarn Budget Declaration: the stock lots a budget uses, Local and Import, with their yarn count and composition. */
    public function declaration(string $no): JsonResponse
    {
        $b = $this->readable($no, ['prebudget.stock', 'prebudget.view'], true);
        $view = (new BudgetSheet)->view($b);
        $rows = [];
        foreach ($view['sections'] as $section => $data) {
            foreach ($data['lines'] as $l) {
                foreach ($l['lots'] as $lot) {
                    $rows[] = $lot + ['section' => $section, 'yarn_count' => $l['yarn_count'], 'composition' => $l['composition'], 'style' => $l['style'], 'fabrication' => $l['fabrication']];
                }
            }
        }

        return ApiResponse::ok($this->head($b) + ['factories' => $view['factories'], 'lots' => $rows]);
    }

    /** @return array<string, mixed> what each work screen shows on top */
    private function head(PbBudget $b): array
    {
        return ['budget_no' => $b->label(), 'status' => $b->status, 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no, 'version' => $b->version];
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function body(PbBudget $b): array
    {
        $reqs = $b->requisitions()->orderBy('serial_no')->get();

        return [
            'budget_no' => $b->label(), 'status' => $b->status, 'version' => $b->version, 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no,
            'editable' => $b->editable() && $this->caller()->can('prebudget.update'), 'can_delete' => $b->status === PbBudget::DRAFT && $this->caller()->can('prebudget.delete'),
            'approval' => (new BudgetApproval)->info($b, $this->caller()->user),
            // The budget print's "Prepared By": whoever made the budget.
            'prepared_by' => $b->created_by ? DB::table('users')->where('id', $b->created_by)->value('name') : null,
            'can_finalize' => $b->editable() && $this->caller()->can('prebudget.update'),
            'can_withdraw' => $b->status === PbBudget::PENDING && $this->mayWithdraw($b),
            'can_revise' => $b->status === PbBudget::APPROVED && $this->caller()->can('prebudget.update'),
            // Cancelled by its order (Quantity, PAC or FOB changed after sending, or dropped): when, by whom, why.
            'cancelled' => $b->status === PbBudget::CANCELLED ? [
                'at' => $b->cancelled_at?->toIso8601String(), 'by' => $b->cancelled_by ? DB::table('users')->where('id', $b->cancelled_by)->value('name') : null, 'reason' => $b->cancel_reason,
            ] : null,
            'revisions' => PbBudget::query()->where('budget_no', $b->budget_no)->orderBy('revision_no')->get(['budget_no', 'revision_no', 'status'])->map(fn ($r) => ['budget_no' => $r->label(), 'status' => $r->status])->all(),
            'requisitions' => $reqs->map(fn (PbRequisition $r) => [
                'id' => $r->id, 'serial_no' => $r->serial_no, 'entry_type' => $r->entry_type, 'style' => $r->values['style'] ?? null, 'item' => $r->values['item'] ?? null,
                'order_qty' => $r->values['order_qty'] ?? null, 'total_req' => $r->values['total_req'] ?? null,
                // The phone's order summary shows each style's FOB per piece (its FOB value over its pieces).
                'fob_value' => Formula::num($r->values['fob_value'] ?? null),
                'brand' => Formula::text($r->values['brand'] ?? '') ?: null, 'dept' => Formula::text($r->values['dept'] ?? '') ?: null,
            ])->all(),
            'supplier_options' => DB::table('mp_suppliers')->where('company_id', $b->company_id)->where('active', true)->orderBy('name')->pluck('name')->all(),
        ] + (new BudgetSheet)->view($b);
    }

    /** Completed requisitions this user reaches that no budget holds. */
    private function free(): Builder
    {
        $query = PbRequisition::query()->where('status', PbRequisition::COMPLETE)
            ->whereNotExists(fn ($q) => $q->from('pb_budget_requisitions as br')->join('pb_budgets as b', 'b.id', '=', 'br.budget_id')
                ->whereColumn('br.requisition_id', 'pb_requisitions.id')->whereNull('b.deleted_at')->whereNotIn('b.status', PbBudget::INACTIVE));
        $scope = $this->buyerScope();
        if ($scope !== null) {
            $me = $this->caller()->auditUserId();
            $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me));
        }

        return $query;
    }

    /** @param  list<string>  $ids */
    private function attach(PbBudget $b, array $ids): void
    {
        $now = now();
        DB::table('pb_budget_requisitions')->insert(array_map(fn (string $id) => [
            'id' => Str::lower((string) Str::ulid()), 'company_id' => $b->company_id, 'budget_id' => $b->id, 'requisition_id' => $id, 'created_at' => $now, 'updated_at' => $now,
        ], $ids));
    }

    private static function numOrNull(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : round((float) $v, 4);
    }

}
