<?php

declare(strict_types=1);

namespace App\Modules\Metering\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterType;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Fitting a meter to a machine (SRS 11).
 *
 * A meter is the pairing of a machine and something counted on it: this sewing
 * machine's stitch count, that generator's running hours. Usage-based
 * maintenance has nothing to hang off until one exists, which is why a plan
 * with a METER trigger and no meter fitted can never come due.
 */
class ManageAssetMeter
{
    public function __construct(private readonly TenantContext $context) {}

    public function attach(Asset $asset, string $meterTypeId, string|float $initialValue = '0'): AssetMeter
    {
        $type = MeterType::availableTo($this->context->companyId())
            ->where('active', true)
            ->find($meterTypeId);

        if ($type === null) {
            throw ValidationException::withMessages([
                'meter_type_id' => __('metering.unknown_meter_type'),
            ]);
        }

        $existing = AssetMeter::where('asset_id', $asset->id)
            ->where('meter_type_id', $type->id)
            ->exists();

        if ($existing) {
            // Two meters of the same kind on one machine would give every
            // usage-based due date two answers.
            throw ValidationException::withMessages([
                'meter_type_id' => __('metering.already_fitted', ['type' => $type->name]),
            ])->status(422);
        }

        return AssetMeter::create([
            'company_id' => $this->context->companyId(),
            'asset_id' => $asset->id,
            'meter_type_id' => $type->id,
            'current_value' => number_format((float) $initialValue, 4, '.', ''),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Take a meter out of use.
     *
     * Never deleted: every reading and every due date computed from it names
     * this row, and a maintenance history that cannot say what the hours were
     * is a history nobody can audit.
     */
    public function setStatus(AssetMeter $meter, string $status): AssetMeter
    {
        $meter->forceFill(['status' => $status])->save();

        return $meter->fresh();
    }
}
