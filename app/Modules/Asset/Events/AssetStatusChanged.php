<?php

declare(strict_types=1);

namespace App\Modules\Asset\Events;

use App\Modules\Asset\Models\Asset;
use App\Shared\Broadcasting\AdvisoryBroadcast;
use App\Shared\Realtime\BroadcastsToFactory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A machine changed state (SRS 29).
 *
 * Carries where it came from as well as where it went. A list row that only
 * learns the new status has to guess whether to move the machine between
 * groups, and a dashboard counting machines by status cannot decrement the one
 * it left.
 */
class AssetStatusChanged implements ShouldBroadcast
{
    use AdvisoryBroadcast;
    use BroadcastsToFactory;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Asset $asset,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->factoryChannels($this->asset->company_id, $this->asset->current_factory_id);
    }

    public function broadcastAs(): string
    {
        return 'asset.status-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->asset->id,
            'asset_code' => $this->asset->asset_code,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'criticality' => $this->asset->criticality,
        ];
    }
}
