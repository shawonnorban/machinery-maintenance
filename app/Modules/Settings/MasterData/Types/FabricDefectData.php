<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricRollDefect;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

/**
 * The Knit/Woven defect catalogue an inspector picks from (Inventory spec
 * §4.4) — single source of truth, editable the same way every other
 * reference list is, rather than a hardcoded list only a deploy can change.
 */
class FabricDefectData extends MasterDataType
{
    public function key(): string
    {
        return 'fabric-defects';
    }

    public function model(): string
    {
        return FabricDefect::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    /** The platform seeds the two default lists; a company may add to them. */
    public function sharedWithPlatform(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            Field::enum('fabric_family', FabricDefect::FAMILIES),
            Field::code(48),
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [FabricRollDefect::class => 'fabric_defect_id'];
    }
}
