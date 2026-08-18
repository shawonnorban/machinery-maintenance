<?php

declare(strict_types=1);

namespace App\Modules\Asset\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

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
     * Returned as data rather than decided in Blade, so the mobile scan
     * screen and the future API return the same set.
     *
     * @return list<array{key: string, label: string, route: string, tone: string}>
     */
    public function actionsFor(Asset $asset): array
    {
        $actions = [];

        if (Gate::allows('view', $asset)) {
            $actions[] = [
                'key' => 'view',
                'label' => __('scan.view_asset'),
                'route' => route('app.assets.show', $asset),
                'tone' => 'primary',
            ];
        }

        // Reporting a breakdown is the most time-critical action on this
        // screen: a line has stopped and someone is standing at the machine.
        if (Gate::allows('breakdown.breakdown.create') && ! $asset->isTerminal()) {
            $actions[] = [
                'key' => 'report_breakdown',
                'label' => __('scan.report_breakdown'),
                // The breakdown module lands at build order step 17. Until
                // then the action is listed but not linked, rather than
                // pointing at a route that would 404.
                'route' => Route::has('app.breakdowns.create')
                    ? route('app.breakdowns.create', ['asset' => $asset->id])
                    : '',
                'tone' => 'danger',
            ];
        }

        if (Gate::allows('meter.reading.create') && ! $asset->isTerminal()) {
            $actions[] = [
                'key' => 'log_meter',
                'label' => __('scan.log_meter_reading'),
                'route' => Route::has('app.meters.create')
                    ? route('app.meters.create', ['asset' => $asset->id])
                    : '',
                'tone' => 'secondary',
            ];
        }

        if (Gate::allows('transfer', $asset)) {
            $actions[] = [
                'key' => 'transfer',
                'label' => __('scan.transfer'),
                'route' => route('app.assets.transfer.create', $asset),
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
