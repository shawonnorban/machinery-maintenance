<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;

/**
 * The shared ground of Capacity Planning's master lists (AnnotechERP 12/01):
 * one view and one manage permission.
 */
abstract class PlanningMasterResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'planning.master.view';
    }

    public function managePermission(): string
    {
        return 'planning.master.manage';
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
