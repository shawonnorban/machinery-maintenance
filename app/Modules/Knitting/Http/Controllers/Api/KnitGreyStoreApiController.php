<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitGreyAdjustment;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitGreyMonthClose;
use App\Modules\Knitting\Models\KnitGreyMove;
use App\Modules\Knitting\Models\KnitGreyOpening;
use App\Modules\Knitting\Models\KnitGreyReceipt;
use App\Modules\Knitting\Models\KnitGreyReceiptRoll;
use App\Modules\Knitting\Models\KnitGreyTransfer;
use App\Modules\Knitting\Models\KnitGreyTransferLine;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the grey store's own
 * desks, as Brickland works them:
 *
 * - Grey Fabric Store Receipt: a roll tag scanned into a store and rack is
 *   received at once — onto the day's receive note for that store (GFR-…),
 *   into the grey ledger, the roll now in the grey store. Until the day is
 *   confirmed a scan can be withdrawn, or the whole day cleared. Fabric Stock
 *   lists what the store holds roll by roll; Receive Notes is the day's paper.
 * - Grey Fabric Opening Stock: opening lines (GOB-…) by tab — In-house,
 *   Collar-Cuff, S/C In-house — written open and finalised in one go into
 *   the ledger.
 * - Grey Fabric Stock Close: Show Exception lists what would stop a finished
 *   month closing; Month Close checks again and strikes it.
 */
class KnitGreyStoreApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $company = app(TenantContext::class)->companyId();
        $o = YarnProcurement::options();
        $p = KnitProduction::options();
        $today = CarbonImmutable::today();

        return ApiResponse::ok(collect($o)->only(['company', 'stores', 'buyers', 'orders', 'suppliers', 'uoms', 'compositions', 'counts'])->all() + [
            'colour_groups' => YarnProcurement::COLOURS,
            'colours' => $p['colours'], 'fabric_types' => $p['fabric_types'],
            'bins' => DB::table('bins')->where('company_id', $company)->where('active', true)->where('is_in_transit', false)->orderBy('code')->get(['id', 'code', 'name', 'store_id'])
                ->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->name} ({$b->code})", 'store_id' => $b->store_id])->values()->all(),
            'kinds' => KnitGreyLot::KINDS, 'prod_types' => KnitProdOrder::KINDS, 'trans_types' => KnitGreyTransfer::TYPES, 'transfer_statuses' => KnitGreyTransfer::STATUSES,
            'adj_statuses' => KnitGreyAdjustment::STATUSES, 'cc_types' => KnitGreyTransfer::CC_TYPES,
            // Racking Board's yarn filters, as Brickland lists them; brands from the yarn the stores have held.
            'spin_types' => ['Combed', 'Carded', 'Open End', 'Ring Spun', 'Vortex', 'Semi Combed'],
            'cotton_types' => ['Conventional', 'Organic', 'Bci', 'Recycled', 'Supima', 'Egyptian'],
            'brands' => DB::table('knit_yarn_lots')->where('company_id', $company)->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand')->all(),
            // The days a receipt may be booked on: today and the thirty before it.
            'dates' => collect(range(0, 30))->map(fn ($d) => $today->subDays($d)->toDateString())->all(),
            'closed' => KnitGreyMonthClose::query()->orderByDesc('period')->pluck('period')->all(),
        ]);
    }

    /* ---------------- Grey Fabric Store Receipt ---------------- */

    /** A store's day: its note and the rolls scanned in on it. */
    public function day(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $f = $request->validate(['store' => ['nullable', 'string'], 'date' => ['required', 'date']]);

        return ApiResponse::ok($this->presentDay($f['store'] ?? null, $f['date']));
    }

    public function scan(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'receive_date' => ['required', 'date', 'before_or_equal:today'], 'roll_code' => ['required', 'string', 'max:40'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'bin_id' => ['required', Rule::exists('bins', 'id')->where('company_id', $company)->where('store_id', $request->input('store_id'))],
        ], [
            'roll_code.required' => __('knitting.fs.v.roll_code'), 'store_id.required' => __('knitting.fs.v.store_rack'), 'bin_id.required' => __('knitting.fs.v.store_rack'),
            'bin_id.exists' => __('knitting.fs.v.rack_store'),
        ]);
        GreyStore::assertOpen($data['receive_date'], 'receive_date');
        $code = mb_strtoupper(trim($data['roll_code']));
        $roll = KnitRoll::query()->whereRaw('upper(roll_code) = ?', [$code])->first()
            ?? throw ValidationException::withMessages(['roll_code' => __('knitting.fs.v.roll_unknown', ['roll' => $code])]);
        $taken = KnitGreyReceiptRoll::query()->where('roll_id', $roll->id)->first();
        if ($taken) {
            $note = KnitGreyReceipt::query()->find($taken->receipt_id);
            throw ValidationException::withMessages(['roll_code' => __('knitting.fs.v.roll_received', ['roll' => $roll->roll_code, 'no' => $note?->note_no])]);
        }
        if ($roll->movement !== 'IN_KNITTING') {
            throw ValidationException::withMessages(['roll_code' => __('knitting.fs.v.roll_moved', ['roll' => $roll->roll_code, 'where' => __("knitting.fs.movement.{$roll->movement}")])]);
        }
        $promised = KnitGreyTransferLine::query()->where('roll_id', $roll->id)
            ->whereIn('transfer_id', KnitGreyTransfer::query()->whereIn('status', ['DRAFT', 'SUBMITTED'])->pluck('id'))->first();
        if ($promised) {
            throw ValidationException::withMessages(['roll_code' => __('knitting.gt.v.roll_on_move', ['roll' => $roll->roll_code,
                'no' => KnitGreyTransfer::query()->whereKey($promised->transfer_id)->value('transfer_no')])]);
        }
        if ($roll->quality === 'REJECTED') {
            throw ValidationException::withMessages(['roll_code' => __('knitting.fs.v.roll_rejected', ['roll' => $roll->roll_code])]);
        }
        DB::transaction(function () use ($data, $roll): void {
            $note = KnitGreyReceipt::query()->where('store_id', $data['store_id'])->whereDate('receive_date', $data['receive_date'])->lockForUpdate()->first();
            if ($note && $note->status === 'CONFIRMED') {
                throw ValidationException::withMessages(['receive_date' => __('knitting.fs.v.day_confirmed', ['no' => $note->note_no])]);
            }
            $note ??= KnitGreyReceipt::query()->create([
                'store_id' => $data['store_id'], 'receive_date' => $data['receive_date'],
                'note_no' => GreyStore::nextNumber('GFR', 'knit_grey_receipts', 'note_no', $data['receive_date']),
            ]);
            $weight = round((float) $roll->weight - (float) $roll->deducted, 3);
            $lot = GreyStore::lotFor(GreyStore::rollFacts($roll) + ['store_id' => $data['store_id'], 'bin_id' => $data['bin_id']], $data['receive_date']);
            $row = KnitGreyReceiptRoll::query()->create([
                'receipt_id' => $note->id, 'roll_id' => $roll->id, 'bin_id' => $data['bin_id'], 'lot_id' => $lot->id, 'weight' => $weight,
                'scanned_at' => now(), 'scanned_by' => $this->caller()->auditUserId(),
            ]);
            GreyStore::move('RECEIVE', $note->note_no, $data['receive_date'], ['lot_id' => $lot->id, 'qty' => $weight, 'rolls' => 1, 'doc_line_id' => $row->id]);
            $roll->update(['movement' => 'GREY_STORE']);
        });

        return ApiResponse::created($this->presentDay($data['store_id'], $data['receive_date']) + ['scanned' => $roll->roll_code]);
    }

    /** A scan taken back while its day is still open. */
    public function withdraw(string $id): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = KnitGreyReceiptRoll::query()->findOrFail($id);
        $note = KnitGreyReceipt::query()->findOrFail($row->receipt_id);
        $this->openNote($note);
        DB::transaction(fn () => $this->takeBack($note, $row));

        return ApiResponse::ok($this->presentDay($note->store_id, $note->receive_date->toDateString()));
    }

    public function clearDay(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $f = $request->validate(['store_id' => ['required', 'string'], 'receive_date' => ['required', 'date']]);
        $note = KnitGreyReceipt::query()->where('store_id', $f['store_id'])->whereDate('receive_date', $f['receive_date'])->first();
        if ($note) {
            $this->openNote($note);
            DB::transaction(function () use ($note): void {
                foreach (KnitGreyReceiptRoll::query()->where('receipt_id', $note->id)->get() as $row) {
                    $this->takeBack($note, $row);
                }
                $note->delete();
            });
        }

        return ApiResponse::ok($this->presentDay($f['store_id'], $f['receive_date']));
    }

    public function confirm(string $no): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $note = KnitGreyReceipt::query()->where('note_no', $no)->firstOrFail();
        $this->openNote($note);
        if (! KnitGreyReceiptRoll::query()->where('receipt_id', $note->id)->exists()) {
            throw ValidationException::withMessages(['status' => __('knitting.fs.v.day_empty')]);
        }
        $note->update(['status' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok($this->presentDay($note->store_id, $note->receive_date->toDateString()));
    }

    /** Receive Notes: the days' papers. */
    public function notes(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $f = $request->validate(['store' => ['nullable', 'string'], 'date' => ['nullable', 'date']]);
        $rows = KnitGreyReceipt::query()->when(! empty($f['store']), fn ($q) => $q->where('store_id', $f['store']))
            ->when(! empty($f['date']), fn ($q) => $q->whereDate('receive_date', $f['date']))->orderByDesc('receive_date')->limit(300)->get();
        $sums = KnitGreyReceiptRoll::query()->whereIn('receipt_id', $rows->pluck('id'))->groupBy('receipt_id')->selectRaw('receipt_id, count(*) as n, sum(weight) as w')->get()->keyBy('receipt_id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitGreyReceipt $r) => [
            'note_no' => $r->note_no, 'receive_date' => $r->receive_date->toDateString(), 'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null,
            'rolls' => (int) ($sums[$r->id]->n ?? 0), 'weight' => round((float) ($sums[$r->id]->w ?? 0), 3), 'status' => $r->status,
        ])->values()->all());
    }

    public function note(string $no): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $note = KnitGreyReceipt::query()->where('note_no', $no)->firstOrFail();

        return ApiResponse::ok($this->presentDay($note->store_id, $note->receive_date->toDateString()));
    }

    /** Fabric Stock: what the stores hold, roll by roll. */
    public function stock(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $f = $request->validate(['store' => ['nullable', 'string'], 'bin' => ['nullable', 'string'], 'date' => ['nullable', 'date'], 'prod_type' => ['nullable', Rule::in(KnitProdOrder::KINDS)]]);
        $rows = collect(GreyStore::lots($f['store'] ?? null))
            ->filter(fn ($l) => (empty($f['bin']) || $l['bin_id'] === $f['bin']) && (empty($f['prod_type']) || $l['prod_type'] === $f['prod_type'])
                && (empty($f['date']) || $l['first_date'] === $f['date']))
            ->sortByDesc('first_date')->values();

        return ApiResponse::ok($rows->all());
    }

    /**
     * Grey Fabric Racking Board: what each rack holds. A roll's yarn is its
     * knit card's (count, spin, cotton, brand, lot as the floor wrote them),
     * which is what Count, Col. Group, Composition, Spin Type, Cotton Type,
     * Brand and Lot narrow by.
     */
    public function racks(Request $request): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $f = $request->validate([
            'store' => ['nullable', 'string'], 'bin' => ['nullable', 'string'], 'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'],
            'spin' => ['nullable', 'string', 'max:40'], 'cotton' => ['nullable', 'string', 'max:40'], 'brand' => ['nullable', 'string', 'max:80'], 'lot' => ['nullable', 'string', 'max:40'],
            'count' => ['nullable', 'string', 'max:40'], 'group' => ['nullable', 'string', 'max:40'], 'composition' => ['nullable', 'string', 'max:120'],
        ]);
        $yarn = DB::table('knit_rolls')->join('knit_cards', 'knit_cards.id', '=', 'knit_rolls.card_id')->pluck('knit_cards.yarn_details', 'knit_rolls.id');
        $has = fn (?string $hay, ?string $needle) => ! $needle || str_contains(mb_strtolower((string) $hay), mb_strtolower($needle));
        $rows = collect(GreyStore::lots($f['store'] ?? null, true, $f['order'] ?? null))
            ->map(fn ($l) => $l + ['yarn' => $l['roll_id'] ? ($yarn[$l['roll_id']] ?? null) : null])
            ->filter(fn ($l) => (empty($f['bin']) || $l['bin_id'] === $f['bin']) && (empty($f['buyer']) || $l['buyer_id'] === $f['buyer'])
                && collect(['spin', 'cotton', 'brand', 'count', 'group', 'composition'])->every(fn ($k) => $has($l['yarn'], $f[$k] ?? null))
                && (empty($f['lot']) || $has($l['yarn'], $f['lot']) || $has($l['roll_no'], $f['lot'])))
            // Racked lines first, rack by rack; what nobody racked last.
            ->sortBy(fn ($l) => ($l['rack'] ? '0'.$l['rack'] : '1').'|'.$l['store'].'|'.$l['roll_no'])->values();

        return ApiResponse::ok($rows->all());
    }

    /* ---------------- Grey Fabric Opening Stock ---------------- */

    public function openings(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $f = $request->validate([
            'kind' => ['nullable', Rule::in(KnitGreyLot::KINDS)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'buyer' => ['nullable', 'string'],
            'order' => ['nullable', 'string'], 'store' => ['nullable', 'string'], 'finalized' => ['nullable', Rule::in(['YES', 'NO'])],
        ]);
        $rows = KnitGreyOpening::query()
            ->when(! empty($f['kind']), fn ($q) => $q->where('kind', $f['kind']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('opening_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('opening_date', '<=', $f['to']))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_id', $f['order']))
            ->when(! empty($f['store']), fn ($q) => $q->where('store_id', $f['store']))
            ->when(! empty($f['finalized']), fn ($q) => $q->where('finalized', $f['finalized'] === 'YES'))
            ->orderByDesc('opening_date')->orderByDesc('opening_no')->limit(500)->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');
        $buyers = DB::table('buyers')->whereIn('id', $rows->pluck('buyer_id')->filter()->unique())->get(['id', 'name', 'short_name'])->keyBy('id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->unique())->pluck('name', 'id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (KnitGreyOpening $r) use ($orders, $buyers, $stores, $parties) {
            $o = $orders[$r->order_id] ?? null;
            $b = $buyers[$r->buyer_id] ?? null;

            return collect($r->only(['id', 'opening_no', 'kind', 'store_id', 'buyer_id', 'order_id', 'party_id', 'fabric', 'colour_id', 'colour_name', 'finish_dia', 'gsm', 'qty', 'rate',
                'remarks', 'finalized']))->all() + [
                    'opening_date' => $r->opening_date->toDateString(), 'store' => $stores[$r->store_id] ?? null, 'buyer' => $b ? ($b->short_name ?: $b->name) : null,
                    'party' => $parties[$r->party_id] ?? null, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no) : null,
                    'value' => round((float) $r->qty * (float) ($r->rate ?? 0), 2),
                ];
        })->values()->all());
    }

    public function saveOpening(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? KnitGreyOpening::query()->where('opening_no', $no)->firstOrFail() : null;
        if ($row?->finalized) {
            throw ValidationException::withMessages(['finalized' => __('knitting.fs.v.opening_final', ['no' => $row->opening_no])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'kind' => ['required', Rule::in(KnitGreyLot::KINDS)], 'opening_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'party_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'fabric' => ['required', 'string', 'max:160'], 'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'colour_name' => ['nullable', 'string', 'max:80'], 'finish_dia' => ['nullable', 'string', 'max:24'], 'gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'qty.required' => __('knitting.fs.v.opening_qty'), 'qty.gt' => __('knitting.fs.v.opening_qty'), 'fabric.required' => __('knitting.fs.v.fabric'),
            'store_id.required' => __('knitting.fs.v.store'), 'opening_date.required' => __('knitting.fs.v.opening_date'), 'party_id.required' => __('knitting.fs.v.party'),
        ]);
        if (! empty($data['order_id'])) {
            $data['buyer_id'] = ($data['buyer_id'] ?? null) ?: Order::query()->whereKey($data['order_id'])->value('buyer_id');
        }
        $values = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all() + ['buyer_id' => null, 'order_id' => null, 'party_id' => null, 'colour_id' => null];
        if ($row) {
            $row->update($values);
        } else {
            $row = KnitGreyOpening::query()->create($values + [
                'opening_no' => GreyStore::nextNumber('GOB', 'knit_grey_openings', 'opening_no', $data['opening_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
        }

        return $no ? ApiResponse::ok($row->refresh()->toArray()) : ApiResponse::created($row->refresh()->toArray());
    }

    /** The ticked open lines, finalised into the grey ledger in one go. */
    public function finalizeOpenings(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $data = $request->validate(['nos' => ['required', 'array', 'min:1'], 'nos.*' => ['string']], ['nos.required' => __('knitting.fs.v.tick'), 'nos.min' => __('knitting.fs.v.tick')]);
        $rows = KnitGreyOpening::query()->whereIn('opening_no', $data['nos'])->where('finalized', false)->get();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['nos' => __('knitting.fs.v.tick')]);
        }
        foreach ($rows as $r) {
            GreyStore::assertOpen($r->opening_date->toDateString(), 'nos');
        }
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $r) {
                $date = $r->opening_date->toDateString();
                $lot = GreyStore::lotFor(collect($r->only(['kind', 'store_id', 'buyer_id', 'order_id', 'party_id', 'fabric', 'colour_id', 'colour_name', 'finish_dia', 'gsm']))->all(), $date, (float) ($r->rate ?? 0));
                GreyStore::post('OPENING', $r->opening_no, $date, [['lot_id' => $lot->id, 'qty' => (float) $r->qty, 'value' => (float) $r->qty * (float) ($r->rate ?? 0), 'doc_line_id' => $r->id]]);
                $r->update(['finalized' => true, 'lot_id' => $lot->id]);
            }
        });

        return ApiResponse::ok(['finalized' => $rows->pluck('opening_no')->values()->all()]);
    }

    /* ---------------- Grey Fabric Stock Close ---------------- */

    public function closes(): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok(KnitGreyMonthClose::query()->orderByDesc('period')->get()->map(fn (KnitGreyMonthClose $c) => [
            'period' => $c->period, 'remarks' => $c->remarks, 'closed_at' => $c->closed_at->toIso8601String(), 'by' => $users[$c->closed_by] ?? null,
            'qty' => round((float) KnitGreyMove::query()->where('move_date', '<', CarbonImmutable::parse($c->period.'-01')->addMonth()->toDateString())->sum('qty'), 3),
        ])->values()->all());
    }

    public function exceptions(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $f = $request->validate(['period' => ['required', 'date_format:Y-m']], ['period.required' => __('knitting.fs.v.period')]);

        return ApiResponse::ok(['period' => $f['period'], 'exceptions' => $this->blockers($f['period'])]);
    }

    public function close(Request $request): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $f = $request->validate(['period' => ['required', 'date_format:Y-m'], 'remarks' => ['nullable', 'string', 'max:255']], ['period.required' => __('knitting.fs.v.period')]);
        $start = CarbonImmutable::parse($f['period'].'-01');
        if ($start->addMonth()->greaterThan(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['period' => __('knitting.fs.v.not_finished', ['period' => $start->format('F Y')])]);
        }
        if (KnitGreyMonthClose::query()->where('period', $f['period'])->exists()) {
            throw ValidationException::withMessages(['period' => __('knitting.fs.v.already_closed', ['period' => $start->format('F Y')])]);
        }
        $blockers = $this->blockers($f['period']);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['period' => __('knitting.fs.v.exceptions', ['count' => count($blockers), 'period' => $start->format('F Y')])]);
        }
        KnitGreyMonthClose::query()->create(['period' => $f['period'], 'remarks' => ($f['remarks'] ?? '') ?: null, 'closed_at' => now(), 'closed_by' => $this->caller()->auditUserId()]);

        return ApiResponse::created(['period' => $f['period']]);
    }

    /* ---------------- helpers ---------------- */

    /**
     * What would stop a month closing: papers dated in it still half way.
     *
     * @return list<array{kind: string, no: string, date: string, status: string}>
     */
    private function blockers(string $period): array
    {
        $from = $period.'-01';
        $to = CarbonImmutable::parse($from)->endOfMonth()->toDateString();
        $out = [];
        foreach (KnitGreyReceipt::query()->where('status', 'DRAFT')->whereBetween('receive_date', [$from, $to])->get() as $r) {
            $out[] = ['kind' => 'RECEIPT', 'no' => $r->note_no, 'date' => $r->receive_date->toDateString(), 'status' => 'DRAFT'];
        }
        foreach (KnitGreyOpening::query()->where('finalized', false)->whereBetween('opening_date', [$from, $to])->get() as $r) {
            $out[] = ['kind' => 'OPENING', 'no' => $r->opening_no, 'date' => $r->opening_date->toDateString(), 'status' => 'OPEN'];
        }
        foreach (KnitGreyTransfer::query()->whereIn('status', ['DRAFT', 'SUBMITTED'])->whereBetween('transfer_date', [$from, $to])->get() as $r) {
            $out[] = ['kind' => 'TRANSFER', 'no' => $r->transfer_no, 'date' => $r->transfer_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitGreyAdjustment::query()->where('activity', 'ACTIVE')->whereIn('status', ['DRAFT', 'VERIFIED'])->whereBetween('adj_date', [$from, $to])->get() as $r) {
            $out[] = ['kind' => 'ADJUSTMENT', 'no' => $r->adj_no, 'date' => $r->adj_date->toDateString(), 'status' => $r->status];
        }

        return $out;
    }

    private function openNote(KnitGreyReceipt $note): void
    {
        if ($note->status === 'CONFIRMED') {
            throw ValidationException::withMessages(['status' => __('knitting.fs.v.day_confirmed', ['no' => $note->note_no])]);
        }
        GreyStore::assertOpen($note->receive_date->toDateString(), 'receive_date');
    }

    private function takeBack(KnitGreyReceipt $note, KnitGreyReceiptRoll $row): void
    {
        GreyStore::unpost('RECEIVE', $note->note_no, $row->id);
        KnitRoll::query()->whereKey($row->roll_id)->update(['movement' => 'IN_KNITTING']);
        $row->delete();
    }

    /** @return array<string, mixed> */
    private function presentDay(?string $storeId, string $date): array
    {
        $note = $storeId ? KnitGreyReceipt::query()->where('store_id', $storeId)->whereDate('receive_date', $date)->first() : null;
        $notes = $storeId ? collect([$note])->filter() : KnitGreyReceipt::query()->whereDate('receive_date', $date)->get();
        $rows = KnitGreyReceiptRoll::query()->whereIn('receipt_id', $notes->pluck('id'))->orderByDesc('scanned_at')->orderByDesc('id')->get();
        $lots = KnitGreyLot::query()->whereIn('id', $rows->pluck('lot_id'))->get()->keyBy('id');
        $names = GreyStore::names($lots->values());
        $status = $notes->pluck('status', 'id');
        $bins = DB::table('bins')->whereIn('id', $rows->pluck('bin_id')->filter()->unique())->pluck('name', 'id');

        return [
            'date' => $date, 'store_id' => $storeId, 'note_no' => $note?->note_no, 'status' => $note?->status, 'company' => GreyStore::companyName(),
            'store' => $storeId ? DB::table('stores')->where('id', $storeId)->value('name') : null,
            'confirmed_at' => $note?->confirmed_at?->toIso8601String(),
            'rolls' => $rows->map(fn (KnitGreyReceiptRoll $r) => GreyStore::describe($lots[$r->lot_id], $names) + [
                'id' => $r->id, 'weight' => (float) $r->weight, 'rack' => $bins[$r->bin_id] ?? null, 'note_status' => $status[$r->receipt_id] ?? null,
                'scanned_at' => $r->scanned_at->toIso8601String(),
            ])->values()->all(),
            'total' => round((float) $rows->sum('weight'), 3),
        ];
    }
}
