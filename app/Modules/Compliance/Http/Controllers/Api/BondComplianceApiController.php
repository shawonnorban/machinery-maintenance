<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Http\Controllers\Api;

use App\Modules\Compliance\Actions\AssessBondedDuty;
use App\Modules\Compliance\Actions\ManageBondCompliance;
use App\Modules\Compliance\Models\BondEntitlement;
use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Models\DutyAssessment;
use App\Modules\Compliance\Models\UtilisationDeclaration;
use App\Modules\Compliance\Models\UtilisationDeclarationLine;
use App\Modules\Compliance\Services\BondUtilisation;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bonded warehouse compliance over the API (Shared Systems spec §3).
 *
 * One controller for licences, declarations and duty because they are one
 * chain: the licence permits the import, the declaration says what it is
 * for, and an assessment is what happens when the goods go somewhere
 * else. Splitting them would mean three screens that each tell a third of
 * the story.
 */
class BondComplianceApiController extends ApiController
{
    public const VIEW = 'compliance.bond.view_any';

    public const MANAGE = 'compliance.bond.manage';

    public const ASSESS = 'compliance.duty.assess';

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageBondCompliance $bonds,
        private readonly BondUtilisation $utilisation,
        private readonly AssessBondedDuty $duty,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = BondLicence::query()
            ->with('factory:id,name')
            ->withCount(['entitlements', 'declarations'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(licence_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(issuing_office) LIKE ?', [$needle]));
        }

        $query = $this->applyFilters($query, $request, ['status', 'factory_id']);
        $query = $this->applySort($query, $request, ['licence_no', 'valid_from', 'valid_to', 'status'], 'valid_to');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (BondLicence $licence): array => $this->licenceSummary($licence),
        );
    }

    public function formOptions(): JsonResponse
    {
        // Whoever raises a duty assessment needs these same lists and is
        // not always the person who keeps the licences.
        if (! $this->caller()->can(self::ASSESS)) {
            $this->allow(self::MANAGE);
        }

        $factoryIds = $this->context->accessibleFactoryIds();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            // Only bonded stores: duty is a bond concept, and offering an
            // ordinary store here would invite a charge nobody owes.
            'bonded_stores' => Store::query()->where('active', true)->where('store_type', 'BONDED')
                ->with('warehouse:id,factory_id')->orderBy('name')->get()
                ->filter(fn (Store $store): bool => in_array($store->warehouse?->factory_id, $factoryIds, true))
                ->map(fn (Store $store): array => [
                    'id' => $store->id, 'name' => $store->name, 'code' => $store->code,
                    'factory_id' => $store->warehouse?->factory_id,
                ])->values()->all(),
            'bases' => BondEntitlement::BASES,
            'ud_types' => UtilisationDeclaration::TYPES,
            'duty_reasons' => DutyAssessment::REASONS,
        ]);
    }

    public function show(BondLicence $licence): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($licence);

        return ApiResponse::ok($this->licenceDetail($licence));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $this->licenceRules($request, creating: true);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->licenceDetail($this->bonds->createLicence(
            Factory::findOrFail($data['factory_id']),
            $data,
            $data['entitlements'] ?? [],
            $this->caller()->auditUserId(),
        )));
    }

    public function update(Request $request, BondLicence $licence): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($licence);

        $data = $this->licenceRules($request, creating: false);

        return ApiResponse::ok($this->licenceDetail($this->bonds->updateLicence(
            $licence, $data, $data['entitlements'] ?? null,
        )));
    }

    public function setStatus(Request $request, BondLicence $licence): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($licence);

        $data = $request->validate([
            'status' => ['required', Rule::in(BondLicence::STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->licenceDetail(
            $this->bonds->setLicenceStatus($licence, $data['status'], $data['reason'] ?? null),
        ));
    }

    /** Entitlement against what has actually been imported (§3.1). */
    public function utilisation(BondLicence $licence): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($licence);

        return ApiResponse::ok([
            'licence' => $this->licenceSummary($licence),
            'rows' => $this->utilisation->forLicence($licence),
        ]);
    }

    public function storeDeclaration(Request $request, BondLicence $licence): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($licence);

        $data = $request->validate([
            'ud_no' => ['required', 'string', 'max:64'],
            'ud_type' => ['nullable', Rule::in(UtilisationDeclaration::TYPES)],
            'issue_date' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'purchase_order_id' => ['nullable', 'string', 'size:26'],
            'production_order_id' => ['nullable', 'string', 'size:26'],
            'buyer_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.declared_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.wastage_percent' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::created($this->declaration($this->bonds->createDeclaration(
            $licence, $data, $data['lines'], $this->caller()->auditUserId(),
        )));
    }

    public function showDeclaration(UtilisationDeclaration $declaration): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachableDeclaration($declaration);

        return ApiResponse::ok($this->declaration($declaration, withReconciliation: true));
    }

    public function issueDeclaration(UtilisationDeclaration $declaration): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableDeclaration($declaration);

        return ApiResponse::ok($this->declaration($this->bonds->issueDeclaration($declaration)));
    }

    public function closeDeclaration(UtilisationDeclaration $declaration): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableDeclaration($declaration);

        return ApiResponse::ok($this->declaration($this->bonds->closeDeclaration($declaration), withReconciliation: true));
    }

    public function cancelDeclaration(Request $request, UtilisationDeclaration $declaration): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachableDeclaration($declaration);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->declaration(
            $this->bonds->cancelDeclaration($declaration, $data['reason'] ?? null),
        ));
    }

    public function assessments(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = DutyAssessment::query()
            ->with(['licence:id,licence_no', 'store:id,name,code', 'factory:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(assessment_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_code) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_name) LIKE ?', [$needle]));
        }

        $query = $this->applyFilters($query, $request, ['reason', 'factory_id', 'bond_licence_id']);
        $query = $this->applySort($query, $request, ['assessment_no', 'assessment_date', 'total_payable'], 'assessment_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (DutyAssessment $assessment): array => $this->assessment($assessment),
        );
    }

    public function assess(Request $request): JsonResponse
    {
        $this->allow(self::ASSESS);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'item_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', Rule::in(DutyAssessment::REASONS)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'store_id' => ['nullable', 'string', 'size:26'],
            'unit_value' => ['nullable', 'numeric', 'gte:0'],
            'bond_licence_id' => ['nullable', 'string', 'size:26'],
            'assessment_date' => ['nullable', 'date'],
            'reference_type' => ['nullable', 'string', 'max:48'],
            'reference_id' => ['nullable', 'string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->assessment($this->duty->handle(
            Factory::findOrFail($data['factory_id']),
            Item::findOrFail($data['item_id']),
            $data,
            $this->caller()->auditUserId(),
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function licenceRules(Request $request, bool $creating): array
    {
        return $request->validate([
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'licence_no' => [$creating ? 'required' : 'sometimes', 'string', 'max:64'],
            'issuing_office' => [$creating ? 'required' : 'nullable', 'string', 'max:160'],
            'issued_date' => ['nullable', 'date'],
            'valid_from' => [$creating ? 'required' : 'nullable', 'date'],
            'valid_to' => [$creating ? 'required' : 'nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'entitlements' => ['nullable', 'array'],
            'entitlements.*.item_id' => ['required', 'string', 'size:26'],
            'entitlements.*.uom_id' => ['nullable', 'string', 'size:26'],
            'entitlements.*.entitled_quantity' => ['required', 'numeric', 'gt:0'],
            'entitlements.*.basis' => ['nullable', Rule::in(BondEntitlement::BASES)],
            'entitlements.*.period_start' => ['nullable', 'date'],
            'entitlements.*.period_end' => ['nullable', 'date'],
            'entitlements.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function licenceSummary(BondLicence $licence): array
    {
        return [
            'id' => $licence->id,
            'licence_no' => $licence->licence_no,
            'issuing_office' => $licence->issuing_office,
            'issued_date' => $licence->issued_date?->toDateString(),
            'valid_from' => $licence->valid_from?->toDateString(),
            'valid_to' => $licence->valid_to?->toDateString(),
            'status' => $licence->status,
            // Status alone cannot say whether it applies today.
            'is_live' => $licence->isLive(),
            'has_expired' => $licence->hasExpired(),
            'days_to_expiry' => $licence->daysToExpiry(),
            'factory' => $licence->factory === null ? null : ['id' => $licence->factory->id, 'name' => $licence->factory->name],
            'entitlement_count' => $licence->entitlements_count ?? null,
            'declaration_count' => $licence->declarations_count ?? null,
            'created_at' => $licence->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function licenceDetail(BondLicence $licence): array
    {
        $licence->loadMissing(['factory:id,name', 'creator:id,name', 'entitlements.uom:id,code', 'declarations']);

        $mayManage = $this->caller()->can(self::MANAGE);

        return $this->licenceSummary($licence) + [
            'notes' => $licence->notes,
            'created_by' => $licence->creator === null ? null : ['id' => $licence->creator->id, 'name' => $licence->creator->name],
            'can_edit' => $licence->status !== 'CANCELLED' && $mayManage,
            'can_suspend' => $licence->canTransitionTo('SUSPENDED') && $mayManage,
            'can_restore' => $licence->canTransitionTo('ACTIVE') && $mayManage,
            'can_cancel' => $licence->canTransitionTo('CANCELLED') && $mayManage,
            // Entitlement beside what has actually come in.
            'utilisation' => $this->utilisation->forLicence($licence),
            'declarations' => $licence->declarations
                ->sortByDesc('issue_date')
                ->values()
                ->map(fn (UtilisationDeclaration $row): array => [
                    'id' => $row->id,
                    'ud_no' => $row->ud_no,
                    'ud_type' => $row->ud_type,
                    'issue_date' => $row->issue_date?->toDateString(),
                    'valid_to' => $row->valid_to?->toDateString(),
                    'status' => $row->status,
                    'is_live' => $row->isLive(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function declaration(UtilisationDeclaration $declaration, bool $withReconciliation = false): array
    {
        $declaration->loadMissing([
            'licence:id,licence_no,status', 'factory:id,name', 'creator:id,name',
            'purchaseOrder:id,po_no', 'lines.uom:id,code',
        ]);

        $mayManage = $this->caller()->can(self::MANAGE);

        $payload = [
            'id' => $declaration->id,
            'ud_no' => $declaration->ud_no,
            'ud_type' => $declaration->ud_type,
            'issue_date' => $declaration->issue_date?->toDateString(),
            'valid_to' => $declaration->valid_to?->toDateString(),
            'status' => $declaration->status,
            'is_live' => $declaration->isLive(),
            'buyer_reference' => $declaration->buyer_reference,
            'notes' => $declaration->notes,
            'licence' => $declaration->licence === null ? null : [
                'id' => $declaration->licence->id,
                'licence_no' => $declaration->licence->licence_no,
                'status' => $declaration->licence->status,
            ],
            'factory' => $declaration->factory === null ? null : ['id' => $declaration->factory->id, 'name' => $declaration->factory->name],
            'purchase_order' => $declaration->purchaseOrder === null ? null : [
                'id' => $declaration->purchaseOrder->id,
                'po_no' => $declaration->purchaseOrder->po_no,
            ],
            'created_by' => $declaration->creator === null ? null : ['id' => $declaration->creator->id, 'name' => $declaration->creator->name],
            'issued_at' => $declaration->issued_at?->toIso8601String(),
            'closed_at' => $declaration->closed_at?->toIso8601String(),
            'cancelled_at' => $declaration->cancelled_at?->toIso8601String(),
            'can_issue' => $declaration->canTransitionTo('ISSUED') && $mayManage,
            'can_close' => $declaration->canTransitionTo('CLOSED') && $mayManage,
            'can_cancel' => $declaration->canTransitionTo('CANCELLED') && $mayManage,
            'lines' => $declaration->lines->map(fn (UtilisationDeclarationLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom?->code,
                'declared_quantity' => (string) $line->declared_quantity,
                'wastage_percent' => $line->wastage_percent,
                'allowed_quantity' => $line->allowedQuantity(),
                'notes' => $line->notes,
            ])->all(),
            'created_at' => $declaration->created_at?->toIso8601String(),
        ];

        if ($withReconciliation) {
            $payload['reconciliation'] = $this->utilisation->reconcile($declaration);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function assessment(DutyAssessment $assessment): array
    {
        $assessment->loadMissing(['licence:id,licence_no', 'store:id,name,code', 'factory:id,name']);

        return [
            'id' => $assessment->id,
            'assessment_no' => $assessment->assessment_no,
            'assessment_date' => $assessment->assessment_date?->toDateString(),
            'item_id' => $assessment->item_id,
            'item_code' => $assessment->item_code,
            'item_name' => $assessment->item_name,
            'reason' => $assessment->reason,
            'quantity' => (string) $assessment->quantity,
            'unit_value' => (string) $assessment->unit_value,
            'assessable_value' => (string) $assessment->assessable_value,
            'hs_code' => $assessment->hs_code,
            'duty_rate' => $assessment->duty_rate,
            'vat_rate' => $assessment->vat_rate,
            'duty_amount' => (string) $assessment->duty_amount,
            'vat_amount' => (string) $assessment->vat_amount,
            'total_payable' => (string) $assessment->total_payable,
            'currency' => $assessment->currency,
            // False when the item carries no tariff heading: the row was
            // still written, at zero, as a question for customs.
            'is_rated' => $assessment->isRated(),
            'store' => $assessment->store === null ? null : ['id' => $assessment->store->id, 'name' => $assessment->store->name],
            'licence' => $assessment->licence === null ? null : ['id' => $assessment->licence->id, 'licence_no' => $assessment->licence->licence_no],
            'factory' => $assessment->factory === null ? null : ['id' => $assessment->factory->id, 'name' => $assessment->factory->name],
            'notes' => $assessment->notes,
            'created_at' => $assessment->created_at?->toIso8601String(),
        ];
    }

    private function assertReachable(BondLicence $licence): void
    {
        if (! $this->context->canAccessFactory((string) $licence->factory_id)) {
            abort(404);
        }
    }

    private function assertReachableDeclaration(UtilisationDeclaration $declaration): void
    {
        if (! $this->context->canAccessFactory((string) $declaration->factory_id)) {
            abort(404);
        }
    }
}
