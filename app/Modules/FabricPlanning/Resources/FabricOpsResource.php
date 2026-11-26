<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;

/**
 * The shared ground of Fabric Requirement's operation lists (AnnotechERP
 * 13/02): one view and one manage permission over the plans.
 */
abstract class FabricOpsResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'fabricplanning.plan.view';
    }

    public function managePermission(): string
    {
        return 'fabricplanning.plan.manage';
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
