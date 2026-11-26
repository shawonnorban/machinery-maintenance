<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;

/**
 * The shared ground of Work Study's master lists (AnnotechERP 14/01): one view
 * and one manage permission.
 */
abstract class WorkStudyResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'workstudy.master.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.master.manage';
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
