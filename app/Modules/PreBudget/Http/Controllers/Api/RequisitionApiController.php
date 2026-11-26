<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api;

use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Modules\PreBudget\Models\PbRequisitionDraft;
use App\Modules\PreBudget\Support\OrderLink;
use App\Modules\PreBudget\Support\RequisitionSheet;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Support\Thumbnails;
use App\Shared\Formula\FormulaSet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Sheet\ListCells;
use App\Shared\JobNo\JobNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pre-Budget → Requisition (AnnotechERP 33/34): All and Add Requisition.
 * The sheet's make-up for this user, the rows (filtered by any column like
 * an Excel header filter, with the totals row), and every write: new rows
 * Completed (dropping the draft they were typed in — drafts are
 * DraftApiController's), the changed rows saved together with the version
 * each was read at, Copy and Delete, and the artwork. A user reaches the requisitions of their own
 * buyers (Merchandising Team, as Order Summary) and the rows they entered.
 * A requisition is Completed only with every required column of its Entry
 * Type filled; it then gets its serial number.
 */
class RequisitionApiController extends ApiController
{
    public const PER_PAGE = [25, 50, 100, 200, 500];

    /* ── Meta ───────────────────────────────────────────────────────── */

    /**
     * The Job No dialog (Pre-Budget requisitions): `?factory=NCL` gives this year's prefix
     * (26NCL), the next serial over every module and the serials this sheet
     * already has; no factory gives every factory at once (loaded as the sheet opens).
     */
    public function jobNumbers(Request $request): JsonResponse
    {
        $this->allow('prebudget.view');

        return ApiResponse::ok(JobNumber::answer('pb_requisitions', $this->caller()->companyId, RequisitionSheet::today(), $request->query('factory')));
    }

    public function meta(): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.create');
        $sheet = $this->sheet();
        $visible = $sheet->visible();
        $sources = [];
        foreach ($visible as $c) {
            if ($c->source && ! isset($sources[$c->source])) {
                $sources[$c->source] = $sheet->options($c->source);
            }
            // An Order Color cell picks its sizes from the Size master.
            if ($c->type === 'colorsize' && ! isset($sources['sizes'])) {
                $sources['sizes'] = $sheet->options('sizes');
            }
        }

        return ApiResponse::ok([
            'today' => RequisitionSheet::today(),
            'groups' => $sheet->groups->filter(fn ($g) => $g->active && $visible->contains('group_id', $g->id))
                ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'tone' => $g->tone])->values()->all(),
            'columns' => $visible->map(fn (PbColumn $c) => self::columnBody($c, $sheet) + ['access' => $sheet->access($c)])->values()->all(),
            'sources' => $sources,
            'per_page' => self::PER_PAGE,
            // The cells an order fills on a row made from it (Add Requisition's order picker): changed in Order Summary only.
            'order_keys' => OrderLink::lockedKeys($sheet),
            'can' => [
                'create' => $this->caller()->can('prebudget.create'), 'update' => $this->caller()->can('prebudget.update'),
                'delete' => $this->caller()->can('prebudget.delete'), 'columns' => $this->caller()->can('prebudget.columns.manage'),
            ],
        ]);
    }

    /** A column as the sheet uses it; formulas in their stored form (the browser works them out live). */
    public static function columnBody(PbColumn $c, RequisitionSheet|\App\Modules\PreBudget\Support\BomColumns $sheet): array
    {
        return [
            'key' => $c->key, 'label' => $c->label, 'group_id' => $c->group_id, 'type' => $c->type, 'source' => $c->source, 'multiple' => $c->multiple,
            'prefix' => $c->prefix, 'suffix' => $c->suffix, 'decimals' => $c->decimals, 'width' => $c->width, 'vertical' => $c->vertical, 'frozen' => $c->frozen,
            'required' => $c->required, 'applies_to' => $c->applies_to, 'formula' => $c->formula, 'volatile' => $sheet->formulas->isVolatile($c->key),
            'color_rules' => $c->color_rules ?? [], 'text_tone' => $c->text_tone, 'bold' => (bool) $c->bold, 'kpi' => $c->kpi, 'is_system' => $c->is_system, 'hidden' => $c->hidden,
        ];
    }

    /* ── Orders (Add Requisition's order picker) ───────────────────── */

    /**
     * The orders this user may make a requisition from: their Order Summary
     * orders, open (not DROPPED, Cancelled or shipped out) and with no
     * requisition yet — the most urgent PO-wise RFI first.
     */
    public function orders(): JsonResponse
    {
        $this->allow('prebudget.create');
        $orders = OrderLink::open($this->orderScope())
            ->orderByRaw("nullif(\"values\"->>'po_wise_rfi_sd', '') asc nulls last")->orderByDesc('serial_no')
            ->limit(3000)->get(['id', 'serial_no', 'values']);

        return ApiResponse::ok(['orders' => $orders->map(fn (OsOrder $o) => OrderLink::option($o))->all()]);
    }

    /** One picked order: the cells it fills on a new requisition row (its artwork copied as the requisition's). */
    public function pickOrder(Request $request): JsonResponse
    {
        $this->allow('prebudget.create');
        $request->validate(['order_id' => ['required', 'string', 'size:26']]);
        $order = $this->pickable((string) $request->input('order_id'), 'order_id');
        $sheet = $this->sheet();
        $filled = OrderLink::fill($order, $sheet);
        $values = $filled + OrderLink::prefill($order);
        $artwork = OrderLink::copyArtwork($order, $this->caller()->companyId, $this->caller()->auditUserId());
        if ($artwork) {
            $values['artwork'] = $artwork->id;
        }

        // `locked`: the cells the order has a value in — the rest (empty on the order) are typed on the row.
        // `warning`: order values the requisition's lists do not hold (corrected in Order Summary before Complete).
        return ApiResponse::ok([
            'order' => OrderLink::option($order), 'values' => $values, 'locked' => array_keys($filled), 'artwork_url' => $artwork ? "/api/files/{$artwork->id}/download" : null,
            'warning' => $this->orderProblem($order, $filled, $sheet),
        ]);
    }

    /* ── List ───────────────────────────────────────────────────────── */

    /** All Requisition (status COMPLETE, the default) or Draft Requisition (status DRAFT). */
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.create');
        $sheet = $this->sheet();
        $request->validate([
            'per_page' => ['nullable', Rule::in([...array_map('strval', self::PER_PAGE), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:120'],
        ]);
        $status = PbRequisition::COMPLETE;
        $query = $this->filtered($request, $sheet, $status);
        $total = (clone $query)->count();
        $totals = $this->totals(clone $query, $sheet);
        $all = $request->input('per_page') === 'all';
        $perPage = $all ? max($total, 1) : (int) $request->input('per_page', 50);
        $query->orderByRaw('serial_no desc nulls first')->orderByDesc('created_at');
        $page = $query->with(['creator:id,name', 'updater:id,name', 'order:id,serial_no,values'])->paginate($perPage, ['*'], 'page', (int) $request->input('page', 1));

        // The artwork thumbnails inline, so the pictures show with the page (not one request each, one after another).
        $this->thumbs = count($page->items()) <= Thumbnails::MAX_ROWS ? Thumbnails::many(collect($page->items())->map(fn (PbRequisition $r) => $r->values['artwork'] ?? null)->all()) : [];

        return ApiResponse::ok([
            'rows' => collect($page->items())->map(fn (PbRequisition $r) => $this->row($r, $sheet))->all(),
            'page' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'per_page' => $all ? 'all' : $perPage, 'total' => $total, 'all_total' => $this->scoped($status)->count()],
            'totals' => $totals,
        ]);
    }

    /** @return array<string, float|null> SUM / AVG of each total column over every filtered row */
    private function totals(Builder $query, RequisitionSheet $sheet): array
    {
        $cols = $sheet->columns->filter(fn (PbColumn $c) => in_array($c->kpi, ['SUM', 'AVG'], true) && in_array($c->type, FormulaSet::NUMBER_TYPES, true)
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

    /** Distinct values of one column under the other filters — a header filter's list. */
    public function distinct(Request $request): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.create');
        $sheet = $this->sheet();
        $key = (string) $request->input('column');
        $c = $sheet->column($key);
        if (! $c || $sheet->access($c) === 'none' || $sheet->formulas->isVolatile($key) || $c->type === 'image') {
            return ApiResponse::ok([]);
        }
        $query = $this->filtered($request, $sheet, PbRequisition::COMPLETE, except: $key);
        if ($key === 'serial_no') {
            $rows = $query->selectRaw('serial_no::text as v, count(*) as n')->groupBy('serial_no')->orderBy('serial_no')->limit(2000)->get();
        } elseif ($key === 'entry_date') {
            $rows = $query->selectRaw("(created_at at time zone 'Asia/Dhaka')::date::text as v, count(*) as n")->groupByRaw('1')->orderByRaw('1')->limit(2000)->get();
        } elseif ($c->type === 'multi') {
            $rows = ListCells::distinct($query, $key);
        } elseif ($c->type === 'colorsize') {
            // Each colour of an Order Color cell counts for its row; a row with none is a blank.
            $counts = [];
            foreach ($query->toBase()->selectRaw('"values"->? as v', [$key])->limit(50000)->pluck('v') as $json) {
                $colours = RequisitionSheet::colorsOf(json_decode((string) $json, true));
                foreach ($colours !== [] ? array_unique($colours) : [''] as $v) {
                    $counts[$v] = ($counts[$v] ?? 0) + 1;
                }
            }
            ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
            $rows = collect($counts)->map(fn ($n, $v) => (object) ['v' => $v, 'n' => $n])->values();
        } else {
            $rows = $query->selectRaw('coalesce("values"->>?, \'\') as v, count(*) as n', [$key])->groupByRaw('1')->orderByRaw('1')->limit(2000)->get();
        }

        return ApiResponse::ok($rows->map(fn ($r) => ['value' => (string) $r->v, 'count' => (int) $r->n])->all());
    }

    /* ── Write ──────────────────────────────────────────────────────── */

    /**
     * Complete on Add Requisition: the rows, each with every required column
     * of its Entry Type, saved and numbered. Body: rows[] of {values}, and
     * draft_no — the draft the page was saving into, dropped once saved (a
     * half-done page is a Draft Requisition, DraftApiController).
     */
    public function store(Request $request): JsonResponse
    {
        $this->allow('prebudget.create');
        $sheet = $this->sheet();
        $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:200'], 'rows.*.values' => ['present', 'array'],
            'rows.*.order_id' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', Rule::in([PbRequisition::COMPLETE])], 'draft_no' => ['nullable', 'integer'], 'client_key' => ['nullable', 'string', 'max:64'],
        ]);
        $complete = true;
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $scope = $this->buyerScope();
        $created = DB::transaction(function () use ($request, $sheet, $batch, $by, $scope, $complete): array {
            $out = [];
            $orders = [];
            $prepared = [];
            foreach ($request->input('rows') as $i => $row) {
                $input = (array) $row['values'];
                $orderId = $row['order_id'] ?? null;
                if ($orderId) {
                    // A row made from an order: the order's cells as the order has them now (whatever the page sent) —
                    // on its first row with the quantity and price, on its other parts' rows without (they carry none).
                    $first = ! isset($orders[$orderId]);
                    $order = $orders[$orderId] ??= $this->pickable((string) $orderId, "rows.{$i}.order_id", lock: true);
                    $filled = OrderLink::fill($order, $sheet);
                    if ($problem = $this->orderProblem($order, $filled, $sheet)) {
                        throw ValidationException::withMessages(["rows.{$i}.order_id" => $problem]);
                    }
                    $input = $first ? array_merge($input, $filled) : array_merge($input, OrderLink::fillFor($order, $sheet, false), array_fill_keys(OrderLink::QTY, null));
                    if (empty($input['artwork']) && ($copy = OrderLink::copyArtwork($order, $sheet->companyId, $by))) {
                        $input['artwork'] = $copy->id;
                    }
                }
                [$values, $diff] = $sheet->put([], $input, "rows.{$i}.values");
                $prepared[] = [$i, $orderId, $values, $diff];
            }
            // A style's quantity and price are on one of its rows: the others may leave them empty.
            $priced = collect($prepared)->filter(fn ($p) => ! \App\Shared\Formula\Formula::blank($p[2]['quantity_ticket'] ?? null))
                ->map(fn ($p) => self::styleKey($p[2]))->flip();
            foreach ($prepared as [$i, $orderId, $values, $diff]) {
                $type = $this->typeOf($values, "rows.{$i}.values.entry_type");
                $buyer = $this->checkBuyer($sheet, $values, $scope, "rows.{$i}.values.buyer");
                if ($complete) {
                    $this->checkComplete($sheet, $values, "rows.{$i}.values", $i + 1, priced: isset($priced[self::styleKey($values)]));
                }
                $r = PbRequisition::query()->create([
                    'entry_type' => $type, 'status' => PbRequisition::DRAFT, 'buyer_id' => $buyer, 'os_order_id' => $orderId ?: null, 'values' => $values, 'version' => 1,
                    'created_by' => $by, 'updated_by' => $by,
                ]);
                $sheet->log($r, 'CREATE', $diff, $batch, $by);
                $out[] = $r;
            }
            if ($complete) {
                $this->markComplete($sheet, $out, $batch, $by);
            }
            if ($request->filled('draft_no') || $request->filled('client_key')) {
                // The page's draft, by its number or by the page's own key (a save the page did not see the number of).
                $draft = PbRequisitionDraft::query()->where(fn ($q) => $q->when($request->filled('draft_no'), fn ($w) => $w->orWhere('draft_no', (int) $request->input('draft_no')))
                    ->when($request->filled('client_key'), fn ($w) => $w->orWhere('client_key', (string) $request->input('client_key'))));
                if ($by !== null && ! $this->caller()->can('prebudget.view_all')) {
                    $draft->where('user_id', $by);
                }
                // Out of sight, its page key freed (the number is never given again).
                $draft->update(['client_key' => null, 'deleted_at' => now()]);
            }

            return $out;
        });

        return ApiResponse::created(['rows' => array_map(fn (PbRequisition $r) => $this->row($r->fresh(['creator:id,name', 'updater:id,name', 'order:id,serial_no,values']), $sheet), $created)]);
    }

    /**
     * The changed rows, saved together. Body: rows[] of {id, version, values}
     * (changed cells only). A row someone else saved since it was read comes
     * back under `conflicts`; the others are saved. A completed requisition
     * keeps every required column filled.
     */
    public function update(Request $request): JsonResponse
    {
        $this->allow('prebudget.update');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*.id' => ['required', 'string', 'size:26'], 'rows.*.version' => ['required', 'integer'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $scope = $this->buyerScope();
        [$saved, $conflicts] = DB::transaction(function () use ($request, $sheet, $batch, $by, $scope): array {
            $saved = [];
            $conflicts = [];
            foreach ($request->input('rows') as $i => $row) {
                $r = $this->scoped(null)->whereKey($row['id'])->lockForUpdate()->first();
                if (! $r) {
                    throw ValidationException::withMessages(["rows.{$i}.id" => __('prebudget.v.row_gone')]);
                }
                // In a budget waiting for approval or approved: locked (changed through the budget's Revise).
                $locking = DB::table('pb_budget_requisitions as br')->join('pb_budgets as b', 'b.id', '=', 'br.budget_id')->whereNull('b.deleted_at')
                    ->where('br.requisition_id', $r->id)->whereIn('b.status', \App\Modules\PreBudget\Models\PbBudget::LOCKING)->get(['b.budget_no', 'b.revision_no']);
                if ($locking->isNotEmpty()) {
                    throw ValidationException::withMessages(["rows.{$i}.id" => __('prebudget.final.v.requisition_locked', ['serial' => $r->serial_no, 'budget' => $locking->map(fn ($x) => $x->budget_no.($x->revision_no ? "-R{$x->revision_no}" : ''))->implode(', ')])]);
                }
                if ((int) $row['version'] !== $r->version) {
                    $conflicts[] = $this->row($r->load(['creator:id,name', 'updater:id,name', 'order:id,serial_no,values']), $sheet);

                    continue;
                }
                $saved[] = $this->apply($sheet, $r, (array) $row['values'], "rows.{$i}.values", $scope, $batch, $by, $i + 1);
            }

            return [$saved, $conflicts];
        });

        return ApiResponse::ok(['rows' => array_map(fn (PbRequisition $r) => $this->row($r->fresh(['creator:id,name', 'updater:id,name', 'order:id,serial_no,values']), $sheet), $saved), 'conflicts' => $conflicts]);
    }

    /** Copy: each ticked requisition again — a completed one completed under a new serial, a draft as a draft. */
    public function copy(Request $request): JsonResponse
    {
        $this->allow('prebudget.create');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $copies = DB::transaction(function () use ($request, $sheet, $batch, $by): array {
            $out = [];
            $complete = [];
            foreach ($this->scoped(null)->whereKey($request->input('ids'))->orderByRaw('serial_no nulls last')->orderBy('created_at')->get() as $src) {
                $values = $sheet->formulas->apply($src->values ?? [], RequisitionSheet::today());
                $r = PbRequisition::query()->create(['entry_type' => $src->entry_type, 'status' => PbRequisition::DRAFT, 'buyer_id' => $src->buyer_id, 'values' => $values, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
                $sheet->log($r, 'CREATE', $sheet->diff([], $values), $batch, $by);
                $out[] = $r;
                if ($src->status === PbRequisition::COMPLETE) {
                    $complete[] = $r;
                }
            }
            $this->markComplete($sheet, $complete, $batch, $by);

            return $out;
        });

        return ApiResponse::created(['rows' => array_map(fn (PbRequisition $r) => $this->row($r->fresh(['creator:id,name', 'updater:id,name', 'order:id,serial_no,values']), $sheet), $copies)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->allow('prebudget.delete');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        // A requisition in a budget stays: remove its style from the budget first.
        $inBudget = DB::table('pb_budget_requisitions as br')->join('pb_budgets as b', 'b.id', '=', 'br.budget_id')->whereNull('b.deleted_at')
            ->whereIn('br.requisition_id', $request->input('ids'))->distinct()->pluck('b.budget_no');
        if ($inBudget->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => __('prebudget.v.in_budget', ['budgets' => $inBudget->implode(', ')])]);
        }
        $n = DB::transaction(function () use ($request, $sheet, $batch, $by): int {
            $n = 0;
            foreach ($this->scoped(null)->whereKey($request->input('ids'))->get() as $r) {
                $sheet->log($r, 'DELETE', [], $batch, $by);
                $r->forceFill(['updated_by' => $by])->save();
                $r->delete();
                $n++;
            }

            return $n;
        });

        return ApiResponse::ok(['deleted' => $n]);
    }

    public function artwork(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allowEither('prebudget.create', 'prebudget.update');
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096']], [], ['file' => __('prebudget.req.artwork')]);
        $file = $files->handle($request->file('file'), 'pb_artwork', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'url' => "/api/files/{$file->id}/download"]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private function sheet(): RequisitionSheet
    {
        return new RequisitionSheet($this->caller()->companyId, $this->caller()->user);
    }

    /** @return list<string>|null */
    private function buyerScope(): ?array
    {
        $user = $this->caller()->auditUserId();

        return $user === null ? null : OrderScope::buyerIds($user, $this->caller()->can('prebudget.view_all'));
    }

    /** The rows this user reaches (their buyers' and their own), of one status or any. */
    private function scoped(?string $status): Builder
    {
        $query = PbRequisition::query()->when($status, fn ($q) => $q->where('status', $status));
        $scope = $this->buyerScope();
        if ($scope !== null) {
            $me = $this->caller()->auditUserId();
            $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me));
        }

        return $query;
    }

    private function typeOf(array $values, string $field): string
    {
        $type = RequisitionSheet::entryType($values);
        if ($type === null) {
            throw ValidationException::withMessages([$field => __('prebudget.v.entry_type')]);
        }

        return $type;
    }

    private function checkBuyer(RequisitionSheet $sheet, array $values, ?array $scope, string $field): ?string
    {
        $buyer = $sheet->buyerId($values);
        if ($scope !== null && $buyer !== null && ! in_array($buyer, $scope, true)) {
            throw ValidationException::withMessages([$field => __('prebudget.v.buyer_scope')]);
        }

        return $buyer;
    }

    /**
     * A row lacking a required column of its Entry Type is not completed: "Row 2 (2261582): Style, GSM … are needed".
     * `$priced`: another row of its style carries the quantity and price, so this part's row may leave them empty.
     */
    private function checkComplete(RequisitionSheet $sheet, array $values, string $field, int $n, mixed $style = null, bool $priced = false): void
    {
        $missing = $sheet->missing($priced ? $values + array_fill_keys(OrderLink::QTY, 1) : $values);
        if ($missing !== []) {
            throw ValidationException::withMessages([$field => __('prebudget.v.missing', ['row' => $n, 'style' => $style ?? ($values['style'] ?? '—'), 'columns' => implode(', ', $missing)])]);
        }
    }

    /** A row's style, as the quantity-and-price rule matches rows (case and spaces aside). */
    private static function styleKey(array $values): string
    {
        return \App\Modules\PreBudget\Support\MerchLists::squash(\App\Shared\Formula\Formula::text($values['style'] ?? ''));
    }

    /** Another live completed requisition of the same order (or, not made from one, the same style) carries the quantity and price. */
    private function pricedElsewhere(PbRequisition $r): bool
    {
        $others = PbRequisition::query()->where('status', PbRequisition::COMPLETE)->whereKeyNot($r->id)->whereRaw("coalesce(\"values\"->>'quantity_ticket', '') <> ''");
        if ($r->os_order_id !== null) {
            return $others->where('os_order_id', $r->os_order_id)->exists();
        }
        $key = self::styleKey($r->values ?? []);

        return $key !== '' && $others->whereNull('os_order_id')->get(['values'])->contains(fn ($o) => self::styleKey($o->values ?? []) === $key);
    }

    /** @var array<string, string> artwork file id => thumbnail data URI (the list's page) */
    private array $thumbs = [];

    /** @var array<string, string> order id => its first requisition's id (this request) */
    private array $firstRows = [];

    /** Whether a requisition is its order's first row (the one holding the quantity and price). */
    private function isFirstRow(PbRequisition $r): bool
    {
        if ($r->os_order_id === null) {
            return true;
        }
        $this->firstRows += OrderLink::firstRows([$r->os_order_id]);

        return ($this->firstRows[$r->os_order_id] ?? null) === $r->id;
    }

    /** The orders this user reaches in Order Summary (the same rule there and here). */
    private function orderScope(): Builder
    {
        return OrderLink::scoped($this->caller()->auditUserId(), $this->caller()->can('ordersummary.view_all') || $this->caller()->can('prebudget.view_all'));
    }

    /**
     * The order's values a requisition list does not hold (a Buyer not in the master, an old Dept…), as one
     * message — they are locked on the row, so they are corrected in Order Summary. Null when all fit.
     */
    private function orderProblem(OsOrder $order, array $filled, RequisitionSheet $sheet): ?string
    {
        $bad = [];
        foreach ($filled as $key => $value) {
            try {
                $sheet->normalize($sheet->column($key), $value, $key);
            } catch (ValidationException) {
                $bad[] = $sheet->column($key)->label.' “'.(is_array($value) ? implode(', ', $value) : $value).'”';
            }
        }
        $o = OrderLink::option($order);

        return $bad === [] ? null : __('prebudget.order.v.bad_values', ['ssl' => $o['ssl'], 'style' => $o['style'] ?? '—', 'values' => implode('; ', $bad)]);
    }

    /** An order this user may make a requisition from now, or a validation error under `$field`. */
    private function pickable(string $id, string $field, bool $lock = false): OsOrder
    {
        $order = $this->orderScope()->whereKey($id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $order) {
            throw ValidationException::withMessages([$field => __('prebudget.order.v.gone')]);
        }
        $why = OrderLink::blocked($order);
        if ($why !== null) {
            $o = OrderLink::option($order);
            throw ValidationException::withMessages([$field => __("prebudget.order.v.{$why}", ['style' => $o['style'] ?? '—', 'ssl' => $o['ssl'], 'type' => $o['order_type'] ?? ''])]);
        }

        return $order;
    }

    /** Drafts → COMPLETE, each with the next serial number of the company. @param  list<PbRequisition>  $rows */
    private function markComplete(RequisitionSheet $sheet, array $rows, string $batch, ?string $by): void
    {
        if ($rows === []) {
            return;
        }
        DB::table('companies')->where('id', $sheet->companyId)->lockForUpdate()->value('id');
        $next = (int) PbRequisition::withTrashed()->where('company_id', $sheet->companyId)->max('serial_no');
        foreach ($rows as $r) {
            $r->forceFill(['status' => PbRequisition::COMPLETE, 'serial_no' => ++$next, 'completed_at' => now(), 'updated_by' => $by])->save();
            $sheet->log($r, 'COMPLETE', [], $batch, $by);
        }
    }

    private function apply(RequisitionSheet $sheet, PbRequisition $r, array $input, string $field, ?array $scope, string $batch, ?string $by, int $n): PbRequisition
    {
        if ($r->order !== null) {
            // A cell the order has a value in is changed in Order Summary, not here.
            foreach (array_keys(OrderLink::fillFor($r->order, $sheet, $this->isFirstRow($r))) as $key) {
                if (array_key_exists($key, $input) && $sheet->diff([$key => $r->values[$key] ?? null], [$key => $sheet->normalize($sheet->column($key), $input[$key], "{$field}.{$key}")]) !== []) {
                    throw ValidationException::withMessages(["{$field}.{$key}" => __('prebudget.v.from_order', ['column' => $sheet->column($key)->label, 'ssl' => $r->order?->serial_no ?? '—'])]);
                }
            }
        }
        [$values, $diff] = $sheet->put($r->values ?? [], $input, $field);
        if ($diff === []) {
            return $r;
        }
        $type = $this->typeOf($values, "{$field}.entry_type");
        $buyer = array_key_exists('buyer', $diff) ? $this->checkBuyer($sheet, $values, $scope, "{$field}.buyer") : $r->buyer_id;
        if ($r->status === PbRequisition::COMPLETE) {
            $this->checkComplete($sheet, $values, $field, $n, priced: $this->pricedElsewhere($r));
        }
        $r->forceFill(['values' => $values, 'entry_type' => $type, 'buyer_id' => $buyer, 'version' => $r->version + 1, 'updated_by' => $by])->save();
        $sheet->log($r, 'EDIT', $diff, $batch, $by);

        return $r;
    }

    /** @return array<string, mixed> */
    private function row(PbRequisition $r, RequisitionSheet $sheet): array
    {
        return [
            'id' => $r->id, 'serial_no' => $r->serial_no, 'entry_type' => $r->entry_type, 'status' => $r->status, 'version' => $r->version, 'values' => $sheet->read($r),
            // Made from an order: its cells come from that Order Summary row (SSL), locked here.
            'order_id' => $r->os_order_id, 'order_ssl' => $r->order?->serial_no, 'order_locked' => $r->order ? array_keys(OrderLink::fillFor($r->order, $sheet, $this->isFirstRow($r))) : [],
            'created_by' => $r->creator?->name, 'updated_by' => $r->updater?->name ?? $r->creator?->name, 'updated_at' => $r->updated_at?->toIso8601String(),
            'artwork_url' => isset($r->values['artwork']) ? "/api/files/{$r->values['artwork']}/download" : null,
            'artwork_thumb' => $this->thumbs[$r->values['artwork'] ?? ''] ?? null,
        ];
    }

    /** The scoped rows of a status, narrowed by `q`, `f[key][]` header filters and `r[key][from|to]` ranges; `$except` leaves one column's filter out. */
    private function filtered(Request $request, RequisitionSheet $sheet, string $status, ?string $except = null): Builder
    {
        $query = $this->scoped($status);
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
                if ($c?->type === 'colorsize') {
                    // An Order Color cell holding a line of that colour.
                    $vals !== [] && $q->orWhereRaw("exists (select 1 from jsonb_array_elements(case when jsonb_typeof(\"values\"->?) = 'array' then \"values\"->? else '[]'::jsonb end) l where l->>'color' = any(?::text[]))", [$key, $key, '{'.implode(',', array_map(fn ($v) => '"'.addcslashes($v, '"\\').'"', $vals)).'}']);
                    $blank && $q->orWhereRaw("coalesce(jsonb_array_length(case when jsonb_typeof(\"values\"->?) = 'array' then \"values\"->? end), 0) = 0", [$key, $key]);

                    return;
                }
                if ($c?->type === 'multi') {
                    ListCells::filter($q, $key, $vals, $blank);

                    return;
                }
                if ($key === 'serial_no') {
                    $q->whereIn('serial_no', array_map('intval', $vals));

                    return;
                }
                if ($key === 'entry_date') {
                    $vals !== [] && $q->orWhereRaw("(created_at at time zone 'Asia/Dhaka')::date::text = any(?::text[])", ['{'.implode(',', $vals).'}']);

                    return;
                }
                if ($vals !== []) {
                    $q->orWhereRaw('"values"->>? = any(?::text[])', [$key, '{'.implode(',', array_map(fn ($v) => '"'.addcslashes($v, '"\\').'"', $vals)).'}']);
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
}
