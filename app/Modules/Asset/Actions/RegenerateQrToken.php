<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetStatusHistory;
use App\Modules\Asset\Services\QrTokenGenerator;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Issues a new QR token for an asset (Data Dictionary 5.5).
 *
 * This invalidates the label physically stuck to the machine, so it is not a
 * routine operation: it exists for a compromised or unreadable label. The
 * change is recorded in the asset's history, because otherwise a scan that
 * suddenly stops working has no explanation anywhere.
 */
class RegenerateQrToken
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QrTokenGenerator $generator,
    ) {}

    public function handle(Asset $asset, ?string $userId = null): Asset
    {
        return DB::transaction(function () use ($asset, $userId): Asset {
            $previous = $asset->qr_code;

            $asset->qr_code = $this->generator->forAsset($this->context->companyId());
            $asset->updated_by = $userId;
            $asset->version++;
            $asset->save();

            AssetStatusHistory::create([
                'asset_id' => $asset->id,
                'from_status' => $asset->status,
                'to_status' => $asset->status,
                'changed_by' => $userId,
                'changed_at' => now(),
                'reason' => "QR token regenerated (was {$previous}). The printed label must be replaced.",
                'source' => 'SYSTEM',
            ]);

            return $asset;
        });
    }
}
