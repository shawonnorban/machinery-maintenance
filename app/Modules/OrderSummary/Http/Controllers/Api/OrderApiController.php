<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Http\Controllers\Api;

use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\OrderNotices;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Modules\OrderSummary\Support\OrderStatus;
use App\Modules\OrderSummary\Support\Sheet;
use App\Modules\PreBudget\Support\OrderBudgetSync;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Support\Thumbnails;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaSet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\JobNo\JobNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Order Summary's sheet (AnnotechERP 32): what it is made of for this user
 * (groups, columns, sources, what they may edit), the order rows — filtered
 * by any column the way an Excel header filter does, sorted, paged, with the
 * summary cards — and every write: new rows, the changed rows saved together
 * (each carrying the version it was read at, so one person never silently
 * overwrites another), Bulk Update, Copy Row, Delete and the artwork image.
 * A user only reaches the orders of their own buyers (OrderScope).
 */
class OrderApiController extends ApiController
{
    /** What the saves of this request did beyond the order (a budget cancelled…), said on the page. @var list<string> */
    private array $notices = [];

    /** @var array<string, string> artwork file id => thumbnail data URI (the list's page) */
    private array $thumbs = [];

    public const PER_PAGE = [25, 50, 100, 200, 500];

    /* ── Meta ───────────────────────────────────────────────────────── */

    public function meta(): JsonResponse
    {
        $this->allow('ordersummary.view');
        $sheet = $this->sheet();
        $visible = $sheet->visible();
        $sources = [];
        foreach ($visible as $c) {
            if ($c->source && ! isset($sources[$c->source])) {
                $sources[$c->source] = $sheet->options($c->source);
            }
        }

        return ApiResponse::ok([
            'today' => Sheet::today(),
            'groups' => $sheet->groups->filter(fn ($g) => $g->active && $visible->contains('group_id', $g->id))
                ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'tone' => $g->tone])->values()->all(),
            'columns' => $visible->map(fn (OsColumn $c) => self::columnBody($c, $sheet) + ['access' => $sheet->access($c)])->values()->all(),
            'sources' => $sources,
            'seasons' => $sheet->options('lookup:SEASON'),
            'per_page' => self::PER_PAGE,
            'can' => [
                'create' => $this->caller()->can('ordersummary.create'), 'update' => $this->caller()->can('ordersummary.update'),
                'delete' => $this->caller()->can('ordersummary.delete'), 'columns' => $this->caller()->can('ordersummary.columns.manage'),
            ],
        ]);
    }

    /** A column as the sheet uses it; formulas in their stored form (the browser works them out live). */
    public static function columnBody(OsColumn $c, Sheet $sheet): array
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
        $this->allow('ordersummary.view');
        $sheet = $this->sheet();
        $request->validate([
            'per_page' => ['nullable', Rule::in([...array_map('strval', self::PER_PAGE), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'max:64'], 'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $query = $this->filtered($request, $sheet);
        $total = (clone $query)->count();
        $styles = $this->styleCount(clone $query);
        $totals = $this->totals(clone $query, $sheet);
        $all = $request->input('per_page') === 'all';
        $perPage = $all ? max($total, 1) : (int) $request->input('per_page', 50);
        $this->sort($query, $request, $sheet);
        // Budget Status worked out in the same query (Pre-Budget's requisition and budget of each order).
        $page = $query->select('os_orders.*')->selectRaw(OrderStatus::sql().' as '.OrderStatus::KEY)->with(['creator:id,name', 'updater:id,name'])->paginate($perPage, ['*'], 'page', (int) $request->input('page', 1));

        // The artwork thumbnails inline, so the pictures show with the page (not one request each, one after another).
        $this->thumbs = count($page->items()) <= Thumbnails::MAX_ROWS ? Thumbnails::many(collect($page->items())->map(fn (OsOrder $o) => $o->values['artwork'] ?? null)->all()) : [];

        return ApiResponse::ok([
            'rows' => collect($page->items())->map(fn (OsOrder $o) => $this->row($o, $sheet))->all(),
            'page' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'per_page' => $all ? 'all' : $perPage, 'total' => $total, 'all_total' => $this->scoped()->count(), 'styles' => $styles],
            'totals' => $totals,
        ]);
    }

    /**
     * View All → Excel (user, 2026-11-20): the filtered orders (only the
     * ticked ones when `ids[]` names some), in the list's order, in the
     * company's own book layout with each order's artwork (ExcelExport);
     * `cols` (comma-separated keys): only the columns shown in Column View.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->allow('ordersummary.view');
        @set_time_limit(600);
        @ini_set('memory_limit', '2048M');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['string'], 'cols' => ['nullable', 'string', 'max:8000'], 'sort' => ['nullable', 'string', 'max:64'], 'dir' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $query = $this->filtered($request, $sheet);
        if ($ids = array_filter((array) $request->input('ids', []))) {
            $query->whereIn('id', $ids);
        }
        $this->sort($query, $request, $sheet);
        $orders = $query->select('os_orders.*')->selectRaw(OrderStatus::sql().' as '.OrderStatus::KEY)->get();
        $buyers = $orders->map(fn (OsOrder $o) => trim((string) ($o->values['buyer'] ?? '')))->filter()->unique()->sort()->values()->all();
        // The columns shown in View All (Column View), when the page names them.
        $only = $request->filled('cols') ? array_values(array_filter(explode(',', (string) $request->input('cols')))) : null;
        $path = (new \App\Modules\OrderSummary\Support\ExcelExport($sheet))->build($orders, $buyers, $only);
        $name = 'Order-Summary-'.now(\App\Modules\OrderSummary\Support\Sheet::TIMEZONE)->format('Ymd-Hi').'.xlsx';

        return response()->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * The totals row: each SUM / AVG column over every filtered row (not just
     * this page) — Excel's SUBTOTAL above the headers. Columns the user may
     * not see and TODAY() columns (worked out per page) are left out.
     *
     * @return array<string, float|null>
     */
    private function totals(Builder $query, Sheet $sheet): array
    {
        $cols = $sheet->columns->filter(fn (OsColumn $c) => in_array($c->kpi, ['SUM', 'AVG'], true) && in_array($c->type, FormulaSet::NUMBER_TYPES, true)
            && $sheet->access($c) !== 'none' && ! $sheet->formulas->isVolatile($c->key))->values();
        if ($cols->isEmpty()) {
            return [];
        }
        $select = [];
        $bindings = [];
        foreach ($cols as $i => $c) {
            // Only numbers count; a stray text value is skipped, as SUBTOTAL skips text.
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

    /** Distinct values of one column under the other filters — an Excel header filter's list. */
    public function distinct(Request $request): JsonResponse
    {
        $this->allow('ordersummary.view');
        $sheet = $this->sheet();
        $key = (string) $request->input('column');
        $c = $sheet->column($key);
        if (! $c || $sheet->access($c) === 'none' || $c->type === 'image') {
            return ApiResponse::ok([]);
        }
        $query = $this->filtered($request, $sheet, except: $key);
        if ($sheet->formulas->isVolatile($key)) {
            // Day-dependent (Days Left In Hand…): nothing stored to group, so each row is worked out for today.
            $counts = [];
            $today = Sheet::today();
            foreach ($query->toBase()->select(['values'])->cursor() as $o) {
                $v = Formula::text($sheet->formulas->apply(json_decode((string) $o->values, true) ?? [], $today, true)[$key] ?? null);
                $counts[$v] = ($counts[$v] ?? 0) + 1;
            }
            uksort($counts, fn ($a, $b) => is_numeric($a) && is_numeric($b) ? (float) $a <=> (float) $b : strnatcasecmp((string) $a, (string) $b));
            $rows = collect($counts)->map(fn ($n, $v) => (object) ['v' => $v, 'n' => $n])->values();
        } elseif ($key === 'serial_no') {
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

    /**
     * The Job No dialog (Order Summary): `?factory=NCL` gives this year's prefix
     * (26NCL), the next serial over every module and the serials this sheet
     * already has; no factory gives every factory at once (loaded as the sheet opens).
     */
    public function jobNumbers(Request $request): JsonResponse
    {
        $this->allow('ordersummary.view');

        return ApiResponse::ok(JobNumber::answer('os_orders', $this->caller()->companyId, Sheet::today(), $request->query('factory')));
    }

    /* ── Write ──────────────────────────────────────────────────────── */

    /** New rows (Create New, Add Row). Body: rows[] of {values}. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('ordersummary.create');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:200'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $scope = $this->buyerScope();
        $created = DB::transaction(function () use ($request, $sheet, $batch, $by, $scope): array {
            DB::table('companies')->where('id', $sheet->companyId)->lockForUpdate()->value('id');
            $next = (int) OsOrder::withTrashed()->where('company_id', $sheet->companyId)->max('serial_no');
            $out = [];
            foreach ($request->input('rows') as $i => $row) {
                [$values, $diff] = $sheet->put([], (array) $row['values'], "rows.{$i}.values");
                $buyer = $this->checkBuyer($sheet, $values, $scope, "rows.{$i}.values.buyer");
                $o = OsOrder::query()->create(['serial_no' => ++$next, 'buyer_id' => $buyer, 'values' => $values, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
                $sheet->log($o, 'CREATE', $diff, $batch, $by);
                $out[] = $o;
            }

            return $out;
        });

        return ApiResponse::created(['rows' => array_map(fn (OsOrder $o) => $this->row($o->fresh(['creator:id,name', 'updater:id,name']), $sheet), $created)]);
    }

    /**
     * The changed rows, saved together. Body: rows[] of {id, version, values}
     * where values holds only the changed cells. A row someone else saved
     * since it was read is not touched: it comes back under `conflicts` with
     * what it holds now; the others are saved.
     */
    public function update(Request $request): JsonResponse
    {
        $this->allow('ordersummary.update');
        $sheet = $this->sheet();
        $request->validate(['rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*.id' => ['required', 'string', 'size:26'], 'rows.*.version' => ['required', 'integer'], 'rows.*.values' => ['present', 'array']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $scope = $this->buyerScope();
        [$saved, $conflicts] = DB::transaction(function () use ($request, $sheet, $batch, $by, $scope): array {
            $saved = [];
            $conflicts = [];
            foreach ($request->input('rows') as $i => $row) {
                $o = $this->scoped()->whereKey($row['id'])->lockForUpdate()->first();
                if (! $o) {
                    throw ValidationException::withMessages(["rows.{$i}.id" => __('ordersummary.v.row_gone')]);
                }
                if ((int) $row['version'] !== $o->version) {
                    $conflicts[] = $this->row($o->load(['creator:id,name', 'updater:id,name']), $sheet);

                    continue;
                }
                $saved[] = $this->apply($sheet, $o, (array) $row['values'], "rows.{$i}.values", $scope, $batch, $by);
            }

            return [$saved, $conflicts];
        });

        return ApiResponse::ok(['rows' => array_map(fn (OsOrder $o) => $this->row($o->fresh(['creator:id,name', 'updater:id,name']), $sheet), $saved), 'conflicts' => $conflicts, 'notice' => $this->notices === [] ? null : implode(' ', $this->notices)]);
    }

    /** Bulk Update: the same values on every ticked row. */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->allow('ordersummary.update');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['string', 'size:26'], 'values' => ['required', 'array', 'min:1']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $scope = $this->buyerScope();
        $count = DB::transaction(function () use ($request, $sheet, $batch, $by, $scope): int {
            $n = 0;
            foreach ($this->scoped()->whereKey($request->input('ids'))->lockForUpdate()->get() as $o) {
                $this->apply($sheet, $o, (array) $request->input('values'), 'values', $scope, $batch, $by);
                $n++;
            }

            return $n;
        });

        return ApiResponse::ok(['updated' => $count, 'notice' => $this->notices === [] ? null : implode(' ', $this->notices)]);
    }

    /** Copy Row: each ticked row again, under a new serial number. */
    public function copy(Request $request): JsonResponse
    {
        $this->allow('ordersummary.create');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        $copies = DB::transaction(function () use ($request, $sheet, $batch, $by): array {
            DB::table('companies')->where('id', $sheet->companyId)->lockForUpdate()->value('id');
            $next = (int) OsOrder::withTrashed()->where('company_id', $sheet->companyId)->max('serial_no');
            $out = [];
            foreach ($this->scoped()->whereKey($request->input('ids'))->orderBy('serial_no')->get() as $src) {
                $values = $sheet->formulas->apply($src->values ?? [], Sheet::today());
                $o = OsOrder::query()->create(['serial_no' => ++$next, 'buyer_id' => $src->buyer_id, 'values' => $values, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
                $sheet->log($o, 'CREATE', $sheet->diff([], $values), $batch, $by);
                $out[] = $o;
            }

            return $out;
        });

        return ApiResponse::created(['rows' => array_map(fn (OsOrder $o) => $this->row($o->fresh(['creator:id,name', 'updater:id,name']), $sheet), $copies)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->allow('ordersummary.delete');
        $sheet = $this->sheet();
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['string', 'size:26']]);
        $batch = (string) Str::ulid();
        $by = $this->caller()->auditUserId();
        // An order with a requisition stays (its requisition and budget hang on it): delete the requisition first.
        $linked = DB::table('pb_requisitions')->whereIn('pb_requisitions.os_order_id', $request->input('ids'))->whereNull('pb_requisitions.deleted_at')->join('os_orders', 'os_orders.id', '=', 'pb_requisitions.os_order_id')
            ->distinct()->orderBy('os_orders.serial_no')->pluck('os_orders.serial_no');
        if ($linked->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => __('ordersummary.v.has_requisition', ['ssl' => $linked->implode(', ')])]);
        }
        $n = DB::transaction(function () use ($request, $sheet, $batch, $by): int {
            $n = 0;
            foreach ($this->scoped()->whereKey($request->input('ids'))->get() as $o) {
                $sheet->log($o, 'DELETE', [], $batch, $by);
                $o->forceFill(['updated_by' => $by])->save();
                $o->delete();
                $n++;
            }

            return $n;
        });

        return ApiResponse::ok(['deleted' => $n]);
    }

    public function artwork(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allowEither('ordersummary.create', 'ordersummary.update');
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096']], [], ['file' => __('ordersummary.artwork')]);
        $file = $files->handle($request->file('file'), 'os_artwork', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'url' => "/api/files/{$file->id}/download"]);
    }

    /**
     * The orders a notification is about (the phone, 2026-11-27): with
     * `serials` (an OS_ORDERS_CHANGED notice's "#12, #13") those orders;
     * else this person's open orders the morning's OS_PENDING_WORK counts —
     * RFI/SD past (30 days), due within 7 days, booking pending. Soonest first.
     */
    public function attention(Request $request): JsonResponse
    {
        $this->allow('ordersummary.view');
        $data = $request->validate(['serials' => ['nullable', 'string', 'max:2000']]);
        $sheet = $this->sheet();
        $today = Formula::day(Sheet::today());
        $serials = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) ($data['serials'] ?? '')) ?: [])));
        $query = $this->scoped();
        if ($serials !== []) {
            $query->whereIn('serial_no', $serials);
        }
        $rows = [];
        $counts = ['overdue' => 0, 'week' => 0, 'booking' => 0];
        foreach ($query->limit($serials !== [] ? 500 : 5000)->get() as $o) {
            $v = $sheet->read($o);
            $flags = OrderNotices::open($v) ? OrderNotices::flags($v, $today) : ['overdue' => false, 'week' => false, 'booking' => false];
            if ($serials === [] && ! in_array(true, $flags, true)) {
                continue;
            }
            foreach ($flags as $k => $on) {
                $counts[$k] += $on ? 1 : 0;
            }
            $rows[] = [
                'serial_no' => $o->serial_no, 'buyer' => Formula::text($v['buyer'] ?? '') ?: null, 'style' => Formula::text($v['style_number_name'] ?? '') ?: null,
                'job_no' => Formula::text($v['job_no'] ?? '') ?: null, 'item' => Formula::text($v['item'] ?? '') ?: null, 'department' => Formula::text($v['department'] ?? '') ?: null,
                'qty' => Formula::num($v['quantity_pcs'] ?? null), 'fob_value' => Formula::num($v['fob_value'] ?? null),
                'rfi_sd' => is_string($v['po_wise_rfi_sd'] ?? null) ? substr($v['po_wise_rfi_sd'], 0, 10) : null,
                'ldd_etd' => is_string($v['po_wise_ldd_etd'] ?? null) ? substr($v['po_wise_ldd_etd'], 0, 10) : null,
                'days_left' => Formula::num($v['days_left_in_hand'] ?? null), 'order_type' => Formula::text($v['order_type'] ?? '') ?: null,
                'flags' => array_keys(array_filter($flags)),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['days_left'] ?? PHP_INT_MAX, $a['serial_no']] <=> [$b['days_left'] ?? PHP_INT_MAX, $b['serial_no']]);

        return ApiResponse::ok(['counts' => $counts, 'rows' => $rows]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private function sheet(): Sheet
    {
        return new Sheet($this->caller()->companyId, $this->caller()->user);
    }

    /** @return list<string>|null */
    private function buyerScope(): ?array
    {
        $user = $this->caller()->auditUserId();

        return $user === null ? null : OrderScope::buyerIds($user, $this->caller()->can('ordersummary.view_all'));
    }

    /** The rows this user reaches: their buyers' orders, and rows they entered. */
    private function scoped(): Builder
    {
        $query = OsOrder::query();
        $scope = $this->buyerScope();
        if ($scope !== null) {
            $me = $this->caller()->auditUserId();
            $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me));
        }

        return $query;
    }

    /** A buyer outside the user's buyers cannot be put on a row. */
    private function checkBuyer(Sheet $sheet, array $values, ?array $scope, string $field): ?string
    {
        $buyer = $sheet->buyerId($values);
        if ($scope !== null && $buyer !== null && ! in_array($buyer, $scope, true)) {
            throw ValidationException::withMessages([$field => __('ordersummary.v.buyer_scope')]);
        }

        return $buyer;
    }

    private function apply(Sheet $sheet, OsOrder $o, array $input, string $field, ?array $scope, string $batch, ?string $by): OsOrder
    {
        [$values, $diff] = $sheet->put($o->values ?? [], $input, $field);
        if ($diff === []) {
            return $o;
        }
        $buyer = array_key_exists('buyer', $diff) ? $this->checkBuyer($sheet, $values, $scope, "{$field}.buyer") : $o->buyer_id;
        $o->forceFill(['values' => $values, 'buyer_id' => $buyer, 'version' => $o->version + 1, 'updated_by' => $by]);
        // Quantity, PAC or FOB changed (or the order dropped) after its budget was sent or approved: that budget is
        // cancelled and budgeted again — only for one allowed to (user, 2026-11-24).
        $cancels = OrderBudgetSync::wouldCancel($o, $diff);
        if ($cancels->isNotEmpty() && ! $this->caller()->can(OrderBudgetSync::PERMISSION)) {
            $key = array_values(array_intersect([...OrderBudgetSync::MONEY, 'order_type'], array_keys($diff)))[0] ?? 'quantity_ticket';
            throw ValidationException::withMessages(["{$field}.{$key}" => __('ordersummary.v.budget_locked', ['ssl' => $o->serial_no, 'column' => $sheet->column($key)?->label ?? $key, 'budgets' => $cancels->map->label()->implode(', ')])]);
        }
        $o->save();
        $sheet->log($o, 'EDIT', $diff, $batch, $by);
        // Its requisition rows follow the order; a budget cancelled says so on the page.
        if ($notice = OrderBudgetSync::changed($o, $diff, $by)) {
            $this->notices[] = $notice;
        }

        return $o;
    }

    /** @return array<string, mixed> */
    private function row(OsOrder $o, Sheet $sheet): array
    {
        return [
            'id' => $o->id, 'serial_no' => $o->serial_no, 'version' => $o->version, 'values' => array_filter([...$sheet->read($o), OrderStatus::KEY => OrderStatus::of($o)], fn ($v) => $v !== null),
            'created_by' => $o->creator?->name, 'updated_by' => $o->updater?->name ?? $o->creator?->name, 'updated_at' => $o->updated_at?->toIso8601String(),
            'artwork_url' => isset($o->values['artwork']) ? "/api/files/{$o->values['artwork']}/download" : null,
            'artwork_thumb' => $this->thumbs[$o->values['artwork'] ?? ''] ?? null,
        ];
    }

    /**
     * The scoped rows narrowed by the request: `q` (any text), `f[key][]`
     * (header filter values; "" is the blanks), `r[key][from|to]` (a date or
     * number range), `season`. `$except` leaves one column's own filter out.
     */
    private function filtered(Request $request, Sheet $sheet, ?string $except = null): Builder
    {
        $query = $this->scoped();
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereRaw('LOWER("values"::text) LIKE ?', ['%'.mb_strtolower(addcslashes($search, '\\%_')).'%']);
        }
        if ($request->filled('season')) {
            $query->whereRaw('"values"->>\'season_quarter\' = ?', [(string) $request->input('season')]);
        }
        $late = []; // filters on day-dependent columns: no stored value, so matched row by row after the SQL ones
        foreach ((array) $request->input('f', []) as $key => $list) {
            $key = (string) $key;
            $c = $sheet->column($key);
            if ($key === $except || (! $c && $key !== 'serial_no')) {
                continue;
            }
            if ($c && $sheet->formulas->isVolatile($key)) {
                $late[] = ['f', $key, array_map('strval', (array) $list), $c];

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
            if (! $c || $key === $except) {
                continue;
            }
            if ($sheet->formulas->isVolatile((string) $key)) {
                $late[] = ['r', (string) $key, (array) $range, $c];

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
                    : $query->whereRaw("{$this->cellText((string) $key)[0]} {$op} ?", [...$this->cellText((string) $key)[1], (string) $v]);
            }
        }
        foreach (['entry_from' => '>=', 'entry_to' => '<='] as $field => $op) {
            if ($request->filled($field)) {
                // created_at is stored UTC (no zone): read it as UTC first, then take its Dhaka day.
                $query->whereRaw("((created_at at time zone 'UTC') at time zone 'Asia/Dhaka')::date {$op} ?", [(string) $request->input($field)]);
            }
        }
        if ($late !== []) {
            $query->whereIn('id', $this->volatileMatches(clone $query, $sheet, $late));
        }

        return $query;
    }

    /**
     * The ids of the rows whose day-dependent cells, worked out for today, pass every late filter:
     * `f` a list of shown values ("" the blanks), `r` a from/to range (numbers as numbers).
     *
     * @param  list<array{0: string, 1: string, 2: array, 3: OsColumn}>  $filters
     * @return list<string>
     */
    private function volatileMatches(Builder $query, Sheet $sheet, array $filters): array
    {
        $today = Sheet::today();
        $ids = [];
        foreach ($query->toBase()->select(['id', 'values'])->cursor() as $o) {
            $v = $sheet->formulas->apply(json_decode((string) $o->values, true) ?? [], $today, true);
            foreach ($filters as [$kind, $key, $arg, $c]) {
                $cell = $v[$key] ?? null;
                if ($kind === 'f') {
                    if (! in_array(Formula::text($cell), $arg, true)) {
                        continue 2;
                    }

                    continue;
                }
                $numeric = in_array($c->type, FormulaSet::NUMBER_TYPES, true);
                $x = $numeric ? Formula::num($cell) : (Formula::blank($cell) ? null : Formula::text($cell));
                if ($x === null) {
                    continue 2;
                }
                foreach (['from' => 1, 'to' => -1] as $side => $sign) {
                    $edge = $arg[$side] ?? null;
                    if ($edge === null || $edge === '') {
                        continue;
                    }
                    $cmp = $numeric ? $x <=> (float) $edge : strcmp((string) $x, (string) $edge);
                    if ($cmp * $sign < 0) {
                        continue 3;
                    }
                }
            }
            $ids[] = $o->id;
        }

        return $ids;
    }

    /** How many styles the rows hold (each name of Style Number / Name once). */
    private function styleCount(Builder $query): int
    {
        $names = $query->toBase()->selectRaw("jsonb_array_elements_text(case when jsonb_typeof(\"values\"->'style_number_name') = 'array' then \"values\"->'style_number_name' else '[]'::jsonb end) as s");

        return (int) DB::query()->fromSub($names, 'x')->distinct()->count('s');
    }

    /**
     * A cell as SQL text, for the header filters: a stored cell is its "values" entry; Last Update
     * Date is the row's own updated_at (stored UTC) as its Dhaka day — what Sheet::read shows.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function cellText(string $key): array
    {
        return match ($key) {
            'last_update' => ["to_char((updated_at at time zone 'UTC') at time zone 'Asia/Dhaka', 'YYYY-MM-DD')", []],
            OrderStatus::KEY => [OrderStatus::sql(), []], // worked out from Pre-Budget, not stored
            default => ['"values"->>?', [$key]],
        };
    }

    private function sort(Builder $query, Request $request, Sheet $sheet): void
    {
        $key = (string) $request->input('sort', '');
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $c = $sheet->column($key);
        if ($c && ! $sheet->formulas->isVolatile($key) && ! in_array($c->type, ['image', 'system'], true)) {
            in_array($c->type, FormulaSet::NUMBER_TYPES, true)
                ? $query->orderByRaw("nullif(\"values\"->>?, '')::numeric {$dir} nulls last", [$key])
                : $query->orderByRaw("\"values\"->>? {$dir} nulls last", [$key]);
        } elseif ($key === 'last_update') {
            $query->orderBy('updated_at', $dir);
        } elseif ($key === OrderStatus::KEY) {
            $query->orderByRaw(OrderStatus::sql()." {$dir} nulls last");
        }
        $query->orderBy('serial_no', $key === 'serial_no' ? $dir : 'desc');
    }
}
