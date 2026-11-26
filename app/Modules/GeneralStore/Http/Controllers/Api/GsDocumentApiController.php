<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Http\Controllers\Api;

use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsDepartment;
use App\Modules\GeneralStore\Models\GsDocument;
use App\Modules\GeneralStore\Models\GsDocumentLine;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsRequisition;
use App\Modules\GeneralStore\Models\GsRequisitionLine;
use App\Modules\GeneralStore\Models\GsStockMovement;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsSupplier;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\GeneralStore\Support\GeneralStoreDefaults;
use App\Modules\GeneralStore\Support\GsBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * General Store → Inventory (AnnotechERP 10/03), as Brickland keeps it: one
 * document form per screen — Opening Stock, Stock Receive, Issue, Return,
 * Adjustment and Transfer — with its item lines, posted to stock the moment
 * it is saved; the list beneath it with its filters and Excel; and the
 * document itself, which is printed, or cancelled with a reason (its stock
 * movement reversed, the document kept as Cancelled — never deleted).
 *
 * Stock never goes below zero: an issue, a transfer, a decrease and a
 * cancellation are each refused when the store does not hold enough. A
 * return against an issue takes back at most what that issue gave and is
 * not yet back. An issue's unit price starts at the last purchase price; a
 * price typed over it is written to the audit trail.
 */
class GsDocumentApiController extends ApiController
{
    /** Route segment => document type. */
    public const KINDS = ['opening' => 'OPENING', 'receive' => 'RECEIVE', 'issue' => 'ISSUE', 'return' => 'RETURN', 'adjustment' => 'ADJUST', 'transfer' => 'TRANSFER'];

    /** Adjustment types that take stock away; COUNT and OTHER go either way. */
    private const DECREASING = ['DECREASE', 'DAMAGE', 'LOST'];

    /** What a form picks from, and the number its next document takes. */
    public function options(string $kind): JsonResponse
    {
        $type = $this->type($kind);
        $this->allow('store.inventory.view');
        $this->ensureLists();
        $items = GsItem::query()->where('active', true)->orderBy('name')->get();
        $units = GsUnit::query()->pluck('code', 'id');
        $stock = GsBook::stock($items->pluck('id'));
        $prices = GsBook::lastPrices($items);
        $stores = GsStore::query()->where('active', true)->get();

        return ApiResponse::ok([
            'type' => $type, 'next_no' => GsBook::previewNumber($type, now()->toDateString()),
            // The form lists the default store first, as Brickland does; the filter lists them by name with their codes.
            'stores' => $stores->sortBy(fn (GsStore $s) => [! $s->is_default, $s->name])->values()->map(fn (GsStore $s) => ['value' => $s->id, 'label' => $s->name, 'is_default' => $s->is_default])->all(),
            'store_filter' => $stores->sortBy('name')->values()->map(fn (GsStore $s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'categories' => GsCategory::query()->where('active', true)->orderBy('name')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'items' => $items->map(fn (GsItem $i) => [
                'value' => $i->id, 'label' => "{$i->name} ({$i->item_code})", 'code' => $i->item_code, 'name' => $i->name, 'category_id' => $i->gs_category_id,
                'unit' => $units[$i->gs_unit_id] ?? '', 'price' => $prices[$i->id] ?? null, 'stock' => (object) array_filter($stock[$i->id] ?? [], fn ($q) => abs($q) > 0.00005),
            ])->values()->all(),
            'suppliers' => in_array($type, ['RECEIVE'], true) ? GsSupplier::query()->where('active', true)->orderBy('name')->get()->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all() : [],
            'departments' => in_array($type, ['ISSUE', 'RETURN'], true) ? GsDepartment::query()->where('active', true)->orderBy('name')->get()->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all() : [],
            'issues' => $type === 'RETURN' ? $this->issueOptions() : [],
            // Approved requisitions the store may still issue against.
            'requisitions' => $type === 'ISSUE' ? $this->requisitionOptions() : [],
            'direct_issue' => GsBook::settings()->direct_issue, 'receiver_required' => GsBook::settings()->receiver_required, 'voucher' => GsBook::voucher(),
            'adjustment_types' => $type === 'ADJUST' ? GsDocument::ADJUSTMENT_TYPES : [],
        ]);
    }

    /** The list beneath a form, by its filters. */
    public function index(Request $request, string $kind): JsonResponse
    {
        $type = $this->type($kind);
        $this->allow('store.inventory.view');

        return ApiResponse::ok(['rows' => $this->rows($type, $this->filters($request))]);
    }

    /** The list as an Excel workbook. */
    public function excel(Request $request, string $kind): StreamedResponse
    {
        $type = $this->type($kind);
        $this->allow('store.inventory.view');
        $rows = $this->rows($type, $this->filters($request));
        $columns = ['doc_no', 'date', ...$this->extraColumns($type), 'items', 'total_qty', 'value', 'status', 'user'];
        GsBook::audit('REPORT_EXPORT', GsDocument::SCREEN[$type], null, GsBook::typeName($type, 'en').' list exported ('.count($rows).' rows)');

        return response()->streamDownload(function () use ($rows, $columns): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => __("store.inv.col.{$c}"), $columns)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => $c === 'status' ? __("store.inv.status.{$r['status']}") : ($c === 'type' && $r['type'] ? __("store.inv.adjust.{$r['type']}") : $r[$c]), $columns)));
            }
            $writer->close();
        }, 'store-'.strtolower($type).'-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** One document, by its number. */
    public function show(string $document): JsonResponse
    {
        $this->allow('store.inventory.view');

        return ApiResponse::ok($this->payload($this->find($document)));
    }

    /** What may still come back against an issue: its store, department and every item with what is returnable. */
    public function returnable(string $document): JsonResponse
    {
        $this->allow('store.inventory.view');
        $issue = $this->find($document);
        if ($issue->doc_type !== 'ISSUE' || $issue->status !== 'POSTED') {
            throw ValidationException::withMessages(['against' => __('store.inv.not_an_issue', ['no' => $issue->doc_no])]);
        }
        $left = $this->returnableOf($issue);
        $items = GsItem::query()->whereIn('id', array_keys($left))->get()->keyBy('id');

        return ApiResponse::ok([
            'doc_no' => $issue->doc_no, 'store_id' => $issue->gs_store_id, 'department_id' => $issue->gs_department_id,
            'lines' => collect($left)->map(fn (array $l, string $id) => ['item_id' => $id, 'name' => $items[$id]->name, 'issued' => $l['issued'], 'returned' => $l['returned'], 'eligible' => $l['eligible']])->values()->all(),
        ]);
    }

    /** Save: the document is posted to stock at once. */
    public function store(Request $request, string $kind): JsonResponse
    {
        $type = $this->type($kind);
        $this->allow('store.inventory.post');
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'doc_date' => ['required', 'date'],
            'gs_store_id' => ['required', $in('stores')->where('active', true)],
            'to_store_id' => $type === 'TRANSFER' ? ['required', $in('stores')->where('active', true), 'different:gs_store_id'] : ['prohibited'],
            'gs_supplier_id' => $type === 'RECEIVE' ? ['nullable', $in('gs_suppliers')] : ['prohibited'],
            'challan_no' => ['nullable', 'string', 'max:64'],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'gs_department_id' => match ($type) {
                'ISSUE' => ['required', $in('gs_departments')], 'RETURN' => ['nullable', $in('gs_departments')], default => ['prohibited']
            },
            'person' => ['nullable', 'string', 'max:120'],
            'against' => $type === 'RETURN' ? ['nullable', 'string', 'max:24'] : ['prohibited'],
            'requisition' => $type === 'ISSUE' ? ['nullable', 'string', 'max:24'] : ['prohibited'],
            'adjustment_type' => $type === 'ADJUST' ? ['required', Rule::in(GsDocument::ADJUSTMENT_TYPES)] : ['prohibited'],
            'remarks' => [$type === 'ADJUST' ? 'required' : 'nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.gs_item_id' => ['nullable', 'string'], 'lines.*.quantity' => ['nullable'], 'lines.*.unit_price' => ['nullable'],
            'lines.*.line_remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'doc_date.required' => __('store.inv.v.date'), 'gs_store_id.required' => __($type === 'TRANSFER' ? 'store.inv.v.from_store' : 'store.inv.v.store'),
            'to_store_id.required' => __('store.inv.v.to_store'), 'to_store_id.different' => __('store.inv.v.same_store'),
            'gs_department_id.required' => __('store.inv.v.department'), 'adjustment_type.required' => __('store.inv.v.adjustment_type'),
            'remarks.required' => __('store.inv.v.reason'),
        ]);
        ksort($data['lines']);
        $against = $type === 'RETURN' && trim((string) ($data['against'] ?? '')) !== '' ? $this->find(strtoupper(trim($data['against']))) : null;
        if ($against && ($against->doc_type !== 'ISSUE' || $against->status !== 'POSTED')) {
            throw ValidationException::withMessages(['against' => __('store.inv.not_an_issue', ['no' => $against->doc_no])]);
        }
        // A return against an issue goes back to that issue's store and department.
        if ($against) {
            $data['gs_store_id'] = $against->gs_store_id;
            $data['gs_department_id'] = $against->gs_department_id;
        }
        // An issue against an approved requisition goes from its store to its department; without one, only if Settings allow it.
        $settings = GsBook::settings();
        $requisition = null;
        if ($type === 'ISSUE') {
            $no = strtoupper(trim((string) ($data['requisition'] ?? '')));
            if ($no !== '') {
                $requisition = GsRequisition::query()->where('req_no', $no)->first();
                if (! $requisition || ! in_array($requisition->status, GsRequisition::ISSUABLE, true)) {
                    throw ValidationException::withMessages(['requisition' => __('store.req.v.not_issuable', ['no' => $no])]);
                }
                $data['gs_store_id'] = $requisition->gs_store_id;
                $data['gs_department_id'] = $requisition->gs_department_id;
            } elseif (! $settings->direct_issue) {
                throw ValidationException::withMessages(['requisition' => __('store.req.v.direct_off')]);
            }
            if ($settings->receiver_required && trim((string) ($data['person'] ?? '')) === '') {
                throw ValidationException::withMessages(['person' => __('store.req.v.receiver')]);
            }
        }

        $doc = DB::transaction(function () use ($type, $data, $against, $requisition, $company): GsDocument {
            // One posting at a time per company: the stock read and the stock written agree.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["gs_stock:{$company}"]);
            $lines = $this->checkedLines($type, $data, $against, $requisition);
            $date = $data['doc_date'];
            $doc = GsDocument::query()->create([
                'doc_type' => $type, 'doc_no' => GsBook::nextNumber($type, $date), 'doc_date' => $date, 'gs_store_id' => $data['gs_store_id'],
                'to_store_id' => $data['to_store_id'] ?? null, 'gs_supplier_id' => $data['gs_supplier_id'] ?? null,
                'challan_no' => $type === 'RECEIVE' ? ($data['challan_no'] ?? null) : null, 'reference_no' => $type === 'ISSUE' ? null : ($data['reference_no'] ?? null),
                'gs_department_id' => $data['gs_department_id'] ?? null, 'person' => in_array($type, ['ISSUE', 'RETURN'], true) ? ($data['person'] ?? null) : null,
                'against_id' => $against?->id, 'requisition_id' => $requisition?->id, 'adjustment_type' => $data['adjustment_type'] ?? null, 'remarks' => $data['remarks'] ?? null,
                'status' => 'POSTED', 'created_by' => $this->caller()->auditUserId(),
            ]);
            foreach ($lines as $i => $l) {
                $line = GsDocumentLine::query()->create($l['row'] + ['gs_document_id' => $doc->id, 'sort_order' => $i + 1]);
                foreach ($this->legs($doc, (float) $line->movement_qty) as [$store, $q]) {
                    GsStockMovement::query()->create([
                        'gs_item_id' => $line->gs_item_id, 'gs_store_id' => $store, 'gs_document_id' => $doc->id, 'movement_type' => $type, 'quantity' => $q,
                        'unit_price' => $line->unit_price, 'moved_on' => $date, 'reference' => $doc->doc_no, 'is_reversal' => false, 'created_by' => $this->caller()->auditUserId(),
                    ]);
                }
                if ($l['price_changed']) {
                    GsBook::audit('PRICE_CHANGE', GsDocument::SCREEN[$type], $doc->doc_no, __('store.inv.audit.price', [
                        'type' => GsBook::typeName($type, 'en'), 'no' => $doc->doc_no, 'item' => $l['name'],
                        'from' => '৳'.GsBook::qty((float) $line->suggested_price), 'to' => '৳'.GsBook::qty((float) $line->unit_price),
                    ], 'en'));
                }
            }
            $totals = $this->totals(GsDocumentLine::query()->where('gs_document_id', $doc->id)->get());
            GsBook::audit('CREATE', GsDocument::SCREEN[$type], $doc->doc_no, __('store.inv.audit.create', [
                'type' => GsBook::typeName($type, 'en'), 'no' => $doc->doc_no, 'n' => count($lines), 'qty' => GsBook::qty($totals['qty']), 'value' => '৳'.GsBook::qty($totals['value']),
            ], 'en'));
            if ($requisition) {
                GsBook::refreshRequisition($requisition);
            }

            return $doc;
        });

        return ApiResponse::created($this->payload($doc->fresh()));
    }

    /** Cancel / Reverse: the stock movement posted back, the document kept as Cancelled. */
    public function cancel(Request $request, string $document): JsonResponse
    {
        $this->allow('store.inventory.cancel');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('store.inv.v.reason')]);
        $doc = $this->find($document);
        if ($doc->status !== 'POSTED') {
            throw ValidationException::withMessages(['document' => __('store.inv.already_cancelled', ['no' => $doc->doc_no])]);
        }
        if ($doc->doc_type === 'ISSUE' && GsDocument::query()->where('against_id', $doc->id)->where('status', 'POSTED')->exists()) {
            throw ValidationException::withMessages(['document' => __('store.inv.has_returns', ['no' => $doc->doc_no])]);
        }
        DB::transaction(function () use ($doc, $data): void {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['gs_stock:'.app(TenantContext::class)->companyId()]);
            $moves = GsStockMovement::query()->where('gs_document_id', $doc->id)->where('is_reversal', false)->get();
            // What the document brought into each store must still be there to take back out.
            $items = GsItem::query()->whereIn('id', $moves->pluck('gs_item_id'))->get()->keyBy('id');
            $units = GsUnit::query()->pluck('code', 'id');
            foreach ($moves->groupBy(fn ($m) => $m->gs_item_id.'|'.$m->gs_store_id) as $set) {
                $net = round((float) $set->sum('quantity'), 4);
                $first = $set->first();
                $have = GsBook::stockIn($first->gs_item_id, $first->gs_store_id);
                if ($net > 0 && $have + 0.00005 < $net) {
                    throw ValidationException::withMessages(['document' => __('store.inv.cancel_short', [
                        'qty' => GsBook::qty(max(0, $have)), 'unit' => $units[$items[$first->gs_item_id]->gs_unit_id] ?? '', 'item' => $items[$first->gs_item_id]->name,
                        'store' => GsStore::query()->whereKey($first->gs_store_id)->value('name'),
                    ])]);
                }
            }
            $today = now()->toDateString();
            foreach ($moves as $m) {
                GsStockMovement::query()->create([
                    'gs_item_id' => $m->gs_item_id, 'gs_store_id' => $m->gs_store_id, 'gs_document_id' => $doc->id, 'movement_type' => $m->movement_type,
                    'quantity' => -1 * (float) $m->quantity, 'unit_price' => $m->unit_price, 'moved_on' => $today, 'reference' => $doc->doc_no, 'is_reversal' => true,
                    'created_by' => $this->caller()->auditUserId(),
                ]);
            }
            $doc->update(['status' => 'CANCELLED', 'cancelled_by' => $this->caller()->auditUserId(), 'cancelled_at' => now(), 'cancel_reason' => $data['reason']]);
            GsBook::audit('UPDATE', GsDocument::SCREEN[$doc->doc_type], $doc->doc_no, __('store.inv.audit.cancel', ['type' => GsBook::typeName($doc->doc_type, 'en'), 'no' => $doc->doc_no, 'reason' => $data['reason']], 'en'));
            // A cancelled issue gives its requisition the quantity back to issue.
            if ($doc->requisition_id && ($req = GsRequisition::query()->find($doc->requisition_id))) {
                GsBook::refreshRequisition($req);
            }
        });

        return ApiResponse::ok($this->payload($doc->fresh()));
    }

    /**
     * The lines a save posts, each checked as Brickland checks it: an item and
     * a quantity on every line used, a unit price on an issue, never more out
     * of a store than it holds, never more back than an issue gave, never more
     * against a requisition than it approved and is not yet issued.
     *
     * @return list<array{row: array<string, mixed>, name: string, price_changed: bool}>
     */
    private function checkedLines(string $type, array $data, ?GsDocument $against, ?GsRequisition $requisition = null): array
    {
        $given = collect(array_values($data['lines']))->map(fn ($l) => is_array($l) ? $l : [])
            ->filter(fn (array $l) => trim((string) ($l['gs_item_id'] ?? '')) !== '' || trim((string) ($l['quantity'] ?? '')) !== '');
        if ($given->filter(fn (array $l) => trim((string) ($l['gs_item_id'] ?? '')) !== '')->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('store.inv.v.pick_item')]);
        }
        $items = GsItem::query()->where('active', true)->whereIn('id', $given->pluck('gs_item_id')->filter()->all())->get()->keyBy('id');
        $units = GsUnit::query()->pluck('code', 'id');
        $prices = GsBook::lastPrices($items->values());
        $store = $data['gs_store_id'];
        $issuePrices = $against ? GsDocumentLine::query()->where('gs_document_id', $against->id)->pluck('unit_price', 'gs_item_id') : collect();
        $errors = [];
        $out = [];
        foreach ($given->values() as $i => $l) {
            $n = $i + 1;
            $item = $items[$l['gs_item_id'] ?? ''] ?? null;
            if (! $item) {
                $errors["lines.{$i}.gs_item_id"] = __('store.inv.v.line_item', ['n' => $n]);

                continue;
            }
            $raw = $l['quantity'] ?? null;
            $count = $type === 'ADJUST' && $data['adjustment_type'] === 'COUNT';
            $signed = $type === 'ADJUST' && $data['adjustment_type'] === 'OTHER';
            if (! is_numeric($raw) || ($count ? (float) $raw < 0 : ($signed ? abs((float) $raw) < 0.00005 : (float) $raw <= 0))) {
                $errors["lines.{$i}.quantity"] = __($count ? 'store.inv.v.line_count' : ($signed ? 'store.inv.v.line_nonzero' : 'store.inv.v.line_qty'), ['n' => $n]);

                continue;
            }
            $qty = round((float) $raw, 4);
            $price = $l['unit_price'] ?? null;
            if ($price !== null && $price !== '' && (! is_numeric($price) || (float) $price < 0)) {
                $errors["lines.{$i}.unit_price"] = __('store.inv.v.line_price', ['n' => $n]);

                continue;
            }
            if ($type === 'ISSUE' && ($price === null || $price === '')) {
                $errors["lines.{$i}.unit_price"] = __('store.inv.v.line_price_required', ['n' => $n]);

                continue;
            }
            $movement = match ($type) {
                'ISSUE' => -$qty,
                'TRANSFER' => $qty,
                'ADJUST' => match (true) {
                    in_array($data['adjustment_type'], self::DECREASING, true) => -$qty,
                    $count => round($qty - GsBook::stockIn($item->id, $store) - collect($out)->where('row.gs_item_id', $item->id)->sum('row.movement_qty'), 4),
                    default => $qty,
                },
                default => $qty,
            };
            if ($count && abs($movement) < 0.00005) {
                $errors["lines.{$i}.quantity"] = __('store.inv.v.line_no_difference', ['n' => $n]);

                continue;
            }
            $suggested = $prices[$item->id] ?? null;
            $unitPrice = match ($type) {
                'RECEIVE', 'OPENING', 'ISSUE' => $price === null || $price === '' ? null : round((float) $price, 4),
                'RETURN' => isset($issuePrices[$item->id]) ? (float) $issuePrices[$item->id] : $suggested,
                default => $suggested,
            };
            $out[] = [
                'row' => [
                    'gs_item_id' => $item->id, 'quantity' => $qty, 'movement_qty' => $movement, 'unit_price' => $unitPrice,
                    'suggested_price' => $type === 'ISSUE' ? $suggested : null, 'line_remarks' => trim((string) ($l['line_remarks'] ?? '')) ?: null,
                ],
                'name' => $item->name, 'n' => $n, 'unit' => $units[$item->gs_unit_id] ?? '',
                'price_changed' => $type === 'ISSUE' && $suggested !== null && abs((float) $unitPrice - $suggested) > 0.00005,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Against a requisition: only its approved items, and no more than is left of each.
        if ($requisition) {
            $issued = GsBook::issuedAgainst($requisition);
            $approved = GsRequisitionLine::query()->where('gs_requisition_id', $requisition->id)->where('approved_qty', '>', 0)->get()
                ->groupBy('gs_item_id')->map(fn ($set) => (float) $set->sum('approved_qty'));
            $taking = [];
            foreach ($out as $l) {
                $id = $l['row']['gs_item_id'];
                if (! isset($approved[$id])) {
                    throw ValidationException::withMessages(['lines.'.($l['n'] - 1).'.gs_item_id' => __('store.inv.v.not_on_issue', ['n' => $l['n'], 'item' => $l['name'], 'no' => $requisition->req_no])]);
                }
                $taking[$id] = ($taking[$id] ?? 0) + $l['row']['quantity'];
                $left = round(max(0, $approved[$id] - ($issued[$id] ?? 0)), 4);
                if ($taking[$id] > $left + 0.00005) {
                    throw ValidationException::withMessages(['lines.'.($l['n'] - 1).'.quantity' => __('store.req.v.issue_over', ['n' => $l['n'], 'qty' => GsBook::qty($left), 'unit' => $l['unit'], 'item' => $l['name'], 'no' => $requisition->req_no])]);
                }
            }
        }

        // Out of the store: an issue, a transfer, an adjustment down — never past what it holds.
        $need = [];
        foreach ($out as $l) {
            $leaving = in_array($type, ['ISSUE', 'ADJUST'], true) ? max(0, -$l['row']['movement_qty']) : ($type === 'TRANSFER' ? $l['row']['movement_qty'] : 0);
            if ($leaving > 0) {
                $need[$l['row']['gs_item_id']] = ($need[$l['row']['gs_item_id']] ?? 0) + $leaving;
                $have = GsBook::stockIn($l['row']['gs_item_id'], $store);
                if ($need[$l['row']['gs_item_id']] > $have + 0.00005) {
                    $storeName = GsStore::query()->whereKey($store)->value('name');
                    throw ValidationException::withMessages($type === 'ADJUST'
                        ? ['lines' => __('store.inv.v.adjust_short', ['item' => $l['name'], 'store' => $storeName, 'have' => GsBook::qty(max(0, $have)), 'need' => GsBook::qty($need[$l['row']['gs_item_id']])])]
                        : ['lines.'.($l['n'] - 1).'.quantity' => __('store.inv.v.line_short', ['n' => $l['n'], 'qty' => GsBook::qty(max(0, $have)), 'unit' => $l['unit'], 'item' => $l['name']])]);
                }
            }
        }

        // Back against an issue: only its items, and no more than it gave and is not back yet.
        if ($against) {
            $left = $this->returnableOf($against);
            $back = [];
            foreach ($out as $l) {
                $id = $l['row']['gs_item_id'];
                if (! isset($left[$id])) {
                    throw ValidationException::withMessages(['lines.'.($l['n'] - 1).'.gs_item_id' => __('store.inv.v.not_on_issue', ['n' => $l['n'], 'item' => $l['name'], 'no' => $against->doc_no])]);
                }
                $back[$id] = ($back[$id] ?? 0) + $l['row']['quantity'];
                if ($back[$id] > $left[$id]['eligible'] + 0.00005) {
                    throw ValidationException::withMessages(['lines.'.($l['n'] - 1).'.quantity' => __('store.inv.v.return_over', ['n' => $l['n'], 'qty' => GsBook::qty($left[$id]['eligible']), 'unit' => $l['unit'], 'item' => $l['name'], 'no' => $against->doc_no])]);
                }
            }
        }

        return $out;
    }

    /** Where a line's movement lands: one store, or both legs of a transfer. @return list<array{0: string, 1: float}> */
    private function legs(GsDocument $doc, float $movement): array
    {
        return $doc->doc_type === 'TRANSFER' ? [[$doc->gs_store_id, -$movement], [$doc->to_store_id, $movement]] : [[$doc->gs_store_id, $movement]];
    }

    /** @return array<string, array{issued: float, returned: float, eligible: float}> item id => quantities */
    private function returnableOf(GsDocument $issue): array
    {
        $issued = GsDocumentLine::query()->where('gs_document_id', $issue->id)->get()->groupBy('gs_item_id')->map(fn ($s) => round((float) $s->sum('quantity'), 4));
        $returned = GsDocumentLine::query()->whereIn('gs_document_id', GsDocument::query()->where('against_id', $issue->id)->where('status', 'POSTED')->select('id'))
            ->get()->groupBy('gs_item_id')->map(fn ($s) => round((float) $s->sum('quantity'), 4));

        return $issued->map(fn (float $q, string $id) => ['issued' => $q, 'returned' => $returned[$id] ?? 0.0, 'eligible' => round(max(0, $q - ($returned[$id] ?? 0)), 4)])->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function requisitionOptions(): array
    {
        $reqs = GsRequisition::query()->whereIn('status', GsRequisition::ISSUABLE)->orderByDesc('req_date')->orderByDesc('req_no')->limit(300)->get();
        $depts = GsDepartment::query()->whereIn('id', $reqs->pluck('gs_department_id'))->pluck('name', 'id');

        return $reqs->map(fn (GsRequisition $r) => ['value' => $r->req_no, 'label' => "{$r->req_no} — ".($depts[$r->gs_department_id] ?? '—')])->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function issueOptions(): array
    {
        $issues = GsDocument::query()->where('doc_type', 'ISSUE')->where('status', 'POSTED')->orderByDesc('doc_date')->orderByDesc('doc_no')->limit(300)->get();
        $depts = GsDepartment::query()->whereIn('id', $issues->pluck('gs_department_id')->filter())->pluck('name', 'id');

        return $issues->map(fn (GsDocument $d) => ['value' => $d->doc_no, 'label' => "{$d->doc_no} — ".($depts[$d->gs_department_id] ?? '—').' ('.$d->doc_date->format('d M Y').')'])->all();
    }

    /** @return array{q: ?string, from: ?string, to: ?string, store: ?string, item: ?string, supplier: ?string, department: ?string, status: ?string} */
    private function filters(Request $request): array
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'store' => ['nullable', 'string'], 'item' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(['POSTED', 'CANCELLED'])],
        ]);

        return array_merge(array_fill_keys(['q', 'from', 'to', 'store', 'item', 'supplier', 'department', 'status'], null), $f);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $type, array $f): array
    {
        $docs = GsDocument::query()->where('doc_type', $type)
            ->when($f['from'], fn ($q, $v) => $q->where('doc_date', '>=', $v))->when($f['to'], fn ($q, $v) => $q->where('doc_date', '<=', $v))
            ->when($f['store'], fn ($q, $v) => $q->where(fn ($w) => $w->where('gs_store_id', $v)->orWhere('to_store_id', $v)))
            ->when($f['status'], fn ($q, $v) => $q->where('status', $v))->when($f['supplier'], fn ($q, $v) => $q->where('gs_supplier_id', $v))
            ->when($f['department'], fn ($q, $v) => $q->where('gs_department_id', $v))
            ->when($f['item'], fn ($q, $v) => $q->whereIn('id', GsDocumentLine::query()->where('gs_item_id', $v)->select('gs_document_id')))
            ->when($f['q'], function ($q, $v) {
                $like = '%'.mb_strtolower(trim($v)).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(doc_no) like ?', [$like])->orWhereIn('id', GsDocumentLine::query()
                    ->whereIn('gs_item_id', GsItem::query()->where(fn ($i) => $i->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(item_code) like ?', [$like]))->select('id'))
                    ->select('gs_document_id')));
            })
            ->orderByDesc('doc_date')->orderByDesc('doc_no')->limit(500)->get();
        $lines = GsDocumentLine::query()->whereIn('gs_document_id', $docs->pluck('id'))->orderBy('sort_order')->get()->groupBy('gs_document_id');
        $items = GsItem::query()->whereIn('id', $lines->flatten()->pluck('gs_item_id')->unique())->pluck('name', 'id');
        $stores = GsStore::query()->pluck('name', 'id');
        $names = DB::table('users')->whereIn('id', $docs->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $suppliers = GsSupplier::query()->whereIn('id', $docs->pluck('gs_supplier_id')->filter())->pluck('name', 'id');
        $depts = GsDepartment::query()->whereIn('id', $docs->pluck('gs_department_id')->filter())->pluck('name', 'id');
        $againsts = GsDocument::query()->whereIn('id', $docs->pluck('against_id')->filter())->pluck('doc_no', 'id');
        $reqNos = GsRequisition::query()->whereIn('id', $docs->pluck('requisition_id')->filter())->pluck('req_no', 'id');

        return $docs->map(function (GsDocument $d) use ($lines, $items, $stores, $names, $suppliers, $depts, $againsts, $reqNos): array {
            $set = $lines[$d->id] ?? collect();
            $first = $set->first();
            $totals = $this->totals($set);

            return [
                'doc_no' => $d->doc_no, 'date' => $d->doc_date->toDateString(), 'store' => $stores[$d->gs_store_id] ?? null, 'to_store' => $d->to_store_id ? ($stores[$d->to_store_id] ?? null) : null,
                'supplier' => $d->gs_supplier_id ? ($suppliers[$d->gs_supplier_id] ?? null) : null, 'department' => $d->gs_department_id ? ($depts[$d->gs_department_id] ?? null) : null,
                'requisition' => $d->requisition_id ? ($reqNos[$d->requisition_id] ?? null) : null, 'against' => $d->against_id ? ($againsts[$d->against_id] ?? null) : null, 'type' => $d->adjustment_type,
                'items' => $first ? ($items[$first->gs_item_id] ?? '').($set->count() > 1 ? ' +'.($set->count() - 1) : '') : '',
                'total_qty' => $totals['qty'], 'value' => $totals['priced'] ? $totals['value'] : null, 'status' => $d->status, 'user' => $names[$d->created_by] ?? null,
            ];
        })->all();
    }

    /** The list's columns between Date and Items, per screen, as Brickland orders them. @return list<string> */
    private function extraColumns(string $type): array
    {
        return match ($type) {
            'RECEIVE' => ['store', 'supplier'],
            'ISSUE' => ['store', 'department', 'requisition'],
            'RETURN' => ['store', 'department', 'against'],
            'ADJUST' => ['store', 'type'],
            'TRANSFER' => ['store', 'to_store'],
            default => ['store'],
        };
    }

    /** @return array{qty: float, value: float, priced: bool} */
    private function totals(Collection $lines): array
    {
        $priced = $lines->filter(fn (GsDocumentLine $l) => $l->unit_price !== null);

        return [
            'qty' => round((float) $lines->sum(fn (GsDocumentLine $l) => abs((float) $l->movement_qty)), 4),
            'value' => round((float) $priced->sum(fn (GsDocumentLine $l) => abs((float) $l->movement_qty) * (float) $l->unit_price), 2),
            'priced' => $priced->isNotEmpty(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(GsDocument $d): array
    {
        $lines = GsDocumentLine::query()->where('gs_document_id', $d->id)->orderBy('sort_order')->get();
        $items = GsItem::query()->whereIn('id', $lines->pluck('gs_item_id'))->get()->keyBy('id');
        $units = GsUnit::query()->pluck('code', 'id');
        $names = DB::table('users')->whereIn('id', array_filter([$d->created_by, $d->cancelled_by]))->pluck('name', 'id');
        $totals = $this->totals($lines);

        return [
            'doc_no' => $d->doc_no, 'doc_type' => $d->doc_type, 'date' => $d->doc_date->toDateString(), 'status' => $d->status,
            'store' => GsStore::query()->whereKey($d->gs_store_id)->value('name'), 'to_store' => $d->to_store_id ? GsStore::query()->whereKey($d->to_store_id)->value('name') : null,
            'supplier' => $d->gs_supplier_id ? GsSupplier::query()->whereKey($d->gs_supplier_id)->value('name') : null, 'challan_no' => $d->challan_no, 'reference_no' => $d->reference_no,
            'department' => $d->gs_department_id ? GsDepartment::query()->whereKey($d->gs_department_id)->value('name') : null, 'person' => $d->person,
            'against' => $d->against_id ? GsDocument::query()->whereKey($d->against_id)->value('doc_no') : null,
            'requisition' => $d->requisition_id ? GsRequisition::query()->whereKey($d->requisition_id)->value('req_no') : null,
            'adjustment_type' => $d->adjustment_type, 'remarks' => $d->remarks,
            'created_by' => $names[$d->created_by] ?? null, 'created_at' => $d->created_at?->toIso8601String(),
            'cancelled_by' => $names[$d->cancelled_by] ?? null, 'cancelled_at' => $d->cancelled_at?->toIso8601String(), 'cancel_reason' => $d->cancel_reason,
            'lines' => $lines->map(fn (GsDocumentLine $l) => [
                'sl' => $l->sort_order, 'item_code' => $items[$l->gs_item_id]->item_code ?? null, 'item_name' => $items[$l->gs_item_id]->name ?? null,
                'unit' => $units[$items[$l->gs_item_id]->gs_unit_id ?? ''] ?? null, 'quantity' => (float) $l->quantity, 'movement_qty' => (float) $l->movement_qty,
                'unit_price' => $l->unit_price === null ? null : (float) $l->unit_price, 'suggested_price' => $l->suggested_price === null ? null : (float) $l->suggested_price,
                'value' => $l->unit_price === null ? null : round(abs((float) $l->movement_qty) * (float) $l->unit_price, 2), 'remarks' => $l->line_remarks,
            ])->values()->all(),
            'total_qty' => $totals['qty'], 'total_value' => $totals['priced'] ? $totals['value'] : null,
        ];
    }

    private function type(string $kind): string
    {
        return self::KINDS[$kind] ?? abort(404);
    }

    private function find(string $no): GsDocument
    {
        return GsDocument::query()->where('doc_no', strtoupper(trim($no)))->first()
            ?? throw ValidationException::withMessages(['document' => __('store.inv.missing', ['no' => strtoupper(trim($no))])]);
    }

    /** The store's starting lists, made the first time any store screen opens. */
    private function ensureLists(): void
    {
        $company = app(TenantContext::class)->companyId();
        GeneralStoreDefaults::categories($company);
        GeneralStoreDefaults::units($company);
        GeneralStoreDefaults::departments($company);
        GeneralStoreDefaults::stores($company);
    }
}
