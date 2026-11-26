<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use App\Modules\AiCore\Models\AiFeature;
use App\Modules\AiCore\Models\AiService;
use Carbon\CarbonImmutable;

/**
 * Which AI service does a feature's work, as Settings → AI Services says: a
 * feature switched on, pointed at a service that is switched on. Without
 * that, the feature has no AI and says so — nothing falls back to a key in
 * the server's environment.
 */
class AiGateway
{
    public function __construct(private readonly AiClientFactory $factory) {}

    public function serviceFor(string $feature): ?AiService
    {
        $row = AiFeature::query()->where('feature', $feature)->first();
        if (! $row || ! $row->enabled || ! $row->service_id) {
            return null;
        }
        $service = AiService::query()->whereKey($row->service_id)->first();

        return $service && $service->enabled ? $service : null;
    }

    public function clientFor(string $feature): ?AiClient
    {
        $service = $this->serviceFor($feature);

        return $service ? $this->factory->make($service) : null;
    }

    /** Whether the feature runs by itself (a file read the moment it is uploaded). */
    public function autoRuns(string $feature): bool
    {
        return (bool) AiFeature::query()->where('feature', $feature)->value('auto_run') && $this->serviceFor($feature) !== null;
    }

    /**
     * Asks the service one small question to prove the key and model work,
     * and records the result on the service.
     */
    public function test(AiService $service): bool
    {
        try {
            $out = $this->factory->make($service)->structured(
                'You check that an API connection works.',
                [['kind' => 'text', 'text' => 'Reply with ok set to true.']],
                ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false],
                2000,
            );
            $ok = ($out['ok'] ?? false) === true;
            $message = $ok ? __('aicore.test.ok') : __('aicore.test.odd');
        } catch (AiException $e) {
            $ok = false;
            $message = $e->getMessage();
        }
        $service->forceFill(['last_tested_at' => CarbonImmutable::now(), 'last_test_ok' => $ok, 'last_test_message' => mb_strimwidth($message, 0, 300, '…')])->save();

        return $ok;
    }
}
