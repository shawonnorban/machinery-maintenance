<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Http\Controllers\Api;

use App\Modules\CommandCenter\Support\CommandDeck;
use App\Modules\Erp\Support\DemoResource;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Command Center (AnnotechERP 27): what its screens' pickers offer — the
 * company's staff and departments. The lists themselves are ERP resources
 * (`/erp/cc-*`).
 */
class CommandCenterApiController extends ApiController
{
    /** Every screen group's view or manage right opens the pickers. */
    public const AREAS = ['command'];

    public function options(): JsonResponse
    {
        $this->allowEither(...array_merge(...array_map(fn ($a) => ["commandcenter.{$a}.view", "commandcenter.{$a}.manage"], self::AREAS)));
        $pairs = fn (array $map) => array_map(fn ($k, $v) => ['value' => (string) $k, 'label' => $v], array_keys($map), array_values($map));

        return ApiResponse::ok(['staff' => $pairs(DemoResource::staff()), 'departments' => $pairs(DemoResource::departments())]);
    }

    /** Chief Executive Command Deck: the factory-wide figures, panels, activity and alerts (see CommandDeck). */
    public function deck(CommandDeck $deck): JsonResponse
    {
        $this->allowEither('commandcenter.command.view', 'commandcenter.command.manage');

        return ApiResponse::ok($deck->build());
    }
}
