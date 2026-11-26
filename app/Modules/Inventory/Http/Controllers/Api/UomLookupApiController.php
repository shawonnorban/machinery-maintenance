<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * The units of measure the unit pick lists offer (trims, bookings, budgets,
 * challans, sub-contract quotations, the material masters): the active
 * platform units plus the company's own. Open to anyone who may see one of
 * the screens that asks for a unit.
 */
class UomLookupApiController extends ApiController
{
    /** Each screen's own view permission; any one of them opens the list. */
    private const SCREENS = [
        'merchandise.master.view', 'merchandise.accessories.view', 'merchandise.booking.view', 'merchandise.costing.view',
        'merchandise.sample.view', 'material.master.view', 'dyehouse.master.view',
    ];

    public function index(): JsonResponse
    {
        $caller = $this->caller();
        if (! collect(self::SCREENS)->contains(fn (string $permission) => $caller->can($permission))) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        return ApiResponse::ok(UomCodes::options($caller->companyId));
    }
}
