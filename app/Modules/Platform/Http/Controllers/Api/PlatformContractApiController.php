<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A customer's contract, from the platform side (Platform API §3; mirrors
 * `TenantController::storeContract`/`updateLimits`, which this delegates to
 * unchanged per ADR-003).
 *
 * No mandatory fixed packages (SRS 40): every term is set per customer, so
 * this takes a full set of terms rather than a plan key.
 */
class PlatformContractApiController extends ApiController
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Every contract this customer has ever had, current first — the
     * history `TenantController::show`'s billing tab reads alongside the
     * invoice list, so a superseded contract's terms stay visible next to
     * the invoices raised under it.
     */
    public function index(Company $company): JsonResponse
    {
        $contracts = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::ok($contracts->map(fn (SubscriptionContract $c): array => $this->summary($c))->all());
    }

    /**
     * A new contract, superseding whichever is currently active. Never an
     * edit: an invoice already raised under the old terms is a document
     * somebody has been sent, and editing the terms it was calculated from
     * makes it unexplainable.
     */
    public function store(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'contract_number' => ['required', 'string', 'max:32'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'billing_cycle' => ['required', 'in:'.implode(',', SubscriptionContract::BILLING_CYCLES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'trial_end' => ['nullable', 'date'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:180'],
            'auto_renew' => ['sometimes', 'boolean'],
            'included_factories' => ['nullable', 'integer', 'min:0'],
            'included_assets' => ['nullable', 'integer', 'min:0'],
            'included_users' => ['nullable', 'integer', 'min:0'],
            'overage_policy' => ['required', 'in:'.implode(',', SubscriptionContract::OVERAGE_POLICIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $contract = DB::transaction(function () use ($company, $data, $request): SubscriptionContract {
            SubscriptionContract::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->whereIn('status', ['ACTIVE', 'TRIAL', 'READ_ONLY'])
                ->update(['status' => 'ARCHIVED', 'archived_at' => now()]);

            return SubscriptionContract::withoutGlobalScope(TenantScope::class)->create($data + [
                'company_id' => $company->id,
                'status' => ($data['trial_end'] ?? null) !== null ? 'TRIAL' : 'ACTIVE',
                'auto_renew' => $request->boolean('auto_renew'),
            ]);
        });

        return ApiResponse::created($this->summary($contract));
    }

    /**
     * The numbers somebody actually adjusts day to day — a customer buys
     * twenty more machines in March — without superseding the whole
     * contract for a change of one field.
     */
    public function updateEntitlements(Request $request, Company $company): JsonResponse
    {
        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('start_date')
            ->first();

        if ($contract === null) {
            throw ApiException::of(ErrorCode::CONFLICT, __('platform.limits_need_contract'));
        }

        $data = $request->validate([
            'included_factories' => ['nullable', 'integer', 'min:0'],
            'included_assets' => ['nullable', 'integer', 'min:0'],
            'included_users' => ['nullable', 'integer', 'min:0'],
            'overage_policy' => ['required', 'in:'.implode(',', SubscriptionContract::OVERAGE_POLICIES)],
        ]);

        $before = $contract->only(['included_factories', 'included_assets', 'included_users', 'overage_policy']);

        $contract->forceFill($data)->save();

        $this->audit->event(
            'SUBSCRIPTION_CHANGED',
            [
                'reason' => 'LIMITS_CHANGED', 'company_id' => $company->id,
                'contract_number' => $contract->contract_number, 'from' => $before, 'to' => $data,
            ],
            userId: $request->user()->id,
            label: $contract->contract_number,
        );

        return ApiResponse::ok($this->summary($contract->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SubscriptionContract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'status' => $contract->status,
            'start_date' => $contract->start_date?->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'billing_cycle' => $contract->billing_cycle,
            'amount' => $contract->amount,
            'currency' => $contract->currency,
            'trial_end' => $contract->trial_end?->toDateString(),
            'grace_period_days' => $contract->grace_period_days,
            'auto_renew' => $contract->auto_renew,
            'included_factories' => $contract->included_factories,
            'included_assets' => $contract->included_assets,
            'included_users' => $contract->included_users,
            'overage_policy' => $contract->overage_policy,
            'notes' => $contract->notes,
        ];
    }
}
