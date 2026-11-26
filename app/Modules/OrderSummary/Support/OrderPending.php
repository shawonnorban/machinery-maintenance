<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Shared\Formula\Formula;
use Illuminate\Support\Collection;

/**
 * Norban's pending rules over Order Summary rows (as the sheet reads them) —
 * the LDD / RFI pending reports, the dashboard and the daily pending-work
 * notification share them.
 */
final class OrderPending
{
    public const TYPES = ['fabric_booking_pending', 'trims_booking_pending', 'base_hanger_pending', 'black_seal_pending', 'ffp_gss_pending'];

    /** Shipped out: nothing is pending on it any more. */
    public static function shipped(array $v): bool
    {
        return mb_strtolower((string) ($v['shipment_status'] ?? '')) === 'ship out';
    }

    /**
     * The orders a pending report lists and what is pending on each (Norban's
     * rules, by column): booking date missing → Booking Pending, status not
     * done → Status Pending; sample submit/approve dates missing; GSS submit,
     * GSS comments, then FFP not approved.
     *
     * @return Collection<int, array{values: array, pending: string, status: ?string}>
     */
    public static function rows(Collection $rows, string $type): Collection
    {
        $blank = fn ($v, $k) => Formula::blank($v[$k] ?? null);
        $has = fn ($v, $k, string $word) => str_contains(mb_strtolower((string) ($v[$k] ?? '')), $word);

        return $rows->map(function ($v) use ($type, $blank, $has) {
            [$pending, $status] = match ($type) {
                'fabric_booking_pending' => [$blank($v, 'fabric_booking_date') ? 'booking' : ($has($v, 'fabric_status', 'delivery done') ? null : 'status'), $v['fabric_status'] ?? null],
                'trims_booking_pending' => [$blank($v, 'trims_booking_date') ? 'booking' : ($has($v, 'trims_status', 'all trims inhouse') ? null : 'status'), $v['trims_status'] ?? null],
                'base_hanger_pending' => [$blank($v, 'base_hanger_submit_date') ? 'submission' : ($blank($v, 'base_hanger_approv_date') ? 'approval' : null), $v['base_hanger_status'] ?? null],
                'black_seal_pending' => [$blank($v, 'black_ss_fit_submit_date') ? 'submission' : ($blank($v, 'black_ss_fit_approv_date') ? 'approval' : null), $v['black_ss_fit_status'] ?? null],
                'ffp_gss_pending' => [$blank($v, 'gss_pp_submit_date') ? 'gss_submission' : ($blank($v, 'gss_pp_comments_date') ? 'gss_comments' : ($has($v, 'ffp_status', 'approved') ? null : 'ffp')), $v['ffp_status'] ?? $v['gss_pp_status'] ?? null],
            };

            return $pending === null ? null : ['values' => $v, 'pending' => $pending, 'status' => $status];
        })->filter()->values();
    }
}
