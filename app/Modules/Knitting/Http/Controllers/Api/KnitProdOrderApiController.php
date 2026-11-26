<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitProgram;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Bulk, Yarn-Dyed,
 * Sample and Flat Bed Rib Fabric Production Order, as Brickland works them:
 * the register (KP Made, KP Blnc, KC Done, KC Blnc and Rolled are what the
 * programmes, cards and rolls add up to — nothing there is typed; Complete,
 * Hold, Resume), the order's own form (FPO-…), Knit Programmes released
 * against it (KP-…, never past what it still owes) and Knit Cards cut off a
 * programme (KC-…, never past what the programme has left; a card with
 * rolls cannot be cancelled). Everything travels by number.
 */
class KnitProdOrderApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok(KnitProduction::options());
    }

    /** The register of one kind; Running unless asked otherwise. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'kind' => ['required', Rule::in(KnitProdOrder::KINDS)], 'buyer' => ['nullable', 'string'], 'ship_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'style' => ['nullable', 'string', 'max:64'], 'order' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(KnitProdOrder::STATUSES)], 'q' => ['nullable', 'string', 'max:80'],
        ]);
        $rows = KnitProdOrder::query()->where('order_kind', $f['kind'])
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['ship_month']), fn ($q) => $q->where('ship_month', $f['ship_month']))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_id', $f['order']))
            ->when(! empty($f['style']), fn ($q) => $q->whereIn('order_id', Order::query()->where(fn ($w) => $w->where('style_no', $f['style'])->orWhere('work_style_no', $f['style']))->select('id')))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('created_at')->limit(300)->get();

        return ApiResponse::ok($this->registerRows($rows));
    }

    public function show(string $po): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($po)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $data = $this->validated($request);
        $row = KnitProdOrder::query()->create($data + [
            'po_no' => KnitProduction::nextNumber('FPO', 'knit_prod_orders', 'po_no', now()->toDateString()), 'created_by' => $this->caller()->auditUserId(),
        ]);

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $po): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($po);
        $data = $this->validated($request);
        $net = (float) $data['req_qty'] + (float) ($data['adjusted_qty'] ?? 0);
        $made = KnitProduction::figures([$row->id])[$row->id]['kp'];
        if ($net + 0.0005 < $made) {
            throw ValidationException::withMessages(['req_qty' => __('knitting.prod.v.req_below_kp', ['made' => $made])]);
        }
        $row->update($data);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** The register's Complete, Hold and Resume. */
    public function transition(string $po, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($po);
        [$from, $to] = match ($action) {
            'complete' => [['RUNNING', 'HOLD'], 'COMPLETED'],
            'hold' => [['RUNNING'], 'HOLD'],
            'resume' => [['HOLD', 'COMPLETED'], 'RUNNING'],
            default => abort(404),
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.prod.v.cannot', [
                'no' => $this->labelOf($row), 'status' => __("knitting.prod.order_status.{$row->status}"), 'action' => __("knitting.prod.order_action.{$action}"),
            ])]);
        }
        $row->update(['status' => $to]);

        return ApiResponse::ok($this->registerRows(collect([$row->refresh()]))[0]);
    }

    /* ---------------- Knit Programmes (KP) ---------------- */

    public function programs(string $po): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->presentPrograms($this->find($po)));
    }

    /** Release Programme: date, weight and floor; never more than the order still owes. */
    public function release(Request $request, string $po): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($po);
        $this->assertOpen($row);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'program_date' => ['required', 'date'], 'qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'floor_id' => ['nullable', Rule::exists('floors', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['program_date.required' => __('knitting.prod.v.date'), 'qty.required' => __('knitting.prod.v.qty'), 'qty.gt' => __('knitting.prod.v.qty')]);
        $owed = $this->owed($row);
        if ((float) $data['qty'] > $owed + 0.0005) {
            throw ValidationException::withMessages(['qty' => __('knitting.prod.v.kp_over', ['left' => round(max(0, $owed), 3)])]);
        }
        KnitProgram::query()->create($data + [
            'kp_no' => KnitProduction::nextNumber('KP', 'knit_programs', 'kp_no', $data['program_date']), 'prod_order_id' => $row->id, 'status' => 'OPEN',
            'created_by' => $this->caller()->auditUserId(),
        ]);

        return ApiResponse::created($this->presentPrograms($row));
    }

    /** Save: the grid's edits — date, weight, status, remarks — checked together against what the order owes. */
    public function savePrograms(Request $request, string $po): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($po);
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'], 'rows.*.kp_no' => ['required', 'string'], 'rows.*.program_date' => ['required', 'date'],
            'rows.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'], 'rows.*.status' => ['required', Rule::in(KnitProgram::STATUSES)],
            'rows.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $rows = YarnStore::inOrder($data['rows']);
        $own = KnitProgram::query()->where('prod_order_id', $row->id)->get()->keyBy('kp_no');
        $errors = [];
        $total = 0.0;
        foreach ($own as $kp) {
            $edit = collect($rows)->firstWhere('kp_no', $kp->kp_no);
            $status = $edit['status'] ?? $kp->status;
            if ($status !== 'CANCELLED') {
                $total += (float) ($edit['qty'] ?? $kp->qty);
            }
        }
        foreach ($rows as $i => $r) {
            $kp = $own->get($r['kp_no']);
            if (! $kp) {
                $errors["rows.{$i}.kp_no"] = __('knitting.prod.v.not_here');

                continue;
            }
            $carded = $this->cardedOn($kp->id);
            if ($r['status'] === 'CANCELLED' && $carded > 0) {
                $errors["rows.{$i}.status"] = __('knitting.prod.v.kp_has_cards', ['no' => $kp->kp_no]);
            } elseif ((float) $r['qty'] + 0.0005 < $carded) {
                $errors["rows.{$i}.qty"] = __('knitting.prod.v.kp_below_cards', ['no' => $kp->kp_no, 'carded' => $carded]);
            }
        }
        $net = (float) $row->req_qty + (float) $row->adjusted_qty;
        if ($total > $net + 0.0005) {
            $errors['rows'] = __('knitting.prod.v.kp_total', ['total' => round($total, 3), 'net' => round($net, 3)]);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($rows, $own): void {
            foreach ($rows as $r) {
                $own[$r['kp_no']]->update(collect($r)->only(['program_date', 'qty', 'status', 'remarks'])->all());
            }
        });

        return ApiResponse::ok($this->presentPrograms($row));
    }

    public function deleteProgram(string $kp): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $program = KnitProgram::query()->where('kp_no', $kp)->firstOrFail();
        if (KnitCard::query()->where('program_id', $program->id)->exists()) {
            throw ValidationException::withMessages(['kp_no' => __('knitting.prod.v.kp_has_cards', ['no' => $program->kp_no])]);
        }
        $order = KnitProdOrder::query()->findOrFail($program->prod_order_id);
        $program->delete();

        return ApiResponse::ok($this->presentPrograms($order));
    }

    /* ---------------- Knit Cards (KC) ---------------- */

    public function cards(string $po): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->presentCards($this->find($po)));
    }

    /** Cut Knit Card: off one programme, never past what it has left. */
    public function cut(Request $request, string $po): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($po);
        $this->assertOpen($row);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'card_date' => ['required', 'date'], 'kp_no' => ['required', 'string'], 'assigned_qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'colour_name' => ['nullable', 'string', 'max:80'], 'machine_dia' => ['nullable', 'string', 'max:24'], 'finish_dia' => ['nullable', 'string', 'max:24'],
            'gauge' => ['nullable', 'string', 'max:24'], 'floor_id' => ['nullable', Rule::exists('floors', 'id')->where('company_id', $company)],
            'yarn_details' => ['nullable', 'string', 'max:255'],
        ], [
            'card_date.required' => __('knitting.prod.v.date'), 'kp_no.required' => __('knitting.prod.v.kc_program'),
            'assigned_qty.required' => __('knitting.prod.v.qty'), 'assigned_qty.gt' => __('knitting.prod.v.qty'),
        ]);
        $program = KnitProgram::query()->where('prod_order_id', $row->id)->where('kp_no', $data['kp_no'])->whereNotIn('status', ['CANCELLED', 'CLOSED'])->first();
        if (! $program) {
            throw ValidationException::withMessages(['kp_no' => __('knitting.prod.v.kc_program')]);
        }
        $left = (float) $program->qty - $this->cardedOn($program->id);
        if ((float) $data['assigned_qty'] > $left + 0.0005) {
            throw ValidationException::withMessages(['assigned_qty' => __('knitting.prod.v.kc_over', ['left' => round(max(0, $left), 3)])]);
        }
        DB::transaction(function () use ($data, $row, $program): void {
            KnitCard::query()->create(collect($data)->except('kp_no')->all() + [
                'kc_no' => KnitProduction::nextNumber('KC', 'knit_cards', 'kc_no', $data['card_date']), 'program_id' => $program->id, 'prod_order_id' => $row->id,
                'colour_name' => $data['colour_name'] ?? $this->colourOf($row), 'finish_dia' => $data['finish_dia'] ?? $row->finish_dia,
                'status' => 'OPEN', 'created_by' => $this->caller()->auditUserId(),
            ]);
            if ($program->status === 'OPEN') {
                $program->update(['status' => 'RUNNING']);
            }
        });

        return ApiResponse::created($this->presentCards($row));
    }

    /** Save: the card grid's edits, each card checked against its programme. */
    public function saveCards(Request $request, string $po): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($po);
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'], 'rows.*.kc_no' => ['required', 'string'], 'rows.*.card_date' => ['required', 'date'],
            'rows.*.assigned_qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'], 'rows.*.status' => ['required', Rule::in(KnitCard::STATUSES)],
            'rows.*.colour_name' => ['nullable', 'string', 'max:80'], 'rows.*.machine_dia' => ['nullable', 'string', 'max:24'], 'rows.*.finish_dia' => ['nullable', 'string', 'max:24'],
            'rows.*.gauge' => ['nullable', 'string', 'max:24'], 'rows.*.yarn_details' => ['nullable', 'string', 'max:255'],
        ]);
        $rows = YarnStore::inOrder($data['rows']);
        $own = KnitCard::query()->where('prod_order_id', $row->id)->get()->keyBy('kc_no');
        $errors = [];
        foreach ($rows as $i => $r) {
            $card = $own->get($r['kc_no']);
            if (! $card) {
                $errors["rows.{$i}.kc_no"] = __('knitting.prod.v.not_here');

                continue;
            }
            if ($r['status'] === 'CANCELLED' && KnitRoll::query()->where('card_id', $card->id)->exists()) {
                $errors["rows.{$i}.status"] = __('knitting.prod.v.kc_has_rolls', ['no' => $card->kc_no]);
            }
        }
        // Each programme's cards, as edited, within the programme.
        foreach ($own->groupBy('program_id') as $programId => $cards) {
            $program = KnitProgram::query()->find($programId);
            $sum = $cards->sum(function (KnitCard $c) use ($rows) {
                $e = collect($rows)->firstWhere('kc_no', $c->kc_no);

                return ($e['status'] ?? $c->status) === 'CANCELLED' ? 0 : (float) ($e['assigned_qty'] ?? $c->assigned_qty);
            });
            if ($program && $sum > (float) $program->qty + 0.0005) {
                $errors['rows'] = __('knitting.prod.v.kc_over_program', ['no' => $program->kp_no, 'qty' => round((float) $program->qty, 3)]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($rows, $own): void {
            foreach ($rows as $r) {
                $own[$r['kc_no']]->update(collect($r)->only(['card_date', 'assigned_qty', 'status', 'colour_name', 'machine_dia', 'finish_dia', 'gauge', 'yarn_details'])->all());
            }
        });

        return ApiResponse::ok($this->presentCards($row));
    }

    public function deleteCard(string $kc): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $card = KnitCard::query()->where('kc_no', $kc)->firstOrFail();
        if (KnitRoll::query()->where('card_id', $card->id)->exists()) {
            throw ValidationException::withMessages(['kc_no' => __('knitting.prod.v.kc_has_rolls', ['no' => $card->kc_no])]);
        }
        $order = KnitProdOrder::query()->findOrFail($card->prod_order_id);
        $card->delete();

        return ApiResponse::ok($this->presentCards($order));
    }

    /* ---------------- helpers ---------------- */

    private function find(string $po): KnitProdOrder
    {
        return KnitProdOrder::query()->where('po_no', $po)->firstOrFail();
    }

    private function assertOpen(KnitProdOrder $row): void
    {
        if (! in_array($row->status, ['RUNNING'], true)) {
            throw ValidationException::withMessages(['status' => __('knitting.prod.v.not_running', ['status' => __("knitting.prod.order_status.{$row->status}")])]);
        }
    }

    /** What the order still owes: Req Qty ± Adjusted, less the live programmes. */
    private function owed(KnitProdOrder $row): float
    {
        return (float) $row->req_qty + (float) $row->adjusted_qty - KnitProduction::figures([$row->id])[$row->id]['kp'];
    }

    private function cardedOn(string $programId): float
    {
        return round((float) KnitCard::query()->where('program_id', $programId)->where('status', '!=', 'CANCELLED')->sum('assigned_qty'), 3);
    }

    private function colourOf(KnitProdOrder $row): ?string
    {
        return $row->colour_name ?: ($row->colour_id ? DB::table('colours')->where('id', $row->colour_id)->value('name') : null);
    }

    private function labelOf(KnitProdOrder $row): string
    {
        return trim((KnitProduction::orderLabel(Order::query()->find($row->order_id)) ?? $row->po_no).' · '.$row->fabric_desc);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $kg = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'order_kind' => ['required', Rule::in(KnitProdOrder::KINDS)],
            'order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'category_code' => ['nullable', Rule::in(array_column(KnitProduction::categories(), 'value'))],
            'ship_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'fabric_desc' => ['required', 'string', 'max:160'],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'colour_name' => ['nullable', 'string', 'max:80'], 'finish_dia' => ['nullable', 'string', 'max:24'], 'gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'item_image' => ['nullable', 'string', 'max:255'], 'booking_ref' => ['nullable', 'string', 'max:80'],
            'req_qty' => ['required', 'numeric', 'gt:0', 'max:9999999999'], 'adjusted_qty' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
            'saving_target_qty' => $kg, 'yarn_stock_qty' => $kg,
            'status' => ['required', Rule::in(KnitProdOrder::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'order_id.required' => __('knitting.prod.v.order'), 'fabric_desc.required' => __('knitting.prod.v.fabric'),
            'req_qty.required' => __('knitting.prod.v.req_qty'), 'req_qty.gt' => __('knitting.prod.v.req_qty'), 'status.required' => __('knitting.prod.v.status'),
        ]);
        $data['buyer_id'] = Order::query()->whereKey($data['order_id'])->value('buyer_id');
        $data['adjusted_qty'] = (float) ($data['adjusted_qty'] ?? 0);
        if ((float) $data['req_qty'] + $data['adjusted_qty'] <= 0) {
            throw ValidationException::withMessages(['adjusted_qty' => __('knitting.prod.v.net_zero')]);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function registerRows($rows): array
    {
        $figures = KnitProduction::figures($rows->pluck('id'));
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $colours = DB::table('colours')->whereIn('id', $rows->pluck('colour_id')->filter())->pluck('name', 'id');
        $cats = collect(KnitProduction::categories())->pluck('label', 'value');

        return $rows->map(function (KnitProdOrder $r) use ($figures, $orders, $buyers, $colours, $cats) {
            $f = $figures[$r->id];
            $req = round((float) $r->req_qty + (float) $r->adjusted_qty, 3);
            $o = $orders[$r->order_id] ?? null;

            return [
                'po_no' => $r->po_no, 'kind' => $r->order_kind, 'category' => $r->category_code ? (preg_replace('/ \(FP\d+\)$/', '', (string) ($cats[$r->category_code] ?? $r->category_code))) : null,
                'buyer' => $buyers[$r->buyer_id] ?? null, 'order' => KnitProduction::orderLabel($o), 'style' => $o?->work_style_no ?: $o?->style_no,
                'order_type' => $o?->order_type, 'ship_month' => $r->ship_month, 'fabric' => $r->fabric_desc, 'colour' => $r->colour_name ?: ($colours[$r->colour_id] ?? null),
                'booking_ref' => $r->booking_ref, 'req_qty' => $req, 'kp_made' => $f['kp'], 'kp_balance' => round($req - $f['kp'], 3), 'kc_done' => $f['kc'],
                'kc_balance' => round($f['kp'] - $f['kc'], 3), 'adjusted' => round((float) $r->adjusted_qty, 3), 'yarn_stock' => round((float) ($r->yarn_stock_qty ?? 0), 3),
                'rolled' => $f['rolled'], 'saving_target' => round((float) ($r->saving_target_qty ?? 0), 3), 'status' => $r->status,
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitProdOrder $row): array
    {
        return collect($row->only(['po_no', 'order_kind', 'order_id', 'buyer_id', 'category_code', 'ship_month', 'fabric_type_id', 'fabric_desc', 'colour_id', 'colour_name',
            'finish_dia', 'item_image', 'booking_ref', 'status', 'remarks']))->all() + [
                'gsm' => $row->gsm, 'req_qty' => (float) $row->req_qty, 'adjusted_qty' => (float) $row->adjusted_qty, 'saving_target_qty' => $row->saving_target_qty,
                'yarn_stock_qty' => $row->yarn_stock_qty, 'figures' => $this->registerRows(collect([$row]))[0],
            ];
    }

    /** @return array<string, mixed> */
    private function presentPrograms(KnitProdOrder $row): array
    {
        $programs = KnitProgram::query()->where('prod_order_id', $row->id)->orderBy('kp_no')->get();
        $cards = KnitCard::query()->whereIn('program_id', $programs->pluck('id'))->where('status', '!=', 'CANCELLED')->get()->groupBy('program_id');

        return [
            'order' => $this->registerRows(collect([$row]))[0],
            'rows' => $programs->map(fn (KnitProgram $p) => [
                'kp_no' => $p->kp_no, 'program_date' => $p->program_date->toDateString(), 'qty' => (float) $p->qty, 'floor_id' => $p->floor_id,
                'cards' => ($cards[$p->id] ?? collect())->count(), 'carded' => round((float) ($cards[$p->id] ?? collect())->sum('assigned_qty'), 3),
                'status' => $p->status, 'remarks' => $p->remarks,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentCards(KnitProdOrder $row): array
    {
        $cards = KnitCard::query()->where('prod_order_id', $row->id)->orderBy('kc_no')->get();
        $rolls = KnitRoll::query()->whereIn('card_id', $cards->pluck('id'))->get()->groupBy('card_id');
        $machines = DB::table('knit_machines')->whereIn('id', $cards->pluck('machine_id')->filter())->pluck('machine_no', 'id');
        $programs = KnitProgram::query()->where('prod_order_id', $row->id)->orderBy('kp_no')->get();
        $carded = KnitCard::query()->whereIn('program_id', $programs->pluck('id'))->where('status', '!=', 'CANCELLED')->groupBy('program_id')->selectRaw('program_id, sum(assigned_qty) as q')->pluck('q', 'program_id');

        return [
            'order' => $this->registerRows(collect([$row]))[0],
            // What the Cut Knit Card dialog offers: live programmes and what each has left.
            'programs' => $programs->whereNotIn('status', ['CANCELLED', 'CLOSED'])->map(fn (KnitProgram $p) => [
                'value' => $p->kp_no, 'label' => $p->kp_no.' · '.number_format((float) $p->qty, 3).' Kg · '.$p->program_date->format('d M Y'),
                'left' => round((float) $p->qty - (float) ($carded[$p->id] ?? 0), 3),
            ])->values()->all(),
            'defaults' => ['colour_name' => $this->colourOf($row), 'finish_dia' => $row->finish_dia],
            'rows' => $cards->map(fn (KnitCard $c) => [
                'kc_no' => $c->kc_no, 'kp_no' => $programs->firstWhere('id', $c->program_id)?->kp_no, 'card_date' => $c->card_date->toDateString(), 'colour_name' => $c->colour_name,
                'machine_dia' => $c->machine_dia, 'finish_dia' => $c->finish_dia, 'gauge' => $c->gauge, 'assigned_qty' => (float) $c->assigned_qty,
                'rolls' => ($rolls[$c->id] ?? collect())->count(), 'rolled' => round((float) ($rolls[$c->id] ?? collect())->sum(fn ($r) => (float) $r->weight - (float) $r->deducted), 3),
                'machine' => $machines[$c->machine_id] ?? null, 'status' => $c->status, 'yarn_details' => $c->yarn_details,
            ])->values()->all(),
        ];
    }
}
