<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Resources;

use App\Modules\ColourLab\Support\ColourLab;
use App\Modules\Erp\Support\ResourceDefinition;

/**
 * The shared ground of Colour Lab's screen-kit resources (AnnotechERP 16/01):
 * one view and one manage permission.
 */
abstract class ColourLabResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'colourlab.view';
    }

    public function managePermission(): string
    {
        return 'colourlab.manage';
    }

    protected function company(): string
    {
        return ColourLab::company();
    }
}
