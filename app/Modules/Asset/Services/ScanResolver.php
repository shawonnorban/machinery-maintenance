<?php

declare(strict_types=1);

namespace App\Modules\Asset\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Resolves a scanned QR token to a record plus the actions the scanner is
 * actually permitted to take (SRS 8, Data Dictionary 5.2).
 *
 * The token is not a credential. It identifies; it does not authorise. A
 * resolved scan still runs every permission and policy check, and the tenant
 * scope means a token belonging to another company simply does not resolve.
 */
class ScanResolver
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * A token that does not resolve returns null whether it never existed or
     * belongs to another tenant. The caller renders 404 either way, so a
     * scanned label cannot be used to probe for foreign assets.
     */
    public function asset(string $token): ?Asset
    {
        if (! $this->looksLikeToken($token)) {
            return null;
        }

        return Asset::query()
            ->with(['type:id,name', 'factory:id,name', 'location:id,name,full_path'])
            ->where('qr_code', $token)
            ->first();
    }

    public function location(string $token): ?AssetLocation
    {
        if (! $this->looksLikeToken($token)) {
            return null;
        }

        return AssetLocation::query()
            ->with('factory:id,name')
            ->where('qr_code', $token)
            ->first();
    }

    /**
     * Role-aware actions for the scanned asset (SRS 8).
     *
     * Returned as data rather than decided in a view, so the Next.js scan
     * screen (`app/(app)/scan/[code]/page.js`) and any future consumer
     * return the same set. Routes are relative Next.js paths, not absolute
     * URLs — the scan landing page itself now lives in Next.js too (Phase
     * F completion), so there is no cross-origin hand-off left to build
     * these against, and being relative is what lets each one carry the
     * scanned asset as a query param instead of making the technician pick
     * the machine again a second time.
     *
     * `$actor` is passed in explicitly (`Gate::forUser()`, never the
     * ambient `Gate::allows()`) because this is called from an API-token
     * context as often as a session one, and only the token's own
     * `AuthenticateApiToken` middleware resolver knows who that is — the
     * same guard-agnostic-caller fix applied earlier to
     * `ManageCompanyUser::assertNotSelf()`. A null actor (a machine-client
     * token, which cannot meaningfully scan a physical label) gets no
     * actions at all rather than a Gate call that would silently deny
     * everything anyway.
     *
     * @return list<array{key: string, label: string, route: string, tone: string}>
     */
    public function actionsFor(Asset $asset, ?User $actor): array
    {
        if ($actor === null) {
            return [];
        }

        $gate = Gate::forUser($actor);
        $actions = [];

        if ($gate->allows('view', $asset)) {
            $actions[] = [
                'key' => 'view',
                'label' => __('scan.view_asset'),
                'route' => '/assets/'.$asset->id,
                'tone' => 'primary',
            ];
        }

        // Reporting a breakdown is the most time-critical action on this
        // screen: a line has stopped and someone is standing at the machine.
        // `asset_id` preselects the machine on arrival — the technician
        // scanned it, so making them pick it again from a list is exactly
        // the one extra step this page exists to remove.
        if ($gate->allows('breakdown.breakdown.create') && ! $asset->isTerminal()) {
            $actions[] = [
                'key' => 'report_breakdown',
                'label' => __('scan.report_breakdown'),
                'route' => '/breakdowns/create?asset_id='.$asset->id,
                'tone' => 'danger',
            ];
        }

        if ($gate->allows('meter.reading.create') && ! $asset->isTerminal()) {
            $actions[] = [
                'key' => 'log_meter',
                'label' => __('scan.log_meter_reading'),
                // Meters live on the asset's own detail page, as a tab —
                // `?tab=metering` opens straight to it instead of landing
                // on the Overview tab and making the technician find it.
                'route' => '/assets/'.$asset->id.'?tab=metering',
                'tone' => 'secondary',
            ];
        }

        if ($gate->allows('transfer', $asset)) {
            $actions[] = [
                'key' => 'transfer',
                'label' => __('scan.transfer'),
                // The "Request transfer" button already sits in this page's
                // own header, always visible — no tab needed to reach it.
                'route' => '/assets/'.$asset->id,
                'tone' => 'secondary',
            ];
        }

        return $actions;
    }

    /**
     * Cheap shape check before touching the database. The token alphabet is
     * fixed (Data Dictionary 5.1), so anything else is a malformed scan.
     */
    private function looksLikeToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{12}$/', $token);
    }
}
