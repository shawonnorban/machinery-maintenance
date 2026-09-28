<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Actions;

use App\Modules\Vendor\Models\WarrantyClaim;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a claim along (SRS 26).
 *
 * A small state machine rather than a free status field, because the states are
 * the point: a vendor agreeing to a claim and a vendor paying it are months
 * apart, and a factory chasing money needs to see which claims are stuck
 * between the two.
 */
class DecideWarrantyClaim
{
    /** What may follow what. Nothing leaves a settled or rejected claim. */
    private const TRANSITIONS = [
        'SUBMITTED' => ['ACKNOWLEDGED', 'APPROVED', 'REJECTED'],
        'ACKNOWLEDGED' => ['APPROVED', 'REJECTED'],
        'APPROVED' => ['SETTLED'],
        'REJECTED' => [],
        'SETTLED' => [],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WarrantyClaim $claim, string $toStatus, array $data = [], ?string $userId = null): WarrantyClaim
    {
        $allowed = self::TRANSITIONS[$claim->status] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('vendor.claim_transition_invalid', [
                    'from' => $claim->status,
                    'to' => $toStatus,
                ]),
            ])->status(409);
        }

        if ($toStatus === 'REJECTED' && blank($data['resolution'] ?? null)) {
            // A rejection without a reason is an argument nobody can have with
            // the vendor six months later.
            throw ValidationException::withMessages([
                'resolution' => __('vendor.rejection_reason_required'),
            ]);
        }

        if ($toStatus === 'SETTLED' && ($data['settled_amount'] ?? null) === null) {
            throw ValidationException::withMessages([
                'settled_amount' => __('vendor.settled_amount_required'),
            ]);
        }

        return DB::transaction(function () use ($claim, $toStatus, $data): WarrantyClaim {
            $claim->forceFill([
                'status' => $toStatus,
                'resolution' => $data['resolution'] ?? $claim->resolution,
                'settled_amount' => $data['settled_amount'] ?? $claim->settled_amount,
                'resolved_at' => in_array($toStatus, ['APPROVED', 'REJECTED', 'SETTLED'], true)
                    ? CarbonImmutable::now()->toDateString()
                    : $claim->resolved_at,
            ])->save();

            return $claim->fresh();
        });
    }
}
