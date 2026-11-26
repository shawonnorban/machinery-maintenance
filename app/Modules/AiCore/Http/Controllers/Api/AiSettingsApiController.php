<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Http\Controllers\Api;

use App\Modules\AiCore\Models\AiFeature;
use App\Modules\AiCore\Models\AiService;
use App\Modules\AiCore\Resources\AiFeatureResource;
use App\Modules\AiCore\Resources\AiServiceResource;
use App\Modules\AiCore\Services\AiGateway;
use App\Modules\AiCore\Support\AiBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Settings → AI Services: the ERP's AI features (one row each, created on
 * first sight, the file reader running by itself until switched off), and
 * a service's connection test. Services and features are otherwise changed
 * through the ERP resources `/erp/ai-services` and `/erp/ai-features`.
 */
class AiSettingsApiController extends ApiController
{
    public function features(AiFeatureResource $definition): JsonResponse
    {
        $this->allow('settings.company.manage');
        foreach (AiBook::FEATURES as $feature) {
            AiFeature::query()->firstOrCreate(['feature' => $feature], ['auto_run' => $feature === 'FILE_READING', 'enabled' => true]);
        }
        $services = AiService::query()->get(['id', 'provider', 'model'])->keyBy('id');

        return ApiResponse::ok($definition->query()->get()->map(fn (AiFeature $f) => $definition->present($f) + [
            'service' => isset($services[$f->service_id]) ? $services[$f->service_id]->provider : null,
            'service_model' => isset($services[$f->service_id]) ? $services[$f->service_id]->model : null,
        ])->values());
    }

    /** Asks the service one small question; the answer is recorded on the service. */
    public function test(string $id, AiGateway $gateway, AiServiceResource $definition): JsonResponse
    {
        $this->allow('settings.company.manage');
        $service = AiService::query()->findOrFail($id);
        $ok = $gateway->test($service);

        return ApiResponse::ok(['ok' => $ok, 'message' => $service->last_test_message] + $definition->present($service->refresh()));
    }
}
