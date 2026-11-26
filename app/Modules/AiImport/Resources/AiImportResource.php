<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Resources;

use App\Modules\AiImport\Support\ImportBook;
use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * The shared ground of AI File & PO Import's lists (AnnotechERP 26): one view
 * and one manage permission per screen group, the company's buyers, a row
 * shown with its buyer by name, and percentages held to 0–100.
 */
abstract class AiImportResource extends ResourceDefinition
{
    /** The screen group whose permissions open the list: intake (26/01) … */
    protected function area(): string
    {
        return 'intake';
    }

    public function viewPermission(): string
    {
        return "aiimport.{$this->area()}.view";
    }

    public function managePermission(): string
    {
        return "aiimport.{$this->area()}.manage";
    }

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->orderBy('created_at')->orderBy('id');
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        if (array_key_exists('buyer_id', $out)) {
            $out['buyer'] = ImportBook::buyers()[$row->getAttribute('buyer_id')] ?? null;
        }

        return $out;
    }

    public function afterSave(Model $row, bool $created): void
    {
        ImportBook::forget();
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** A buyer of the company, or none ("Any", "Not identified"). */
    protected function buyerRule(): array
    {
        return ['nullable', 'string', Rule::exists('buyers', 'id')->where('company_id', $this->company())];
    }

    /** @return list<string> */
    protected function percent(): array
    {
        return ['sometimes', 'numeric', 'min:0', 'max:100'];
    }

    /** @return list<string> */
    protected function count(): array
    {
        return ['sometimes', 'integer', 'min:0', 'max:100000000'];
    }
}
