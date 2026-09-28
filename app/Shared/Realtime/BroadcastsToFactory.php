<?php

declare(strict_types=1);

namespace App\Shared\Realtime;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * The channels a floor event goes to (SRS 29).
 *
 * Both the company and the factory, because two different screens are
 * listening: a company-wide dashboard that shows every factory, and a factory
 * screen that shows one. Broadcasting only to the factory would leave the
 * dashboard stale; only to the company would push Gazipur's events at a manager
 * who cannot see Gazipur.
 */
trait BroadcastsToFactory
{
    /**
     * @return list<PrivateChannel>
     */
    protected function factoryChannels(string $companyId, ?string $factoryId): array
    {
        $channels = [new PrivateChannel('company.'.$companyId)];

        if ($factoryId !== null) {
            $channels[] = new PrivateChannel('factory.'.$factoryId);
        }

        return $channels;
    }
}
