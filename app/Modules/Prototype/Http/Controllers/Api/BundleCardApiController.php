<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Prototype\Models\Bundle;
use App\Modules\Prototype\Models\BundleCard;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Bundle Card Issue (AnnotechERP 07/03): a sample lay
 * cut on a cutting table — a Company Management line of the Cutting type —
 * and its bundles (SBC-2026100001). The styling and cutting header and the
 * bundling grid save together, at least one bundle; the cutting number is
 * unique per table for the buyer, and the buyer must be the sample's. Each
 * bundle's scan code is minted from the card number and its position
 * (SBC-2026100001-01). Issue, Receive, Start / Resume, Hold, Return and
 * Cancel move the bundles; issuing finalizes the card. A card whose bundles
 * have moved is no longer re-bundled.
 */
class BundleCardApiController extends ApiController
{
    /** What each button moves the bundles from, and to. */
    private const MOVES = [
        'issue' => [['CREATED'], 'ISSUED'],
        'receive' => [['ISSUED'], 'RECEIVED'],
        'start' => [['RECEIVED', 'HOLD'], 'IN_PRODUCTION'],
        'hold' => [['ISSUED', 'RECEIVED', 'IN_PRODUCTION'], 'HOLD'],
        'return' => [['ISSUED', 'RECEIVED', 'IN_PRODUCTION', 'HOLD'], 'RETURNED'],
        'cancel' => [['CREATED', 'ISSUED', 'RECEIVED', 'IN_PRODUCTION', 'HOLD'], 'CANCELLED'],
    ];

    public function options(): JsonResponse
    {
        $this->allow('prototype.bundle.view');
        $base = Prototype::requisitionOptions();
        $samples = Sample::query()->where('state', '!=', 'CANCELLED')->orderByDesc('sample_no')->get();
        $reqs = SampleRequisition::query()->whereIn('id', $samples->pluck('requisition_id'))->get(['id', 'buyer_id', 'style_id', 'order_id', 'buyer_office_id'])->keyBy('id');

        return ApiResponse::ok([
            // A sample carries its buyer, style and order, which the header defaults from.
            'samples' => collect(SampleCards::options($samples))->map(fn (array $s): array => $s + self::reqIds($samples, $reqs, $s['sample_id']))->all(),
            'buyers' => $base['buyers'], 'styles' => $base['styles'], 'orders' => $base['orders'],
            'sample_types' => collect($base['sample_types'])->map(fn ($t) => ['value' => $t['value'], 'label' => $t['label']])->all(),
            'tables' => ProductionLine::query()->where('line_type', 'CUTTING')->where('status', 'ACTIVE')->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($l): array => ['value' => $l->id, 'label' => "{$l->code} — {$l->name}"])->all(),
            'fabric_types' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($f): array => ['value' => $f->id, 'label' => "{$f->name} ({$f->code})"])->all(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d): array => ['value' => $d->id, 'label' => $d->name])->all(),
            'users' => Prototype::users(), 'statuses' => BundleCard::STATUSES, 'bundle_statuses' => BundleCard::BUNDLE_STATUSES, 'priorities' => BundleCard::PRIORITIES,
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.bundle.view');

        return ApiResponse::ok($this->present(BundleCard::query()->orderByDesc('cutting_date')->orderByDesc('card_no')->get()));
    }

    public function show(string $card): JsonResponse
    {
        $this->allow('prototype.bundle.view');

        return ApiResponse::ok($this->present(collect([$this->find($card)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.bundle.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): BundleCard {
            $row = BundleCard::query()->create([
                ...collect($data)->except('bundles')->all(), 'status' => $data['status'] ?? 'DRAFT', 'bundle_status' => 'CREATED',
                'card_no' => $this->nextCardNo($data['cutting_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeBundles($row, $data['bundles']);
            SampleLedger::log($row->sample_id, 'bundle_card_created', ['card' => $row->card_no, 'bundles' => count($data['bundles'])], $row->card_no);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function update(Request $request, string $card): JsonResponse
    {
        $this->allow('prototype.bundle.manage');
        $row = $this->find($card);
        if (in_array($row->bundle_status, BundleCard::CLOSED, true)) {
            throw ValidationException::withMessages(['bundle_status' => __('prototype.bundle.locked', ['status' => __('prototype.bundle_status.'.$row->bundle_status)])]);
        }
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update(collect($data)->except('bundles')->all() + ['status' => $data['status'] ?? $row->status]);
            // Re-bundled only until a bundle moves.
            if (! $this->moved($row)) {
                $this->writeBundles($row, $data['bundles']);
            }
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** Issue, Receive, Start / Resume, Hold, Return, Cancel. */
    public function move(string $card, string $action): JsonResponse
    {
        $this->allow('prototype.bundle.manage');
        abort_unless(isset(self::MOVES[$action]), 404);
        [$from, $to] = self::MOVES[$action];
        $row = $this->find($card);
        if (! in_array($row->bundle_status, $from, true)) {
            throw ValidationException::withMessages(['bundle_status' => __('prototype.bundle.wrong_status', ['action' => __('prototype.bundle_action.'.$action), 'status' => __('prototype.bundle_status.'.$row->bundle_status)])]);
        }
        $stamp = ['bundle_status' => $to];
        if ($action === 'issue') {
            // Issuing finalizes the card; a blank issue date is today.
            $stamp += ['is_finalized' => true, 'issue_date' => $row->issue_date ?? CarbonImmutable::today()];
        }
        if ($action === 'cancel') {
            $stamp += ['status' => 'CANCELLED'];
        }
        $row->update($stamp);
        SampleLedger::log($row->sample_id, 'bundle_card_'.$action, ['card' => $row->card_no], $row->card_no);
        if ($action === 'issue') {
            SampleLedger::raise('PRODUCTION_READY', 'INFO', $row->sample_id, 'PRODUCTION', $row->card_no, ['card' => $row->card_no]);
        } elseif (in_array($action, ['return', 'cancel'], true)) {
            SampleLedger::resolve('PRODUCTION_READY', $row->card_no);
        }

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** A bundle has moved once it has been received on the floor, or scanned anywhere. */
    private function moved(BundleCard $row): bool
    {
        return ! in_array($row->bundle_status, ['CREATED', 'ISSUED'], true) || Bundle::query()->where('card_id', $row->id)->whereNotNull('now_at')->exists();
    }

    /** SBC + year + month + a running number within the month: SBC-2026100001. */
    private function nextCardNo(string $date): string
    {
        $company = app(TenantContext::class)->companyId();
        $head = 'SBC-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["proto_bundle_cards:{$head}:{$company}"]);
        $last = BundleCard::query()->where('card_no', 'like', $head.'%')->pluck('card_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) (1 + (int) $last), 4, '0', STR_PAD_LEFT);
    }

    /** The grid as sent, each bundle's scan code the card number and its position. */
    private function writeBundles(BundleCard $row, array $bundles): void
    {
        Bundle::query()->where('card_id', $row->id)->delete();
        foreach (array_values($bundles) as $i => $b) {
            Bundle::query()->create([...$b, 'card_id' => $row->id, 'bundle_no' => $row->card_no.'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'req_qty' => (int) ($b['req_qty'] ?? 0), 'sort_order' => $i + 1]);
        }
    }

    private function find(string $card): BundleCard
    {
        return BundleCard::query()->where('card_no', $card)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?BundleCard $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'sample_id' => ['nullable', $in('proto_samples')],
            'buyer_id' => ['required', $in('buyers')],
            'buyer_office_id' => ['nullable', $in('buyer_offices')],
            'style_id' => ['nullable', $in('merch_styles')],
            'order_id' => ['nullable', $in('merch_orders')],
            'cutting_no' => ['required', 'string', 'max:32'],
            'table_id' => ['required', Rule::exists('production_lines', 'id')->where('company_id', $company)->where('line_type', 'CUTTING')],
            'fabric_type_id' => ['nullable', $in('fabric_types')],
            'cutting_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(BundleCard::STATUSES)],
            'is_finalized' => ['sometimes', 'boolean'],
            'department_id' => ['nullable', $in('departments')],
            'assigned_team' => ['nullable', 'string', 'max:120'],
            'operator_id' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(BundleCard::PRIORITIES)],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'bundles' => ['nullable', 'array'],
            'bundles.*.order_id' => ['nullable', $in('merch_orders')],
            'bundles.*.sample_type_id' => ['nullable', $in('sample_types')],
            'bundles.*.color' => ['nullable', 'string', 'max:96'],
            'bundles.*.size' => ['nullable', 'string', 'max:32'],
            'bundles.*.req_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'bundles.*.cutting_qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'bundles.*.remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'buyer_id.required' => __('prototype.bundle.buyer_required'), 'cutting_no.required' => __('prototype.bundle.cutting_required'),
            'table_id.required' => __('prototype.bundle.table_required'), 'table_id.exists' => __('prototype.bundle.table_required'), 'cutting_date.required' => __('prototype.bundle.date_required'),
            'bundles.*.cutting_qty.required' => __('prototype.bundle.cutting_qty_required'), 'bundles.*.cutting_qty.min' => __('prototype.bundle.cutting_qty_required'),
        ]);
        $data['bundles'] = $data['bundles'] ?? [];
        ksort($data['bundles']);
        $data['cutting_no'] = trim($data['cutting_no']);
        if (array_key_exists('is_finalized', $data)) {
            $data['is_finalized'] = (bool) $data['is_finalized'];
        }
        // The buyer must be the sample's; the style and order are the buyer's.
        if (! empty($data['sample_id'])) {
            $buyer = SampleRequisition::query()->whereKey(Sample::query()->whereKey($data['sample_id'])->value('requisition_id'))->value('buyer_id');
            if ($buyer !== $data['buyer_id']) {
                throw ValidationException::withMessages(['buyer_id' => __('prototype.bundle.buyer_not_sample')]);
            }
        }
        if (! empty($data['style_id']) && Style::query()->whereKey($data['style_id'])->value('buyer_id') !== $data['buyer_id']) {
            throw ValidationException::withMessages(['style_id' => __('prototype.bundle.style_other_buyer')]);
        }
        if (! empty($data['order_id']) && Order::query()->whereKey($data['order_id'])->value('buyer_id') !== $data['buyer_id']) {
            throw ValidationException::withMessages(['order_id' => __('prototype.bundle.order_other_buyer')]);
        }
        $taken = BundleCard::query()->where('table_id', $data['table_id'])->where('buyer_id', $data['buyer_id'])->where('cutting_no', $data['cutting_no'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['cutting_no' => __('prototype.bundle.cutting_taken', ['no' => $data['cutting_no']])]);
        }
        if ((! $row || ! $this->moved($row)) && $data['bundles'] === []) {
            throw ValidationException::withMessages(['bundles' => __('prototype.bundle.bundles_required')]);
        }
        if (! empty($data['due_date']) && CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($data['cutting_date']))) {
            throw ValidationException::withMessages(['due_date' => __('prototype.bundle.due_before_cut')]);
        }

        return $data;
    }

    /** @return array{buyer_id: ?string, style_id: ?string, order_id: ?string, buyer_office_id: ?string} */
    private static function reqIds(Collection $samples, Collection $reqs, string $sampleId): array
    {
        $req = $reqs[$samples->firstWhere('id', $sampleId)?->requisition_id ?? ''] ?? null;

        return ['buyer_id' => $req?->buyer_id, 'style_id' => $req?->style_id, 'order_id' => $req?->order_id, 'buyer_office_id' => $req?->buyer_office_id];
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows, bool $detail = false): array
    {
        $bundles = Bundle::query()->whereIn('card_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('card_id');
        $samples = Sample::query()->whereIn('id', $rows->pluck('sample_id')->filter())->pluck('sample_no', 'id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id'))->pluck('short_name', 'id');
        $offices = BuyerOffice::query()->whereIn('id', $rows->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $styles = Style::query()->whereIn('id', $rows->pluck('style_id')->filter())->get(['id', 'style_ref', 'style_ext', 'description'])->keyBy('id');
        $orders = Order::query()->whereIn('id', [...$rows->pluck('order_id')->filter(), ...$bundles->flatten()->pluck('order_id')->filter()])->pluck('order_no', 'id');
        $tables = ProductionLine::query()->whereIn('id', $rows->pluck('table_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $fabrics = FabricType::query()->whereIn('id', $rows->pluck('fabric_type_id')->filter())->pluck('name', 'id');
        $types = SampleType::query()->whereIn('id', $bundles->flatten()->pluck('sample_type_id')->filter())->pluck('name', 'id');
        $departments = Department::query()->whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');
        $names = Prototype::names($rows->pluck('operator_id')->all());
        $today = CarbonImmutable::today();

        return $rows->map(function (BundleCard $r) use ($bundles, $samples, $buyers, $offices, $styles, $orders, $tables, $fabrics, $types, $departments, $names, $today, $detail): array {
            $list = $bundles[$r->id] ?? collect();
            $style = $r->style_id ? ($styles[$r->style_id] ?? null) : null;
            $table = $tables[$r->table_id] ?? null;
            $out = [
                'id' => $r->id, 'card_no' => $r->card_no, 'sample_id' => $r->sample_id, 'sample_no' => $r->sample_id ? ($samples[$r->sample_id] ?? null) : null,
                'buyer_id' => $r->buyer_id, 'buyer' => $buyers[$r->buyer_id] ?? null, 'buyer_office_id' => $r->buyer_office_id, 'buyer_office' => $r->buyer_office_id ? ($offices[$r->buyer_office_id] ?? null) : null,
                'style_id' => $r->style_id, 'style' => $style ? trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')) : null, 'style_name' => $style?->description,
                'order_id' => $r->order_id, 'order_no' => $r->order_id ? ($orders[$r->order_id] ?? null) : null, 'cutting_no' => $r->cutting_no,
                'table_id' => $r->table_id, 'table' => $table ? "{$table->code} — {$table->name}" : null, 'table_code' => $table?->code,
                'fabric_type_id' => $r->fabric_type_id, 'fabric' => $r->fabric_type_id ? ($fabrics[$r->fabric_type_id] ?? null) : null,
                'cutting_date' => $r->cutting_date->toDateString(), 'status' => $r->status, 'is_finalized' => $r->is_finalized, 'bundle_status' => $r->bundle_status,
                'department_id' => $r->department_id, 'department' => $r->department_id ? ($departments[$r->department_id] ?? null) : null, 'assigned_team' => $r->assigned_team,
                'operator_id' => $r->operator_id, 'operator_name' => $names[$r->operator_id] ?? null, 'issue_date' => $r->issue_date?->toDateString(), 'due_date' => $r->due_date?->toDateString(),
                'priority' => $r->priority, 'remarks' => $r->remarks,
                'bundles_count' => $list->count(), 'req_qty' => (int) $list->sum('req_qty'), 'cutting_qty' => (int) $list->sum('cutting_qty'),
                'overdue' => $r->due_date !== null && $r->due_date->lt($today) && ! in_array($r->bundle_status, BundleCard::CLOSED, true),
                'locked' => in_array($r->bundle_status, BundleCard::CLOSED, true), 'rebundle' => ! $this->moved($r),
            ];
            if ($detail) {
                $out['bundles'] = $list->map(fn (Bundle $b): array => [
                    ...$b->only(['bundle_no', 'order_id', 'sample_type_id', 'color', 'size', 'req_qty', 'cutting_qty', 'now_at', 'remarks']),
                    'order_no' => $b->order_id ? ($orders[$b->order_id] ?? null) : null, 'sample_type' => $b->sample_type_id ? ($types[$b->sample_type_id] ?? null) : null,
                ])->values()->all();
            }

            return $out;
        })->values()->all();
    }
}
