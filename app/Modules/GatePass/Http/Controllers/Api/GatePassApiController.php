<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Http\Controllers\Api;

use App\Modules\GatePass\Models\GatePass;
use App\Modules\GatePass\Models\GatePassCategory;
use App\Modules\GatePass\Models\GatePassEvent;
use App\Modules\GatePass\Models\GatePassIn;
use App\Modules\GatePass\Models\GatePassInLine;
use App\Modules\GatePass\Models\GatePassItem;
use App\Modules\GatePass\Models\GatePassLine;
use App\Modules\GatePass\Models\GatePassLocation;
use App\Modules\GatePass\Models\GatePassLocationType;
use App\Modules\GatePass\Models\GatePassPurpose;
use App\Modules\GatePass\Support\GatePassBook;
use App\Modules\GatePass\Support\GatePassDefaults;
use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
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
 * Gate Pass → Create, Approval, Out, In, Return Reconciliation and List
 * (AnnotechERP 11/03–11/04), as Brickland keeps them. A pass is prepared —
 * a Main Gate Pass for an employee, or an Item Gate Pass for goods with
 * their items — saved as a draft or submitted; the approver approves it
 * (Gate Security may then let it out), rejects it, or sends an approved one
 * back for amendment; any of those before it goes out may be cancelled.
 * Gate Security confirms OUT, which completes an OUT ONLY pass. A returnable
 * pass comes back in one or more Gate INs — partly, in full, or with a final
 * return that declares the rest short — and a short pass is closed by an
 * approver who authorises the short quantity. Every step is on the pass's
 * audit trail. A pass is addressed by its number (GP-2026-000001), never
 * its key.
 */
class GatePassApiController extends ApiController
{
    public const UNITS = ['PCS', 'DZN', 'SET', 'KG', 'YDS', 'MTR', 'ROLL', 'CTN', 'BOX', 'BAG', 'LTR', 'NOS'];

    /** Anyone who works with gate passes reads them. */
    public const READERS = ['gatepass.view', 'gatepass.create', 'gatepass.approve', 'gatepass.gate'];

    /** Which list each screen asks for, and who may ask. */
    private const LISTS = [
        'approval' => ['gatepass.approve'], 'approved' => ['gatepass.approve', 'gatepass.gate'], 'out-today' => ['gatepass.gate'],
        'in-await' => ['gatepass.gate'], 'in-today' => ['gatepass.gate'], 'recon' => ['gatepass.approve', 'gatepass.view'], 'all' => self::READERS,
    ];

    /** What the Create Gate Pass form picks from. */
    public function options(): JsonResponse
    {
        $this->allow('gatepass.create');
        $company = app(TenantContext::class)->companyId();
        GatePassDefaults::categories($company);
        GatePassDefaults::locationTypes($company);
        GatePassDefaults::purposes($company);
        $types = GatePassLocationType::query()->pluck('name', 'id');

        return ApiResponse::ok([
            'next_no' => GatePassBook::previewNumber(now()->toDateString()),
            'categories' => GatePassCategory::query()->where('active', true)->orderByDesc('kind')->orderBy('name')->get()
                ->map(fn (GatePassCategory $c) => ['value' => $c->id, 'label' => $c->name, 'kind' => $c->kind])->all(),
            'purposes' => GatePassPurpose::query()->where('active', true)->orderBy('code')->get()->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all(),
            'locations' => GatePassLocation::query()->where('active', true)->orderBy('name')->get()->map(fn (GatePassLocation $l) => [
                'value' => $l->id, 'label' => $l->name, 'type' => $types[$l->location_type_id] ?? null, 'address' => $l->address, 'contact_person' => $l->contact_person, 'mobile' => $l->mobile,
            ])->all(),
            'items' => GatePassItem::query()->where('active', true)->orderBy('code')->get()->map(fn (GatePassItem $i) => ['value' => $i->id, 'label' => "{$i->code} — {$i->name}", 'unit' => $i->unit])->all(),
            'units' => self::UNITS,
            'buyers' => Buyer::query()->where('active', true)->orderBy('name')->get()->map(fn (Buyer $b) => ['value' => $b->id, 'label' => $b->name])->all(),
            'orders' => Order::query()->orderByDesc('order_date')->orderByDesc('order_no')->limit(500)->get()->map(fn (Order $o) => [
                'value' => $o->id, 'label' => $o->order_no.($o->style_no ? " — {$o->style_no}" : ''), 'buyer_id' => $o->buyer_id, 'style' => $o->style_no, 'po_no' => $o->po_no,
            ])->all(),
        ]);
    }

    /** The carrier, found by their employee card number. */
    public function employee(string $card): JsonResponse
    {
        $this->allow('gatepass.create');
        $employee = HrEmployee::query()->whereRaw('upper(card_no) = ?', [strtoupper(trim($card))])->first()
            ?? throw ValidationException::withMessages(['card_no' => __('gatepass.gp.v.no_employee', ['card' => trim($card)])]);

        return ApiResponse::ok($this->employeeCard($employee));
    }

    /** A screen's list — or, for Gate Pass List, every pass that matches the filters. */
    public function index(Request $request): JsonResponse
    {
        $list = (string) $request->query('list', 'all');
        abort_unless(isset(self::LISTS[$list]), 404);
        $this->allowAny(self::LISTS[$list]);
        $passes = $this->listed($list, $request)->get();
        $meta = $list === 'recon' ? ['short_waiting' => $passes->where('status', 'SHORT')->count()] : [];

        return ApiResponse::ok(['rows' => $this->rows($passes, $list === 'in-today')] + $meta);
    }

    /** Gate Pass List as a workbook, with the screen's filters. */
    public function excel(Request $request): StreamedResponse
    {
        $this->allowAny(self::READERS);
        $rows = $this->rows($this->listed('all', $request)->get());
        $head = ['gp_no', 'date', 'category', 'type', 'purpose', 'destination', 'taken_by', 'items', 'qty', 'status', 'return'];

        return response()->streamDownload(function () use ($rows, $head): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($k) => __("gatepass.gp.col.{$k}"), $head)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues([
                    $r['gp_no'], $r['date'], __("gatepass.gp.kind.{$r['kind']}"), __("gatepass.gp.type.{$r['pass_type']}"), $r['purpose'], $r['destination'], $r['taken_by'],
                    implode(', ', $r['items']), GatePassBook::withUnit((float) $r['out'], $r['unit']), __("gatepass.gp.status.{$r['status']}"),
                    $r['return'] ? __("gatepass.gp.return.{$r['return']}") : '',
                ]));
            }
            $writer->close();
        }, 'gate-passes-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function show(string $pass): JsonResponse
    {
        $this->allowAny(self::READERS);

        return ApiResponse::ok($this->payload($this->find($pass)));
    }

    /** Save as Draft, or Submit for Approval, a new pass. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('gatepass.create');
        $submit = $request->boolean('submit');
        $data = $this->validated($request, $submit);
        $user = $this->caller()->auditUserId();
        $pass = DB::transaction(function () use ($data, $submit, $user): GatePass {
            $pass = GatePass::query()->create([...$data['fields'], 'gp_no' => GatePassBook::nextNumber($data['fields']['gp_date']), 'status' => 'DRAFT', 'prepared_by' => $user]);
            $this->writeLines($pass, $data['lines']);
            GatePassBook::event($pass, 'CREATED', null, $user);
            if ($submit) {
                $pass->update(['status' => 'PENDING']);
                GatePassBook::event($pass, 'SUBMITTED', null, $user);
            }

            return $pass;
        });

        return ApiResponse::created($this->payload($pass->fresh()));
    }

    /** Change a draft — a new one, or one sent back for amendment — and save it again or submit it. */
    public function update(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.create');
        $gp = $this->draft($pass);
        $submit = $request->boolean('submit');
        $data = $this->validated($request, $submit);
        $user = $this->caller()->auditUserId();
        DB::transaction(function () use ($gp, $data, $submit, $user): void {
            $gp->update($data['fields']);
            GatePassLine::query()->where('gate_pass_id', $gp->id)->delete();
            $this->writeLines($gp, $data['lines']);
            if ($submit) {
                $gp->update(['status' => 'PENDING']);
                GatePassBook::event($gp, 'SUBMITTED', null, $user);
            }
        });

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Submit a saved draft, once nothing it needs is missing. */
    public function submit(string $pass): JsonResponse
    {
        $this->allow('gatepass.create');
        $gp = $this->draft($pass);
        if ($missing = $this->missing($gp)) {
            throw ValidationException::withMessages(['pass' => __('gatepass.gp.v.still_needed', ['list' => implode(', ', $missing)])]);
        }
        $gp->update(['status' => 'PENDING']);
        GatePassBook::event($gp, 'SUBMITTED', null, $this->caller()->auditUserId());

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Approve: Gate Security may now let it out. */
    public function approve(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.approve');
        $gp = $this->inStatus($pass, ['PENDING'], 'not_pending');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $user = $this->caller()->auditUserId();
        $gp->update(['status' => 'APPROVED', 'approved_by' => $user, 'approved_at' => now()]);
        GatePassBook::event($gp, 'APPROVED', $data['note'] ?? null, $user);

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Reject, with the reason the preparer will see. */
    public function reject(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.approve');
        $gp = $this->inStatus($pass, ['PENDING'], 'not_pending');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('gatepass.gp.v.reason')]);
        $gp->update(['status' => 'REJECTED']);
        GatePassBook::event($gp, 'REJECTED', $data['reason'], $this->caller()->auditUserId());

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Send an approved pass back for amendment: a draft again, to change and resubmit. */
    public function sendBack(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.approve');
        $gp = $this->inStatus($pass, ['APPROVED'], 'not_approved');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('gatepass.gp.v.reason')]);
        $gp->update(['status' => 'DRAFT', 'approved_by' => null, 'approved_at' => null]);
        GatePassBook::event($gp, 'SENT_BACK', $data['reason'], $this->caller()->auditUserId());

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Cancel a pass that has not gone out — by the approver, or a draft or pending pass by whoever prepared it. It stays on record. */
    public function cancel(Request $request, string $pass): JsonResponse
    {
        $gp = $this->inStatus($pass, ['DRAFT', 'PENDING', 'APPROVED'], 'not_cancellable');
        $mine = $gp->prepared_by === $this->caller()->auditUserId() && $gp->status !== 'APPROVED';
        if (! ($mine && $this->caller()->can('gatepass.create'))) {
            $this->allow('gatepass.approve');
        }
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $gp->update(['status' => 'CANCELLED']);
        GatePassBook::event($gp, 'CANCELLED', $data['reason'] ?? null, $this->caller()->auditUserId());

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Gate OUT: the date, time and guard are recorded; an OUT ONLY pass is then complete, a returnable one owed back. */
    public function out(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.gate');
        $gp = $this->inStatus($pass, ['APPROVED'], 'not_approved');
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);
        $user = $this->caller()->auditUserId();
        $gp->update(['status' => $gp->returnable() ? 'OUT' : 'COMPLETED', 'out_at' => now(), 'out_by' => $user, 'out_remarks' => $data['remarks'] ?? null]);
        GatePassBook::event($gp, 'OUT', $data['remarks'] ?? null, $user);

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /**
     * Gate IN: what came back of each line (none above its balance). Short of
     * the balance it is Partially Returned; a final return declares the rest
     * short, with a reason, for an approver to close; all of it closes the
     * pass. An employee's return closes a Main pass.
     */
    public function in(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.gate');
        $gp = $this->find($pass);
        if (! $gp->returnable() || ! in_array($gp->status, GatePass::AWAITING_RETURN, true)) {
            throw ValidationException::withMessages(['pass' => __('gatepass.gp.v.not_out', ['no' => $gp->gp_no])]);
        }
        $data = $request->validate([
            'lines' => ['array'], 'lines.*.line_id' => ['required', 'string'], 'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'final' => ['boolean'], 'short_reason' => ['nullable', Rule::in(GatePass::SHORT_REASONS)], 'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $this->caller()->auditUserId();
        if ($gp->kind === 'MAIN') {
            DB::transaction(function () use ($gp, $data, $user): void {
                $in = GatePassIn::query()->create(['gate_pass_id' => $gp->id, 'gpi_no' => GatePassBook::nextInNumber(now()->toDateString()), 'in_at' => now(), 'final' => true, 'remarks' => $data['remarks'] ?? null, 'received_by' => $user]);
                $gp->update(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $user]);
                GatePassBook::event($gp, 'IN', trim("{$in->gpi_no} ".($data['remarks'] ?? '')), $user);
            });

            return ApiResponse::ok($this->payload($gp->fresh()));
        }

        $lines = GatePassLine::query()->where('gate_pass_id', $gp->id)->orderBy('sort_order')->get();
        $returned = GatePassBook::returned(collect([$gp->id]));
        $given = collect($data['lines'] ?? [])->keyBy('line_id');
        $final = (bool) ($data['final'] ?? false);
        $items = GatePassItem::query()->whereIn('id', $lines->pluck('item_id'))->pluck('name', 'id');
        $errors = [];
        $plan = [];
        foreach ($lines as $i => $l) {
            $balance = round((float) $l->quantity - ($returned[$l->id] ?? 0) - (float) $l->short_qty, 3);
            $q = round((float) ($given[$l->id]['quantity'] ?? 0), 3);
            if ($q > $balance + 0.0005) {
                $errors["lines.{$i}.quantity"] = __('gatepass.gp.v.in_over', ['item' => $items[$l->item_id] ?? '', 'qty' => GatePassBook::withUnit($balance, $l->unit)]);
            }
            $plan[] = [$l, $q, max(0, round($balance - $q, 3))];
        }
        $coming = array_sum(array_column($plan, 1));
        $left = array_sum(array_column($plan, 2));
        if (! $errors && ! $final && $coming <= 0.0005) {
            $errors['lines'] = __('gatepass.gp.v.in_none');
        }
        if (! $errors && $final && $left > 0.0005 && empty($data['short_reason'])) {
            $errors['short_reason'] = __('gatepass.gp.v.short_reason');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $short = $final && $left > 0.0005;
        DB::transaction(function () use ($gp, $plan, $data, $user, $short, $coming, $left, $lines): void {
            $in = GatePassIn::query()->create([
                'gate_pass_id' => $gp->id, 'gpi_no' => GatePassBook::nextInNumber(now()->toDateString()), 'in_at' => now(), 'final' => (bool) ($data['final'] ?? false),
                'short_reason' => $short ? $data['short_reason'] : null, 'remarks' => $data['remarks'] ?? null, 'received_by' => $user,
            ]);
            foreach ($plan as [$l, $q, $rest]) {
                if ($q > 0.0005) {
                    GatePassInLine::query()->create(['gate_pass_in_id' => $in->id, 'gate_pass_line_id' => $l->id, 'quantity' => $q]);
                }
                if ($short && $rest > 0.0005) {
                    $l->update(['short_qty' => round((float) $l->short_qty + $rest, 3), 'short_reason' => $data['short_reason']]);
                }
            }
            $status = $left <= 0.0005 ? 'CLOSED' : ($short ? 'SHORT' : 'PARTIAL');
            $gp->update(['status' => $status] + ($status === 'CLOSED' ? ['closed_at' => now(), 'closed_by' => $user] : []));
            $unit = $lines->pluck('unit')->unique()->count() === 1 ? $lines->first()->unit : null;
            $note = __('gatepass.gp.audit.in', ['no' => $in->gpi_no, 'qty' => GatePassBook::withUnit($coming, $unit, 'en')], 'en')
                .($short ? ' '.__('gatepass.gp.audit.in_short', ['qty' => GatePassBook::withUnit($left, $unit, 'en'), 'reason' => __('gatepass.gp.short_reason.'.$data['short_reason'], [], 'en')], 'en') : '')
                .(! empty($data['remarks']) ? " — {$data['remarks']}" : '');
            GatePassBook::event($gp, 'IN', $note, $user);
        });

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** Approve Short & Close: the approver authorises the short quantity; the pass closes and the short stays on record. */
    public function close(Request $request, string $pass): JsonResponse
    {
        $this->allow('gatepass.approve');
        $gp = $this->inStatus($pass, ['SHORT'], 'not_short');
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => __('gatepass.gp.v.close_note')]);
        $user = $this->caller()->auditUserId();
        $gp->update(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $user]);
        GatePassBook::event($gp, 'CLOSED', $data['note'], $user);

        return ApiResponse::ok($this->payload($gp->fresh()));
    }

    /** @return Builder<GatePass> */
    private function listed(string $list, Request $request): Builder
    {
        $q = GatePass::query();

        return match ($list) {
            'approval' => $q->where('status', 'PENDING')->orderBy('gp_date')->orderBy('gp_no'),
            'approved' => $q->where('status', 'APPROVED')->orderBy('approved_at')->orderBy('gp_no'),
            'out-today' => $q->whereNotNull('out_at')->whereDate('out_at', today())->orderByDesc('out_at'),
            'in-await' => $q->where('pass_type', 'RETURNABLE')->whereIn('status', GatePass::AWAITING_RETURN)->orderByRaw('expected_return asc nulls last')->orderBy('gp_no'),
            'in-today' => $q->whereIn('id', GatePassIn::query()->whereDate('in_at', today())->select('gate_pass_id'))
                ->orderByDesc(GatePassIn::query()->selectRaw('max(in_at)')->whereColumn('gate_pass_id', 'gate_passes.id')),
            'recon' => $q->where('kind', 'ITEM')->where('pass_type', 'RETURNABLE')->whereIn('status', ['OUT', 'PARTIAL', 'SHORT', 'CLOSED'])->orderByDesc('gp_date')->orderByDesc('gp_no')->limit(500),
            default => $this->filtered($q, $request)->orderByDesc('gp_date')->orderByDesc('gp_no')->limit(500),
        };
    }

    /**
     * Gate Pass List's filters: the search across number, carrier, employee,
     * item, buyer, style, PO and destination, then each field of its own.
     *
     * @param  Builder<GatePass>  $q
     * @return Builder<GatePass>
     */
    private function filtered(Builder $q, Request $request): Builder
    {
        $f = fn (string $k): string => trim((string) $request->query($k, ''));
        $like = fn (string $v): string => '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';
        $employees = fn (string $v) => HrEmployee::query()->where(fn ($e) => $e->where('card_no', 'ilike', $like($v))->orWhere('name', 'ilike', $like($v)))->select('id');
        $items = fn (string $v) => GatePassLine::query()->whereIn('item_id', GatePassItem::query()->where(fn ($i) => $i->where('name', 'ilike', $like($v))->orWhere('code', 'ilike', $like($v)))->select('id'))->select('gate_pass_id');
        $buyers = fn (string $v) => Buyer::query()->where('name', 'ilike', $like($v))->select('id');

        if ($v = $f('q')) {
            $q->where(fn ($w) => $w->where('gp_no', 'ilike', $like($v))->orWhere('carrier_name', 'ilike', $like($v))->orWhere('style', 'ilike', $like($v))
                ->orWhere('po_no', 'ilike', $like($v))->orWhere('destination_text', 'ilike', $like($v))->orWhereIn('employee_id', $employees($v))
                ->orWhereIn('id', $items($v))->orWhereIn('buyer_id', $buyers($v))
                ->orWhereIn('location_id', GatePassLocation::query()->where('name', 'ilike', $like($v))->select('id')));
        }
        if ($v = $f('from')) {
            $q->whereDate('gp_date', '>=', $v);
        }
        if ($v = $f('to')) {
            $q->whereDate('gp_date', '<=', $v);
        }
        foreach (['category' => 'kind', 'type' => 'pass_type', 'status' => 'status', 'purpose' => 'purpose_id', 'location' => 'location_id', 'carrier' => 'carrier_type'] as $key => $column) {
            if ($v = $f($key)) {
                $q->where($column, $v);
            }
        }
        if ($v = $f('employee')) {
            $q->whereIn('employee_id', $employees($v));
        }
        if ($v = $f('item')) {
            $q->whereIn('id', $items($v));
        }
        if ($v = $f('buyer')) {
            $q->whereIn('buyer_id', $buyers($v));
        }
        if ($v = $f('style')) {
            $q->where('style', 'ilike', $like($v));
        }
        if ($v = $f('po')) {
            $q->where('po_no', 'ilike', $like($v));
        }

        return $q;
    }

    /**
     * @return array{fields: array<string, mixed>, lines: list<array{item_id: string, unit: string, quantity: float, remarks: ?string}>}
     */
    private function validated(Request $request, bool $submit): array
    {
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'gp_date' => ['required', 'date'], 'category_id' => ['required', $in('gate_pass_categories')], 'pass_type' => ['required', Rule::in(['RETURNABLE', 'NON_RETURNABLE'])],
            'buyer_id' => ['nullable', $in('buyers')], 'order_id' => ['nullable', $in('merch_orders')], 'style' => ['nullable', 'string', 'max:120'], 'po_no' => ['nullable', 'string', 'max:120'],
            'purpose_id' => ['nullable', $in('gate_pass_purposes')], 'location_id' => ['nullable', $in('gate_pass_locations')], 'destination_text' => ['nullable', 'string', 'max:200'],
            'expected_return' => ['nullable', 'date'], 'carrier_type' => ['nullable', Rule::in(['EMPLOYEE', 'NON_EMPLOYEE'])], 'employee_id' => ['nullable', $in('hr_employees')],
            'carrier_name' => ['nullable', 'string', 'max:120'], 'carrier_company' => ['nullable', 'string', 'max:160'], 'carrier_mobile' => ['nullable', 'string', 'max:32'],
            'carrier_nid' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:60'], 'carrier_remarks' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'], 'documents' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'], 'lines.*.item_id' => ['nullable', 'string'], 'lines.*.unit' => ['nullable', 'string'], 'lines.*.quantity' => ['nullable'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'gp_date.required' => __('gatepass.gp.v.date'), 'category_id.required' => __('gatepass.gp.v.category'), 'pass_type.required' => __('gatepass.gp.v.type'),
        ]);
        ksort($data['lines']);
        $kind = GatePassCategory::query()->whereKey($data['category_id'])->value('kind');
        $returnable = $data['pass_type'] === 'RETURNABLE';
        $carrier = $kind === 'MAIN' ? 'EMPLOYEE' : ($data['carrier_type'] ?? 'EMPLOYEE');
        $v = fn (string $k) => trim((string) ($data[$k] ?? '')) ?: null;
        $fields = [
            'gp_date' => $data['gp_date'], 'category_id' => $data['category_id'], 'kind' => $kind, 'pass_type' => $data['pass_type'],
            'buyer_id' => $kind === 'ITEM' ? $v('buyer_id') : null, 'order_id' => $kind === 'ITEM' ? $v('order_id') : null,
            'style' => $kind === 'ITEM' ? $v('style') : null, 'po_no' => $kind === 'ITEM' ? $v('po_no') : null, 'purpose_id' => $v('purpose_id'), 'location_id' => $v('location_id'),
            'destination_text' => $kind === 'MAIN' ? $v('destination_text') : null, 'expected_return' => $kind === 'ITEM' && $returnable ? $v('expected_return') : null,
            'carrier_type' => $carrier, 'employee_id' => $carrier === 'EMPLOYEE' ? $v('employee_id') : null,
            'carrier_name' => $carrier === 'NON_EMPLOYEE' ? $v('carrier_name') : null, 'carrier_company' => $carrier === 'NON_EMPLOYEE' ? $v('carrier_company') : null,
            'carrier_mobile' => $carrier === 'NON_EMPLOYEE' ? $v('carrier_mobile') : null, 'carrier_nid' => $carrier === 'NON_EMPLOYEE' ? $v('carrier_nid') : null,
            'vehicle_no' => $kind === 'ITEM' ? $v('vehicle_no') : null, 'carrier_remarks' => $kind === 'ITEM' ? $v('carrier_remarks') : null,
            'remarks' => $v('remarks'), 'documents' => $v('documents'),
        ];

        $errors = [];
        if ($fields['expected_return'] && $fields['expected_return'] < $fields['gp_date']) {
            $errors['expected_return'] = __('gatepass.gp.v.return_before');
        }
        $lines = [];
        if ($kind === 'ITEM') {
            $given = collect(array_values($data['lines']))->map(fn ($l) => is_array($l) ? $l : [])
                ->filter(fn ($l) => trim((string) ($l['item_id'] ?? '')) !== '' || trim((string) ($l['quantity'] ?? '')) !== '')->values();
            $items = GatePassItem::query()->where('active', true)->whereIn('id', $given->pluck('item_id')->filter()->all())->pluck('id')->flip();
            foreach ($given as $i => $l) {
                if (! isset($items[$l['item_id'] ?? ''])) {
                    $errors["lines.{$i}.item_id"] = __('gatepass.gp.v.line_item', ['n' => $i + 1]);
                } elseif (! in_array($l['unit'] ?? '', self::UNITS, true)) {
                    $errors["lines.{$i}.unit"] = __('gatepass.gp.v.line_unit', ['n' => $i + 1]);
                } elseif (! is_numeric($l['quantity'] ?? null) || (float) $l['quantity'] <= 0) {
                    $errors["lines.{$i}.quantity"] = __('gatepass.gp.v.line_qty', ['n' => $i + 1]);
                } else {
                    $lines[] = ['item_id' => $l['item_id'], 'unit' => $l['unit'], 'quantity' => round((float) $l['quantity'], 3), 'remarks' => trim((string) ($l['remarks'] ?? '')) ?: null];
                }
            }
        }
        if ($submit) {
            foreach ($this->missingFields($fields, count($lines)) as $key => $label) {
                $errors[$key] ??= __('gatepass.gp.v.required', ['field' => $label]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['fields' => $fields, 'lines' => $lines];
    }

    /** @return list<string> what a saved draft still lacks before it can be submitted */
    private function missing(GatePass $gp): array
    {
        return array_values($this->missingFields($gp->only(['kind', 'pass_type', 'purpose_id', 'location_id', 'expected_return', 'carrier_type', 'employee_id', 'carrier_name', 'carrier_mobile']),
            GatePassLine::query()->where('gate_pass_id', $gp->id)->count()));
    }

    /**
     * What Brickland's "Still needed" lists: purpose; for goods the destination,
     * a returnable pass's expected return, the carrier and at least one item.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, string> field => its label
     */
    private function missingFields(array $f, int $lines): array
    {
        $label = fn (string $k) => __("gatepass.gp.f.{$k}");
        $out = [];
        if (! $f['purpose_id']) {
            $out['purpose_id'] = $label('purpose');
        }
        if ($f['kind'] === 'ITEM' && ! $f['location_id']) {
            $out['location_id'] = $label('destination');
        }
        if ($f['kind'] === 'ITEM' && $f['pass_type'] === 'RETURNABLE' && ! $f['expected_return']) {
            $out['expected_return'] = $label('expected_return');
        }
        if ($f['carrier_type'] === 'EMPLOYEE' && ! $f['employee_id']) {
            $out['employee_id'] = $label('card_no');
        }
        if ($f['carrier_type'] === 'NON_EMPLOYEE' && ! $f['carrier_name']) {
            $out['carrier_name'] = $label('person_name');
        }
        if ($f['carrier_type'] === 'NON_EMPLOYEE' && ! $f['carrier_mobile']) {
            $out['carrier_mobile'] = $label('mobile');
        }
        if ($f['kind'] === 'ITEM' && $lines === 0) {
            $out['lines'] = $label('items');
        }

        return $out;
    }

    private function writeLines(GatePass $gp, array $lines): void
    {
        foreach ($lines as $i => $l) {
            GatePassLine::query()->create(['gate_pass_id' => $gp->id, 'item_id' => $l['item_id'], 'unit' => $l['unit'], 'quantity' => $l['quantity'], 'remarks' => $l['remarks'], 'sort_order' => $i + 1]);
        }
    }

    /** A draft its preparer may change — or the approver, who sent it back. */
    private function draft(string $no): GatePass
    {
        $gp = $this->inStatus($no, ['DRAFT'], 'not_draft');
        if ($gp->prepared_by !== $this->caller()->auditUserId() && ! $this->caller()->can('gatepass.approve')) {
            throw ValidationException::withMessages(['pass' => __('gatepass.gp.v.not_yours', ['no' => $gp->gp_no])]);
        }

        return $gp;
    }

    /** @param list<string> $statuses */
    private function inStatus(string $no, array $statuses, string $message): GatePass
    {
        $gp = $this->find($no);
        if (! in_array($gp->status, $statuses, true)) {
            throw ValidationException::withMessages(['pass' => __("gatepass.gp.v.{$message}", ['no' => $gp->gp_no])]);
        }

        return $gp;
    }

    private function find(string $no): GatePass
    {
        return GatePass::query()->where('gp_no', strtoupper(trim($no)))->first()
            ?? throw ValidationException::withMessages(['pass' => __('gatepass.gp.v.missing', ['no' => strtoupper(trim($no))])]);
    }

    /** @param list<string> $permissions */
    private function allowAny(array $permissions): void
    {
        if (! collect($permissions)->contains(fn ($p) => $this->caller()->can($p))) {
            $this->allow($permissions[0]);
        }
    }

    /** @return array<string, mixed> */
    private function employeeCard(HrEmployee $e): array
    {
        return [
            'id' => $e->id, 'card_no' => $e->card_no, 'employee_code' => $e->employee_code, 'name' => $e->name, 'mobile' => $e->mobile,
            'department' => $e->department_id ? DB::table('departments')->where('id', $e->department_id)->value('name') : null,
            'designation' => $e->designation_id ? DB::table('designations')->where('id', $e->designation_id)->value('name') : null,
        ];
    }

    /**
     * @param  Collection<int, GatePass>  $passes
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $passes, bool $withIn = false): array
    {
        return GatePassBook::rows($passes, $this->caller()->auditUserId(), $withIn);
    }

    /** @return array<string, mixed> */
    private function payload(GatePass $p): array
    {
        $fig = GatePassBook::figures(collect([$p]))[$p->id];
        $lines = GatePassLine::query()->where('gate_pass_id', $p->id)->orderBy('sort_order')->get();
        $returned = GatePassBook::returned(collect([$p->id]));
        $items = GatePassItem::query()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');
        $location = $p->location_id ? GatePassLocation::query()->find($p->location_id) : null;
        $ins = GatePassIn::query()->where('gate_pass_id', $p->id)->orderBy('in_at')->get();
        $inLines = GatePassInLine::query()->whereIn('gate_pass_in_id', $ins->pluck('id'))->get()->groupBy('gate_pass_in_id');
        $events = GatePassEvent::query()->where('gate_pass_id', $p->id)->orderBy('created_at')->get();
        $users = DB::table('users')->whereIn('id', collect([$p->prepared_by, $p->approved_by, $p->out_by, $p->closed_by])->merge($events->pluck('user_id'))->merge($ins->pluck('received_by'))->filter()->unique())->pluck('name', 'id');
        $employee = $p->employee_id ? HrEmployee::query()->find($p->employee_id) : null;
        $order = $p->order_id ? Order::query()->find($p->order_id) : null;
        $byLine = $lines->keyBy('id');

        return [
            'gp_no' => $p->gp_no, 'date' => $p->gp_date->toDateString(), 'status' => $p->status, 'return' => $fig['return'],
            'category_id' => $p->category_id, 'category' => GatePassCategory::query()->whereKey($p->category_id)->value('name'), 'kind' => $p->kind, 'pass_type' => $p->pass_type,
            'buyer_id' => $p->buyer_id, 'buyer' => $p->buyer_id ? Buyer::query()->whereKey($p->buyer_id)->value('name') : null,
            'order_id' => $p->order_id, 'order_no' => $order?->order_no, 'style' => $p->style, 'po_no' => $p->po_no,
            'purpose_id' => $p->purpose_id, 'purpose' => $p->purpose_id ? GatePassPurpose::query()->whereKey($p->purpose_id)->value('name') : null,
            'location_id' => $p->location_id, 'destination' => $location->name ?? $p->destination_text, 'destination_text' => $p->destination_text,
            'location_type' => $location ? GatePassLocationType::query()->whereKey($location->location_type_id)->value('name') : null,
            'address' => $location?->address, 'contact_person' => $location?->contact_person, 'contact_no' => $location?->mobile,
            'expected_return' => $p->expected_return?->toDateString(), 'carrier_type' => $p->carrier_type,
            'employee' => $employee ? $this->employeeCard($employee) : null,
            'carrier_name' => $employee->name ?? $p->carrier_name, 'carrier_company' => $p->carrier_company, 'carrier_mobile' => $employee ? $employee->mobile : $p->carrier_mobile,
            'carrier_nid' => $p->carrier_nid, 'vehicle_no' => $p->vehicle_no, 'carrier_remarks' => $p->carrier_remarks, 'remarks' => $p->remarks, 'documents' => $p->documents,
            'prepared_by' => $users[$p->prepared_by] ?? null, 'approved_by' => $users[$p->approved_by] ?? null, 'approved_at' => $p->approved_at?->toIso8601String(),
            'out_at' => $p->out_at?->toIso8601String(), 'out_by' => $users[$p->out_by] ?? null, 'out_remarks' => $p->out_remarks,
            'closed_at' => $p->closed_at?->toIso8601String(), 'closed_by' => $users[$p->closed_by] ?? null,
            'mine' => $p->prepared_by === $this->caller()->auditUserId(),
            'totals' => ['out' => $fig['out'], 'returned' => $fig['returned'], 'short' => $fig['short'], 'balance' => $fig['balance'], 'unit' => $fig['unit']],
            'short_reason' => $lines->pluck('short_reason')->filter()->first(),
            'lines' => $lines->map(fn (GatePassLine $l) => [
                'id' => $l->id, 'sl' => $l->sort_order, 'item_id' => $l->item_id, 'item_code' => $items[$l->item_id]->code ?? null, 'item' => $items[$l->item_id]->name ?? null,
                'unit' => $l->unit, 'out' => (float) $l->quantity, 'returned' => $returned[$l->id] ?? 0.0, 'short' => (float) $l->short_qty, 'short_reason' => $l->short_reason,
                'balance' => $p->returnable() ? round(max(0, (float) $l->quantity - ($returned[$l->id] ?? 0) - (float) $l->short_qty), 3) : 0.0, 'remarks' => $l->remarks,
            ])->values()->all(),
            'ins' => $ins->map(fn (GatePassIn $in) => [
                'gpi_no' => $in->gpi_no, 'in_at' => $in->in_at->toIso8601String(), 'final' => $in->final, 'short_reason' => $in->short_reason, 'remarks' => $in->remarks,
                'received_by' => $users[$in->received_by] ?? null,
                'items' => ($inLines[$in->id] ?? collect())->map(fn (GatePassInLine $x) => [
                    'item' => $items[$byLine[$x->gate_pass_line_id]->item_id ?? '']->name ?? null, 'quantity' => (float) $x->quantity, 'unit' => $byLine[$x->gate_pass_line_id]->unit ?? null,
                ])->values()->all(),
            ])->values()->all(),
            'events' => $events->map(fn (GatePassEvent $e) => [
                'action' => $e->action, 'to_status' => $e->to_status, 'note' => $e->note, 'by' => $users[$e->user_id] ?? null, 'at' => $e->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
