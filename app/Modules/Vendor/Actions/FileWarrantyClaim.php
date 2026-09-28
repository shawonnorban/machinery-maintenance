<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Actions;

use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Vendor\Models\Warranty;
use App\Modules\Vendor\Models\WarrantyClaim;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Files a claim against a warranty (SRS 26).
 *
 * The failure date decides whether cover applied, not today's date. A machine
 * that broke inside its warranty and was claimed for two weeks later is a valid
 * claim, and refusing it because the warranty has since lapsed would reject
 * exactly the claims worth making.
 */
class FileWarrantyClaim
{
    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Warranty $warranty, array $data, ?string $userId = null): WarrantyClaim
    {
        $claimDate = CarbonImmutable::parse($data['claim_date'] ?? CarbonImmutable::now()->toDateString());

        // The incident, where one is known. A claim raised from a breakdown
        // carries that breakdown's failure date, which is the date the vendor
        // will ask about.
        $incidentDate = isset($data['incident_date'])
            ? CarbonImmutable::parse($data['incident_date'])
            : $claimDate;

        if (! $warranty->isActiveOn($incidentDate)) {
            throw ValidationException::withMessages([
                'warranty_id' => __('vendor.not_covered_on', [
                    'date' => $incidentDate->format('Y-m-d'),
                ]),
            ]);
        }

        if (blank($data['description'] ?? null)) {
            throw ValidationException::withMessages([
                'description' => __('vendor.claim_description_required'),
            ]);
        }

        return DB::transaction(fn (): WarrantyClaim => WarrantyClaim::create([
            'warranty_id' => $warranty->id,
            'asset_id' => $warranty->asset_id,
            'breakdown_id' => $data['breakdown_id'] ?? null,
            'work_order_id' => $data['work_order_id'] ?? null,
            'claim_number' => $this->numbers->next('WARRANTY_CLAIM'),
            'claim_date' => $claimDate->toDateString(),
            'description' => $data['description'],
            'status' => 'SUBMITTED',
            'claimed_amount' => $data['claimed_amount'] ?? null,
            'currency' => $data['currency'] ?? 'BDT',
            'created_by' => $userId,
        ]));
    }
}
