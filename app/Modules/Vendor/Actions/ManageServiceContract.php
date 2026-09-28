<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and renews AMC and service contracts (SRS 26).
 *
 * A renewal is a new contract pointing at the one it replaced, never an edit.
 * Changing the dates on last year's contract erases what was agreed last year,
 * and the entire value of an AMC history is showing what changed between
 * renewals — the price, the visit count, the response time.
 */
class ManageServiceContract
{
    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId = null): ServiceContract
    {
        $vendor = Vendor::find($data['vendor_id'] ?? null);

        if ($vendor === null) {
            throw ValidationException::withMessages(['vendor_id' => __('vendor.vendor_not_found')]);
        }

        if (! $vendor->services()) {
            // A parts supplier is not a service provider. Letting one be named
            // on an AMC is how a contract ends up with nobody to call.
            throw ValidationException::withMessages([
                'vendor_id' => __('vendor.not_a_service_vendor'),
            ]);
        }

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['end_date' => __('vendor.end_before_start')]);
        }

        $assetIds = $data['asset_ids'] ?? [];

        if (($data['asset_id'] ?? null) === null && ($data['factory_id'] ?? null) === null && $assetIds === []) {
            // Scope is the whole point of a contract. One with none covers
            // nothing, and would sit in the list looking like protection.
            throw ValidationException::withMessages(['scope' => __('vendor.contract_scope_required')]);
        }

        return DB::transaction(function () use ($data, $start, $end, $assetIds, $userId): ServiceContract {
            $contract = ServiceContract::create([
                'vendor_id' => $data['vendor_id'],
                'asset_id' => $data['asset_id'] ?? null,
                'factory_id' => $data['factory_id'] ?? null,
                'contract_number' => $data['contract_number'] ?? $this->numbers->next('SERVICE_CONTRACT'),
                'contract_type' => $data['contract_type'] ?? 'AMC',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'renewal_date' => $data['renewal_date'] ?? null,
                'value' => $data['value'] ?? null,
                'currency' => $data['currency'] ?? 'BDT',
                'coverage' => $data['coverage'] ?? null,
                'visits_per_year' => $data['visits_per_year'] ?? null,
                'response_time_hours' => $data['response_time_hours'] ?? null,
                'status' => $end->isPast() ? 'EXPIRED' : 'ACTIVE',
                'renewed_from_contract_id' => $data['renewed_from_contract_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            if ($assetIds !== []) {
                $attachments = [];

                foreach ($this->assetsInCompany($assetIds) as $assetId) {
                    $attachments[$assetId] = ['company_id' => $contract->company_id, 'created_at' => now()];
                }

                $contract->assets()->sync($attachments);
            }

            return $contract;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renew(ServiceContract $contract, array $data, ?string $userId = null): ServiceContract
    {
        if ($contract->status === 'CANCELLED') {
            throw ValidationException::withMessages([
                'contract' => __('vendor.cannot_renew_cancelled'),
            ])->status(409);
        }

        $renewal = $this->create([
            'vendor_id' => $data['vendor_id'] ?? $contract->vendor_id,
            'asset_id' => $contract->asset_id,
            'factory_id' => $contract->factory_id,
            'contract_type' => $contract->contract_type,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'renewal_date' => $data['renewal_date'] ?? null,
            'value' => $data['value'] ?? $contract->value,
            'currency' => $data['currency'] ?? $contract->currency,
            'coverage' => $data['coverage'] ?? $contract->coverage,
            'visits_per_year' => $data['visits_per_year'] ?? $contract->visits_per_year,
            'response_time_hours' => $data['response_time_hours'] ?? $contract->response_time_hours,
            'asset_ids' => $contract->assets()->pluck('assets.id')->all(),
            'renewed_from_contract_id' => $contract->id,
            'notes' => $data['notes'] ?? null,
        ], $userId);

        // The old one is marked RENEWED rather than EXPIRED: it ended because
        // something replaced it, and a list that cannot tell those apart cannot
        // show which contracts were dropped.
        $contract->forceFill(['status' => 'RENEWED'])->save();

        return $renewal;
    }

    public function cancel(ServiceContract $contract, string $reason, ?string $userId = null): ServiceContract
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('vendor.cancel_reason_required')]);
        }

        $contract->forceFill([
            'status' => 'CANCELLED',
            'notes' => trim(($contract->notes ? $contract->notes."\n" : '').$reason),
        ])->save();

        return $contract->fresh();
    }

    /**
     * @param  list<string>  $assetIds
     * @return list<string>
     */
    private function assetsInCompany(array $assetIds): array
    {
        // Through the tenant-scoped model, so a posted id from another company
        // silently drops out rather than being attached.
        return Asset::whereIn('id', $assetIds)->pluck('id')->all();
    }
}
