<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Company Management → Dashboard (AnnotechERP UI parity): Brickland's stat
 * cards — people, machines, and the structure counts. People are the
 * company's sign-in accounts until HR & Payroll brings employees.
 */
class CompanyDashboardApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(): JsonResponse
    {
        $this->allow('masterdata.manage');

        $companyId = $this->context->companyId();
        $members = CompanyUser::query()->where('company_id', $companyId);
        $machines = Asset::query()->whereIn('current_factory_id', $this->context->accessibleFactoryIds());

        return ApiResponse::ok([
            'employees' => ['total' => (clone $members)->count(), 'active' => (clone $members)->where('status', 'ACTIVE')->count()],
            'machines' => ['total' => (clone $machines)->count(), 'active' => (clone $machines)->where('status', 'RUNNING')->count()],
            'buildings' => Building::query()->count(),
            'floors' => Floor::query()->count(),
            'departments' => Department::query()->count(),
            'sections' => Section::query()->count(),
            'lines' => ProductionLine::query()->count(),
        ]);
    }
}
