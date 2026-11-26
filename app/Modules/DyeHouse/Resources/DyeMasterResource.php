<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;

/**
 * The shared ground of Dye House's master lists (AnnotechERP 17/01): one view
 * and one manage permission.
 */
abstract class DyeMasterResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'dyehouse.master.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.master.manage';
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
