<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The shared ground of Fabric Requirement's master lists (AnnotechERP 13/01):
 * one view and one manage permission, and Brickland's "Any?" boxes — a
 * criterion is either a value or ticked Any, which is stored as blank.
 */
abstract class FabricPlanningResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'fabricplanning.master.view';
    }

    public function managePermission(): string
    {
        return 'fabricplanning.master.manage';
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * Each criterion => its Any box. A ticked Any blanks the value; a value
     * left blank needs Any ticked.
     *
     * @return array<string, string>
     */
    abstract protected function anyBoxes(): array;

    /** @param  array<string, mixed>  $data @return array<string, mixed> */
    protected function applyAny(array $data, ?Model $row): array
    {
        foreach ($this->anyBoxes() as $column => $any) {
            if (! array_key_exists($column, $data) && ! array_key_exists($any, $data) && $row) {
                continue;
            }
            $ticked = (bool) ($data[$any] ?? false);
            $value = $data[$column] ?? ($row && ! array_key_exists($column, $data) ? $row->getAttribute($column) : null);
            if ($ticked) {
                $data[$column] = null;
            } elseif ($value === null || $value === '') {
                throw ValidationException::withMessages([$column => __('fabricplanning.master.pick_or_any')]);
            }
            unset($data[$any]);
        }

        return $data;
    }

    /** The Any boxes as the screen shows them: ticked where the value is blank. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        foreach ($this->anyBoxes() as $column => $any) {
            $out[$any] = $row->getAttribute($column) === null;
        }

        return $out;
    }
}
